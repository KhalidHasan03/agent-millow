import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { fetchHealth, type HealthResponse } from './api/health'
import { useAuth } from './contexts/AuthContext'

type LoadState = 'loading' | 'ready' | 'error'

const dateFormatter = new Intl.DateTimeFormat(undefined, {
  dateStyle: 'medium',
  timeStyle: 'medium',
})

const foundations = [
  'Isolated MySQL 8.4 on port 3307',
  'Laravel 12 API with Sanctum',
  'Request IDs and security headers',
  'Database name safety guard',
  'Database-backed queue, cache, and sessions',
  'Typed React console with Vite proxy',
]

function App() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [health, setHealth] = useState<HealthResponse | null>(null)
  const [loadState, setLoadState] = useState<LoadState>('loading')
  const [error, setError] = useState<string | null>(null)

  const loadHealth = useCallback(async (signal?: AbortSignal) => {
    try {
      const payload = await fetchHealth(signal)
      setHealth(payload)
      setLoadState('ready')
    } catch (cause) {
      if (cause instanceof DOMException && cause.name === 'AbortError') {
        return
      }

      setHealth(null)
      setLoadState('error')
      setError(cause instanceof Error ? cause.message : 'Unable to reach the API')
    }
  }, [])

  const refresh = useCallback(() => {
    setLoadState('loading')
    setError(null)
    void loadHealth()
  }, [loadHealth])

  useEffect(() => {
    const controller = new AbortController()
    const timeout = window.setTimeout(() => {
      void loadHealth(controller.signal)
    }, 0)

    return () => {
      window.clearTimeout(timeout)
      controller.abort()
    }
  }, [loadHealth])

  const isHealthy = health?.status === 'ok'
  const isDegraded = loadState !== 'loading' && !isHealthy
  const statusLabel =
    loadState === 'loading' ? 'CHECKING' : isHealthy ? 'OK' : 'DEGRADED'
  const headline =
    loadState === 'loading'
      ? 'Running diagnostics'
      : isHealthy
        ? 'All systems operational'
        : 'Degraded service'
  const description =
    loadState === 'loading'
      ? 'Checking the API, database, and local runtime.'
      : isHealthy
        ? 'Laravel is connected to the isolated MySQL 8.4 instance. Phase 0 is ready for review.'
        : 'The API or database is unavailable. Check the backend process, MySQL service, and logs.'
  const statusTone = isDegraded
    ? 'border-rose-400/30 bg-rose-400/10 text-rose-200'
    : loadState === 'loading'
      ? 'border-amber-300/30 bg-amber-300/10 text-amber-100'
      : 'border-emerald-300/30 bg-emerald-300/10 text-emerald-200'
  const details = [
    { label: 'Database', value: health?.database.database ?? '—' },
    { label: 'MySQL', value: health?.database.version ?? '—' },
    { label: 'PHP', value: health?.runtime?.php ?? '—' },
    { label: 'Laravel', value: health?.runtime?.laravel ?? '—' },
    { label: 'Queue', value: health?.queue ?? '—' },
    { label: 'Cache', value: health?.cache ?? '—' },
  ]

  return (
    <main className="flex min-h-screen flex-col bg-[#08090d] text-zinc-100">
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
          <button
            type="button"
            onClick={() => void logout().then(() => navigate('/login'))}
            className="cursor-pointer text-zinc-400 hover:text-zinc-100"
          >
            Logout
          </button>
        </div>
      </header>
      <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col px-6 py-10 sm:px-8 lg:px-12">
        <header className="flex flex-col gap-6 border-b border-white/10 pb-8 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p className="mb-3 text-xs font-semibold uppercase tracking-[0.3em] text-emerald-300/80">
              Local control plane
            </p>
            <h1 className="text-4xl font-semibold tracking-tight sm:text-5xl">
              Agent Millow
            </h1>
            <p className="mt-4 max-w-2xl text-sm leading-6 text-zinc-400 sm:text-base">
              Conversations, user-scoped memories, approval-gated tools, and an
              append-only audit trail. Every agent action passes through Laravel
              validation, risk policy, approvals, and audit.
            </p>
          </div>
          <button
            type="button"
            onClick={refresh}
            disabled={loadState === 'loading'}
            className="inline-flex cursor-pointer items-center justify-center rounded-full border border-white/15 bg-white/5 px-5 py-2.5 text-sm font-medium text-zinc-100 transition hover:border-emerald-300/40 hover:bg-emerald-300/10 disabled:cursor-wait disabled:opacity-60"
          >
            {loadState === 'loading' ? 'Checking…' : 'Refresh status'}
          </button>
        </header>

        <section className="grid flex-1 gap-6 py-10 lg:grid-cols-[1.25fr_0.75fr]">
          <article className="rounded-3xl border border-white/10 bg-white/[0.03] p-6 shadow-2xl shadow-black/20 sm:p-8">
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div>
                <p className="text-sm text-zinc-400">System status</p>
                <h2 className="mt-2 text-2xl font-semibold sm:text-3xl" aria-live="polite">
                  {headline}
                </h2>
              </div>
              <span
                className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold tracking-[0.18em] ${statusTone}`}
              >
                <span
                  className={`h-1.5 w-1.5 rounded-full ${
                    loadState === 'loading'
                      ? 'animate-pulse bg-amber-300'
                      : isHealthy
                        ? 'bg-emerald-300'
                        : 'bg-rose-300'
                  }`}
                />
                {statusLabel}
              </span>
            </div>

            <p className="mt-4 max-w-xl text-sm leading-6 text-zinc-400">{description}</p>

            {error ? (
              <div className="mt-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 p-4 text-sm text-rose-100">
                {error}
              </div>
            ) : null}

            <dl className="mt-8 grid gap-3 sm:grid-cols-2">
              {details.map((detail) => (
                <div
                  key={detail.label}
                  className="rounded-2xl border border-white/10 bg-black/20 px-4 py-3"
                >
                  <dt className="text-xs font-medium uppercase tracking-[0.18em] text-zinc-500">
                    {detail.label}
                  </dt>
                  <dd className="mt-1 truncate font-mono text-sm text-zinc-100" title={detail.value}>
                    {detail.value}
                  </dd>
                </div>
              ))}
            </dl>

            <div className="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-5 text-xs text-zinc-500">
              <span>
                Environment: <span className="font-mono text-zinc-300">{health?.environment ?? '—'}</span>
              </span>
              <span>
                Last checked:{' '}
                <span className="font-mono text-zinc-300">
                  {health ? dateFormatter.format(new Date(health.timestamp)) : 'Pending'}
                </span>
              </span>
            </div>
          </article>

          <aside className="flex flex-col gap-6">
            <section className="rounded-3xl border border-white/10 bg-white/[0.03] p-6">
              <p className="text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500">
                Foundation
              </p>
              <ul className="mt-5 space-y-3">
                {foundations.map((item) => (
                  <li key={item} className="flex gap-3 text-sm leading-6 text-zinc-300">
                    <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-300" />
                    {item}
                  </li>
                ))}
              </ul>
            </section>

            <section className="rounded-3xl border border-emerald-300/15 bg-emerald-300/[0.04] p-6">
              <p className="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-300/70">
                Try it
              </p>
              <p className="mt-3 text-sm leading-6 text-zinc-300">
                Open Conversations to chat with the offline fake provider, review or
                edit Memories, run the safe Echo tool, approve the guarded Echo demo
                tool, and inspect every action in Audit.
              </p>
            </section>
          </aside>
        </section>

        <footer className="flex flex-col gap-2 border-t border-white/10 py-6 text-xs text-zinc-500 sm:flex-row sm:items-center sm:justify-between">
          <span>API: http://localhost:8000</span>
          <span>Console: http://localhost:5173</span>
        </footer>
      </div>
    </main>
  )
}

export default App
