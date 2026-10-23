import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { api } from '../lib/api'
import type { AuditEvent, AuditResponse } from '../types'

export function AuditPage() {
  const { user, logout } = useAuth()
  const nav = useNavigate()
  const [events, setEvents] = useState<AuditEvent[]>([])
  const [eventFilter, setEventFilter] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let mounted = true
    void (async () => {
      setLoading(true)
      setError(null)
      try {
        const query = eventFilter.trim() === '' ? '' : `?event=${encodeURIComponent(eventFilter.trim())}`
        const res = await api.get<AuditResponse>(`/audit${query}`)
        if (mounted) setEvents(res.data)
      } catch (err) {
        if (mounted) setError(err instanceof Error ? err.message : 'Failed to load audit history')
      } finally {
        if (mounted) setLoading(false)
      }
    })()
    return () => {
      mounted = false
    }
  }, [eventFilter, reloadKey])

  return (
    <div className="min-h-screen bg-[#08090d] text-zinc-100">
      <header className="border-b border-white/10 px-6 py-4 flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Link to="/" className="font-semibold">Agent Millow</Link>
          <Link to="/conversations" className="text-zinc-400 hover:text-zinc-100">Conversations</Link>
          <Link to="/memories" className="text-zinc-400 hover:text-zinc-100">Memories</Link>
          <Link to="/tools" className="text-zinc-400 hover:text-zinc-100">Tools</Link>
          <Link to="/audit" className="text-zinc-400 hover:text-zinc-100">Audit</Link>
        </div>
        <div className="flex items-center gap-3 text-sm">
          <span className="text-zinc-400">{user?.email}</span>
          <button onClick={() => void logout().then(() => nav('/login'))} className="text-zinc-400 hover:text-zinc-100">Logout</button>
        </div>
      </header>
      <main className="max-w-5xl mx-auto px-6 py-8 space-y-6">
        <div className="flex items-end justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold">Audit history</h1>
            <p className="text-sm text-zinc-400">Append-only record of actions performed under your account.</p>
          </div>
          <div className="flex items-center gap-2">
            <input
              value={eventFilter}
              onChange={(e) => setEventFilter(e.target.value)}
              placeholder="Filter by event, e.g. tool.succeeded"
              className="w-64 rounded-lg border border-white/10 bg-black/30 px-3 py-2 text-sm outline-none focus:border-white/30"
            />
            <button
              onClick={() => setReloadKey((k) => k + 1)}
              className="rounded-lg border border-white/10 px-3 py-2 text-sm text-zinc-300 hover:bg-white/5"
            >
              Refresh
            </button>
          </div>
        </div>

        {error && <div className="rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-sm text-rose-300">{error}</div>}

        {loading ? (
          <div className="text-zinc-400">Loading…</div>
        ) : (
          <div className="rounded-2xl border border-white/10 bg-white/[0.02] overflow-auto">
            <table className="w-full text-sm">
              <thead className="bg-black/30">
                <tr>
                  <th className="text-left p-2">Event</th>
                  <th className="text-left p-2">Subject</th>
                  <th className="text-left p-2">IP</th>
                  <th className="text-left p-2">Request ID</th>
                  <th className="text-left p-2">Time</th>
                </tr>
              </thead>
              <tbody>
                {events.map((e) => (
                  <tr key={e.id} className="border-t border-white/10">
                    <td className="p-2">{e.event}</td>
                    <td className="p-2 text-zinc-400">{e.subject_type ? `${e.subject_type}#${e.subject_id}` : '—'}</td>
                    <td className="p-2 text-zinc-400">{e.ip_address || '—'}</td>
                    <td className="p-2 text-zinc-400 font-mono text-xs">{e.request_id || '—'}</td>
                    <td className="p-2 text-zinc-400">{new Date(e.created_at).toLocaleString()}</td>
                  </tr>
                ))}
                {events.length === 0 && (
                  <tr>
                    <td colSpan={5} className="p-4 text-zinc-400">
                      No audit events found{eventFilter.trim() ? ` for "${eventFilter.trim()}"` : ''}.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        )}
      </main>
    </div>
  )
}
