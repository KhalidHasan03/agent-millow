export interface HealthResponse {
  status: 'ok' | 'degraded'
  app: string
  environment: string
  timestamp: string
  database: {
    status: string
    driver?: string
    database?: string | null
    version?: string | null
  }
  runtime?: {
    php: string
    laravel: string
  }
  queue?: string
  cache?: string
}

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '/api/v1'

export async function fetchHealth(signal?: AbortSignal): Promise<HealthResponse> {
  const response = await fetch(`${API_BASE_URL}/health`, {
    headers: { Accept: 'application/json' },
    signal,
  })

  if (!response.ok) {
    throw new Error(`Health check failed with status ${response.status}`)
  }

  return (await response.json()) as HealthResponse
}
