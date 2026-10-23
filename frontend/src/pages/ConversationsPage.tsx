import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { api } from '../lib/api'
import type { Conversation, ConversationsResponse, ProvidersResponse } from '../types'

export function ConversationsPage() {
  const { user, logout } = useAuth()
  const nav = useNavigate()
  const [conversations, setConversations] = useState<Conversation[]>([])
  const [providers, setProviders] = useState<string[]>([])
  const [loading, setLoading] = useState(true)
  const [title, setTitle] = useState('')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let mounted = true
    void (async () => {
      setLoading(true)
      setError(null)
      try {
        const [c, p] = await Promise.all([
          api.get<ConversationsResponse>('/conversations'),
          api.get<ProvidersResponse>('/providers').catch(() => ({ data: [] })),
        ])
        if (mounted) {
          setConversations(c.data)
          setProviders(p.data.filter((x) => x.ready).map((x) => x.name))
        }
      } catch (err) {
        if (mounted) setError(err instanceof Error ? err.message : 'Failed to load')
      } finally {
        if (mounted) setLoading(false)
      }
    })()
    return () => {
      mounted = false
    }
  }, [])

  const create = async (e: FormEvent) => {
    e.preventDefault()
    setError(null)
    try {
      const res = await api.post<{ data: Conversation }>('/conversations', { title: title || null })
      setTitle('')
      nav(`/conversations/${res.data.id}`)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create')
    }
  }

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
      <main className="max-w-4xl mx-auto px-6 py-8 space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-semibold">Conversations</h1>
          <span className="text-xs text-zinc-500">Providers: {providers.join(', ') || '—'}</span>
        </div>
        {error && <div className="text-sm text-rose-300">{error}</div>}
        <form onSubmit={create} className="flex gap-2">
          <input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="New conversation title (optional)" className="flex-1 rounded-lg bg-black/30 border border-white/10 px-3 py-2" />
          <button className="rounded-lg bg-emerald-400/90 text-black px-4 py-2">Create</button>
        </form>
        {loading ? <div className="text-zinc-400">Loading…</div> : (
          <ul className="divide-y divide-white/10 rounded-2xl border border-white/10 bg-white/[0.02]">
            {conversations.map((c) => (
              <li key={c.id} className="p-4 flex items-center justify-between">
                <div>
                  <Link to={`/conversations/${c.id}`} className="font-medium">{c.title || `Conversation ${c.id}`}</Link>
                  <p className="text-xs text-zinc-500">{c.status} · {c.provider || 'default'}</p>
                </div>
                <span className="text-xs text-zinc-500">{c.last_message_at ? new Date(c.last_message_at).toLocaleString() : '—'}</span>
              </li>
            ))}
            {conversations.length === 0 && <li className="p-4 text-zinc-400">No conversations yet.</li>}
          </ul>
        )}
      </main>
    </div>
  )
}
