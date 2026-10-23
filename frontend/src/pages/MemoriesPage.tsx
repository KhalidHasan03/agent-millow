import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { api } from '../lib/api'
import type { Memory, MemoriesResponse } from '../types'

export function MemoriesPage() {
  const { user, logout } = useAuth()
  const nav = useNavigate()
  const [memories, setMemories] = useState<Memory[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [title, setTitle] = useState('')
  const [content, setContent] = useState('')
  const [category, setCategory] = useState('fact')

  const refresh = async () => {
    const res = await api.get<MemoriesResponse>('/memories')
    setMemories(res.data)
  }

  useEffect(() => {
    let mounted = true
    void (async () => {
      setLoading(true)
      setError(null)
      try {
        const res = await api.get<MemoriesResponse>('/memories')
        if (mounted) setMemories(res.data)
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
      await api.post('/memories', { title, content, category })
      setTitle('')
      setContent('')
      await refresh()
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create')
    }
  }

  const inspect = async (id: number) => {
    try {
      await api.get(`/memories/${id}`)
      await refresh()
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to inspect')
    }
  }

  const remove = async (id: number) => {
    try {
      await api.delete(`/memories/${id}`)
      await refresh()
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to delete')
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
      <main className="max-w-5xl mx-auto px-6 py-8 space-y-6">
        <h1 className="text-2xl font-semibold">Memories</h1>
        {error && <div className="text-sm text-rose-300">{error}</div>}
        <form onSubmit={create} className="grid grid-cols-1 md:grid-cols-4 gap-2 rounded-2xl border border-white/10 bg-white/[0.02] p-4">
          <input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Title" required className="rounded-lg bg-black/30 border border-white/10 px-3 py-2" />
          <input value={content} onChange={(e) => setContent(e.target.value)} placeholder="Content" required className="md:col-span-2 rounded-lg bg-black/30 border border-white/10 px-3 py-2" />
          <select value={category} onChange={(e) => setCategory(e.target.value)} className="rounded-lg bg-black/30 border border-white/10 px-3 py-2">
            <option value="fact">fact</option>
            <option value="preference">preference</option>
            <option value="instruction">instruction</option>
            <option value="context">context</option>
            <option value="other">other</option>
          </select>
          <button className="md:col-span-4 rounded-lg bg-emerald-400/90 text-black py-2">Create Memory</button>
        </form>
        {loading ? <div className="text-zinc-400">Loading…</div> : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            {memories.map((m) => (
              <article key={m.id} className="rounded-2xl border border-white/10 bg-white/[0.02] p-4 space-y-2">
                <div className="flex items-center justify-between">
                  <h2 className="font-medium">{m.title}</h2>
                  <span className="text-xs text-zinc-500">{m.category} · {m.source}</span>
                </div>
                <p className="text-sm text-zinc-300 whitespace-pre-wrap">{m.content}</p>
                <div className="flex items-center justify-between text-xs text-zinc-500">
                  <span>Confidence {m.confidence}% {m.pinned ? '· pinned' : ''} {m.sensitive ? '· sensitive' : ''}</span>
                  <div className="flex gap-2">
                    <button onClick={() => void inspect(m.id)} className="hover:text-zinc-100">Inspect</button>
                    <button onClick={() => void remove(m.id)} className="hover:text-rose-300">Delete</button>
                  </div>
                </div>
                {m.last_accessed_at && <div className="text-xs text-zinc-600">Last accessed {new Date(m.last_accessed_at).toLocaleString()}</div>}
              </article>
            ))}
            {memories.length === 0 && <div className="text-zinc-400">No memories.</div>}
          </div>
        )}
      </main>
    </div>
  )
}
