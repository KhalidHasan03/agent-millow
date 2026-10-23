const API_BASE = '/api/v1'

type ApiError = Error & { status?: number; errors?: Record<string, string[]>; requestId?: string }

function getCookie(name: string): string | null {
  if (typeof document === 'undefined') {
    return null
  }

  const value = `; ${document.cookie}`
  const parts = value.split(`; ${name}=`)
  if (parts.length === 2) {
    const token = parts.pop()?.split(';').shift()
    return token ? decodeURIComponent(token) : null
  }
  return null
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers)
  if (init.body && !headers.has('Accept')) {
    headers.set('Accept', 'application/json')
  }
  if (init.body && !(init.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  const method = (init.method || 'GET').toUpperCase()
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    const token = getCookie('XSRF-TOKEN')
    if (token && !headers.has('X-XSRF-TOKEN')) {
      headers.set('X-XSRF-TOKEN', token)
    }
  }

  const res = await fetch(`${API_BASE}${path}`, {
    credentials: 'include',
    ...init,
    method,
    headers,
  })

  if (res.status === 204) {
    return {} as T
  }

  const text = await res.text()
  let data: unknown
  try {
    data = text ? JSON.parse(text) : {}
  } catch {
    data = text
  }

  if (!res.ok) {
    const err = new Error((data as { message?: string })?.message || `Request failed (${res.status})`) as ApiError
    err.status = res.status
    if ((data as { errors?: Record<string, string[]> }).errors) {
      err.errors = (data as { errors: Record<string, string[]> }).errors
    }
    const requestId = res.headers.get('X-Request-Id') || (data as { request_id?: string })?.request_id
    if (requestId) err.requestId = requestId
    throw err
  }

  return data as T
}

export const api = {
  get: <T>(path: string, init?: RequestInit) => request<T>(path, { ...init, method: 'GET' }),
  post: <T>(path: string, body?: unknown, init?: RequestInit) => request<T>(path, { ...init, method: 'POST', body: body === undefined ? undefined : JSON.stringify(body) }),
  patch: <T>(path: string, body?: unknown, init?: RequestInit) => request<T>(path, { ...init, method: 'PATCH', body: body === undefined ? undefined : JSON.stringify(body) }),
  put: <T>(path: string, body?: unknown, init?: RequestInit) => request<T>(path, { ...init, method: 'PUT', body: body === undefined ? undefined : JSON.stringify(body) }),
  delete: <T>(path: string, init?: RequestInit) => request<T>(path, { ...init, method: 'DELETE' }),
}

export type { ApiError }
