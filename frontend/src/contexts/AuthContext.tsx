import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import { api } from '../lib/api'
import type { User } from '../types'

interface AuthContextValue {
  user: User | null
  loading: boolean
  login: (email: string, password: string) => Promise<void>
  register: (name: string, email: string, password: string, passwordConfirmation: string) => Promise<void>
  logout: () => Promise<void>
  refresh: () => Promise<void>
}

function extractUser(res: unknown): User | null {
  if (!res || typeof res !== 'object') {
    return null
  }

  const record = res as Record<string, unknown>
  if (record.user && typeof record.user === 'object') {
    return record.user as unknown as User
  }
  if (record.data && typeof record.data === 'object') {
    const data = record.data as Record<string, unknown>
    if (data.user && typeof data.user === 'object') {
      return data.user as unknown as User
    }
    if (typeof data.email === 'string' || typeof data.id === 'number') {
      return data as unknown as User
    }
  }
  if (typeof record.email === 'string' || typeof record.id === 'number') {
    return record as unknown as User
  }

  return null
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  const refresh = async () => {
    try {
      const res = await api.get<unknown>('/auth/user')
      setUser(extractUser(res))
    } catch {
      setUser(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    let mounted = true
    void (async () => {
      try {
        const res = await api.get<unknown>('/auth/user')
        if (mounted) setUser(extractUser(res))
      } catch {
        if (mounted) setUser(null)
      } finally {
        if (mounted) setLoading(false)
      }
    })()
    return () => {
      mounted = false
    }
  }, [])

  const login = async (email: string, password: string) => {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
    const res = await api.post<unknown>('/auth/login', { email, password })
    const user = extractUser(res)
    if (!user) {
      throw new Error('Login response missing user.')
    }
    setUser(user)
  }

  const register = async (name: string, email: string, password: string, passwordConfirmation: string) => {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
    const res = await api.post<unknown>('/auth/register', {
      name,
      email,
      password,
      password_confirmation: passwordConfirmation,
    })
    const user = extractUser(res)
    if (!user) {
      throw new Error('Registration response missing user.')
    }
    setUser(user)
  }

  const logout = async () => {
    try {
      await api.post('/auth/logout')
    } finally {
      setUser(null)
    }
  }

  return <AuthContext.Provider value={{ user, loading, login, register, logout, refresh }}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
