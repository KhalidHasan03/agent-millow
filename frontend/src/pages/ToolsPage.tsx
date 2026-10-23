import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { api } from '../lib/api'
import type { ToolCallResult, ToolDef, ToolsResponse } from '../types'

export function ToolsPage() {
  const { user, logout } = useAuth()
  const nav = useNavigate()
  const [tools, setTools] = useState<ToolDef[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [selected, setSelected] = useState<string>('')
  const [inputJson, setInputJson] = useState('{"message":"hello"}')
  const [result, setResult] = useState<ToolCallResult | null>(null)
  const [pendingId, setPendingId] = useState<number | null>(null)

  useEffect(() => {
    let mounted = true
    void (async () => {
      setLoading(true)
      setError(null)
      try {
        const res = await api.get<ToolsResponse>('/tools')
        if (mounted) {
          setTools(res.data)
          if (res.data[0]) setSelected(res.data[0].name)
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

  const call = async (e: FormEvent) => {
    e.preventDefault()
    setError(null)
    setResult(null)
    setPendingId(null)
    try {
      const input = JSON.parse(inputJson)
      const res = await api.post<ToolCallResult>('/tools/call', { tool: selected, input })
      setResult(res)
      if (res.status === 'pending_approval') setPendingId(res.tool_call_id)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to call tool')
    }
  }

  const approve = async () => {
    if (!pendingId) return
    setError(null)
    try {
      const res = await api.post<ToolCallResult>(`/tools/calls/${pendingId}/approve`)
      setResult(res)
      setPendingId(null)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to approve')
    }
  }

  const reject = async () => {
    if (!pendingId) return
    setError(null)
    try {
      const res = await api.post<ToolCallResult>(`/tools/calls/${pendingId}/reject`)
      setResult(res)
      setPendingId(null)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to reject')
    }
  }

  const tool = tools.find((t) => t.name === selected)

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
        <h1 className="text-2xl font-semibold">Tools</h1>
        {error && <div className="text-sm text-rose-300">{error}</div>}
        {loading ? <div className="text-zinc-400">Loading…</div> : (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <section className="rounded-2xl border border-white/10 bg-white/[0.02] p-4 space-y-2">
              <h2 className="font-medium">Available</h2>
              <ul className="space-y-1">
                {tools.map((t) => (
                  <li key={t.name}>
                    <button onClick={() => setSelected(t.name)} className={`w-full text-left px-2 py-1 rounded ${selected === t.name ? 'bg-white/10' : 'hover:bg-white/5'}`}>
                      <div className="flex items-center justify-between">
                        <span>{t.display_name}</span>
                        <span className="text-xs text-zinc-500">{t.risk_level}</span>
                      </div>
                      <p className="text-xs text-zinc-500">{t.description}</p>
                    </button>
                  </li>
                ))}
              </ul>
            </section>
            <section className="md:col-span-2 rounded-2xl border border-white/10 bg-white/[0.02] p-4 space-y-3">
              <div>
                <h2 className="font-medium">{tool?.display_name} ({selected})</h2>
                <p className="text-sm text-zinc-400">{tool?.description}</p>
                <p className="text-xs text-zinc-500">Risk: {tool?.risk_level} · Approval required: {tool?.approval_required ? 'yes' : 'no'}</p>
              </div>
              <form onSubmit={call} className="space-y-2">
                <label className="block text-sm">
                  <span className="text-zinc-400">Input (JSON)</span>
                  <textarea value={inputJson} onChange={(e) => setInputJson(e.target.value)} rows={6} className="mt-1 w-full rounded-lg bg-black/30 border border-white/10 px-3 py-2 font-mono text-sm" />
                </label>
                <button className="rounded-lg bg-emerald-400/90 text-black px-4 py-2">Call Tool</button>
              </form>
              {result && (
                <div className="rounded-xl border border-white/10 bg-black/30 p-3 space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="text-sm">Status: {result.status}</span>
                    <span className="text-xs text-zinc-500">Call #{result.tool_call_id}</span>
                  </div>
                  {result.result !== undefined && <pre className="text-xs overflow-auto">{JSON.stringify(result.result, null, 2)}</pre>}
                  {pendingId && (
                    <div className="flex gap-2">
                      <button onClick={() => void approve()} className="rounded-lg bg-emerald-400/90 text-black px-3 py-1.5">Approve</button>
                      <button onClick={() => void reject()} className="rounded-lg border border-white/10 px-3 py-1.5">Reject</button>
                    </div>
                  )}
                </div>
              )}
            </section>
          </div>
        )}
      </main>
    </div>
  )
}
