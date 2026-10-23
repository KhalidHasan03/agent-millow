import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { api } from '../lib/api'
import type { Conversation, Message, MessagesResponse } from '../types'

export function ConversationDetailPage() {
  const { id } = useParams()
  const convId = Number(id)
  const { user, logout } = useAuth()
  const nav = useNavigate()
  const [conversation, setConversation] = useState<Conversation | null>(null)
  const [messages, setMessages] = useState<Message[]>([])
  const [content, setContent] = useState('')
  const [loading, setLoading] = useState(true)
  const [sending, setSending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [streaming, setStreaming] = useState(false)
  const bottomRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    let mounted = true
    void (async () => {
      setLoading(true)
      setError(null)
      try {
        const [c, m] = await Promise.all([
          api.get<{ data: Conversation }>(`/conversations/${convId}`),
          api.get<MessagesResponse>(`/conversations/${convId}/messages`),
        ])
        if (mounted) {
          setConversation(c.data)
          setMessages(m.data)
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
  }, [convId])

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  const send = async (e: FormEvent) => {
    e.preventDefault()
    if (!content.trim()) return
    setSending(true)
    setError(null)
    try {
      await api.post(`/conversations/${convId}/messages`, { content })
      setContent('')
      const [c, m] = await Promise.all([
        api.get<{ data: Conversation }>(`/conversations/${convId}`),
        api.get<MessagesResponse>(`/conversations/${convId}/messages`),
      ])
      setConversation(c.data)
      setMessages(m.data)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to send')
    } finally {
      setSending(false)
    }
  }

  const stream = async () => {
    setStreaming(true)
    setError(null)
    try {
      const res = await fetch(`/api/v1/conversations/${convId}/stream`, { credentials: 'include' })
      if (!res.ok) throw new Error(`Stream failed (${res.status})`)
      const reader = res.body?.getReader()
      if (!reader) throw new Error('No stream')
      const decoder = new TextDecoder()
      let buf = ''
      while (true) {
        const { done, value } = await reader.read()
        if (done) break
        buf += decoder.decode(value, { stream: true })
        const parts = buf.split('\n\n')
        buf = parts.pop() || ''
        for (const p of parts) {
          if (p.startsWith('data: ')) {
            try {
              const evt = JSON.parse(p.slice(6))
              if (evt.type === 'token' && typeof evt.content === 'string') {
                // tokens are informational; refresh to show final message
              }
            } catch {
              // ignore
            }
          }
        }
      }
      const [c, m] = await Promise.all([
        api.get<{ data: Conversation }>(`/conversations/${convId}`),
        api.get<MessagesResponse>(`/conversations/${convId}/messages`),
      ])
      setConversation(c.data)
      setMessages(m.data)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Stream failed')
    } finally {
      setStreaming(false)
    }
  }

  return (
    <div className="min-h-screen bg-[#08090d] text-zinc-100 flex flex-col">
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
      <main className="flex-1 max-w-3xl mx-auto w-full px-6 py-6 flex flex-col gap-4">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-semibold">{conversation?.title || `Conversation ${convId}`}</h1>
            <p className="text-xs text-zinc-500">{conversation?.status} · {conversation?.provider || 'default'}</p>
          </div>
          <button onClick={() => void stream()} disabled={streaming} className="rounded-lg border border-white/10 px-3 py-1.5 text-sm disabled:opacity-60">{streaming ? 'Streaming…' : 'Stream reply'}</button>
        </div>
        {error && <div className="text-sm text-rose-300">{error}</div>}
        <div className="flex-1 rounded-2xl border border-white/10 bg-white/[0.02] p-4 space-y-3 overflow-auto min-h-[50vh]">
          {loading ? <div className="text-zinc-400">Loading…</div> : messages.map((m) => (
            <div key={m.id} className={`max-w-[80%] rounded-xl px-3 py-2 ${m.role === 'user' ? 'ml-auto bg-emerald-400/10 border border-emerald-300/20' : 'bg-black/30 border border-white/10'}`}>
              <div className="text-xs text-zinc-500 mb-1">{m.role}</div>
              <div className="whitespace-pre-wrap text-sm">{m.content}</div>
            </div>
          ))}
          <div ref={bottomRef} />
        </div>
        <form onSubmit={send} className="flex gap-2">
          <input value={content} onChange={(e) => setContent(e.target.value)} placeholder="Message..." className="flex-1 rounded-lg bg-black/30 border border-white/10 px-3 py-2" />
          <button disabled={sending} className="rounded-lg bg-emerald-400/90 text-black px-4 py-2 disabled:opacity-60">{sending ? 'Sending…' : 'Send'}</button>
        </form>
      </main>
    </div>
  )
}
