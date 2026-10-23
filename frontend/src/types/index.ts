export interface User {
  id: number
  name: string
  email: string
  role: string
  email_verified_at: string | null
  created_at: string
}

export interface AuthResponse {
  user: User
  token?: string | null
}

export interface Conversation {
  id: number
  title: string | null
  status: string
  provider: string | null
  model: string | null
  last_message_at: string | null
  created_at: string
  updated_at: string
}

export interface Message {
  id: number
  conversation_id: number
  role: 'system' | 'user' | 'assistant' | 'tool'
  content: string
  metadata: Record<string, unknown> | null
  created_at: string
}

export interface MessagesResponse {
  data: Message[]
}

export interface ConversationsResponse {
  data: Conversation[]
}

export interface Provider {
  name: string
  enabled: boolean
  ready: boolean
  supports_streaming: boolean
}

export interface ProvidersResponse {
  data: Provider[]
  meta?: {
    default?: string
  }
}

export interface Memory {
  id: number
  key: string | null
  title: string
  content: string
  category: string
  source: string
  confidence: number
  sensitive: boolean
  pinned: boolean
  last_accessed_at: string | null
  created_at: string
  updated_at: string
}

export interface MemoriesResponse {
  data: Memory[]
}

export interface ToolDef {
  name: string
  display_name: string
  description: string
  risk_level: string
  approval_required: boolean
  input_schema: Record<string, unknown>
}

export interface ToolsResponse {
  data: ToolDef[]
}

export interface ToolCallResult {
  status: string
  tool_call_id: number
  request_id?: string
  result?: unknown
}

export interface AuditEvent {
  id: number
  event: string
  user_id: number | null
  subject_type: string | null
  subject_id: number | null
  request_id: string | null
  ip_address: string | null
  context: Record<string, unknown> | null
  created_at: string
}

export interface AuditResponse {
  data: AuditEvent[]
}
