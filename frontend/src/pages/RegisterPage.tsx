import { useState, type FormEvent } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'

export function RegisterPage() {
  const { register } = useAuth()
  const nav = useNavigate()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setError(null)
    setLoading(true)
    try {
      await register(name, email, password, passwordConfirmation)
      nav('/')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Registration failed')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-[#08090d] text-zinc-100 flex items-center justify-center px-4">
      <form onSubmit={onSubmit} className="w-full max-w-md rounded-2xl border border-white/10 bg-white/[0.03] p-6 space-y-4">
        <h1 className="text-2xl font-semibold">Register</h1>
        {error && <div className="text-sm text-rose-300">{error}</div>}
        <label className="block text-sm">
          <span className="text-zinc-400">Name</span>
          <input value={name} onChange={(e) => setName(e.target.value)} required className="mt-1 w-full rounded-lg bg-black/30 border border-white/10 px-3 py-2" />
        </label>
        <label className="block text-sm">
          <span className="text-zinc-400">Email</span>
          <input value={email} onChange={(e) => setEmail(e.target.value)} type="email" required className="mt-1 w-full rounded-lg bg-black/30 border border-white/10 px-3 py-2" />
        </label>
        <label className="block text-sm">
          <span className="text-zinc-400">Password</span>
          <input value={password} onChange={(e) => setPassword(e.target.value)} type="password" required className="mt-1 w-full rounded-lg bg-black/30 border border-white/10 px-3 py-2" />
        </label>
        <label className="block text-sm">
          <span className="text-zinc-400">Confirm Password</span>
          <input value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)} type="password" required className="mt-1 w-full rounded-lg bg-black/30 border border-white/10 px-3 py-2" />
        </label>
        <button disabled={loading} className="w-full rounded-lg bg-emerald-400/90 text-black py-2 font-medium disabled:opacity-60">{loading ? 'Creating…' : 'Create account'}</button>
        <p className="text-sm text-zinc-400">Have an account? <Link to="/login" className="text-emerald-300">Sign in</Link></p>
      </form>
    </div>
  )
}
