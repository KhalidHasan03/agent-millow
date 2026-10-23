import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'

export function ProtectedRoute() {
  const { user, loading } = useAuth()
  if (loading) return <div className="min-h-screen bg-[#08090d] text-zinc-100 flex items-center justify-center">Loading…</div>
  if (!user) return <Navigate to="/login" replace />
  return <Outlet />
}
