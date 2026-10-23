<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    |
    | Controls whether public self-registration is allowed. In production this
    | is disabled by default so that accounts are provisioned deliberately.
    | This remains independent of the private beta allow-list below.
    |
    */

    'registration' => [
        'enabled' => (bool) env('AGENT_MILLOW_REGISTRATION_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Private beta allow-list
    |--------------------------------------------------------------------------
    |
    | When enabled, registration and login are restricted to the listed email
    | addresses. This lets the platform run as a private beta without exposing
    | account creation to the public. Comparison is case-insensitive.
    |
    */

    'private_beta' => [
        'enabled' => (bool) env('AGENT_MILLOW_PRIVATE_BETA', false),
        'allowed_emails' => array_values(array_filter(array_map(
            'strtolower',
            array_map('trim', explode(',', (string) env('AGENT_MILLOW_ALLOWED_EMAILS', '')))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | API tokens
    |--------------------------------------------------------------------------
    |
    | Personal access tokens are intended for non-browser clients. They are
    | time-limited and named so that users can audit and revoke them. Sessions
    | remain the primary authentication mechanism for the SPA.
    |
    */

    'tokens' => [
        'expiration_minutes' => (int) env('AGENT_MILLOW_TOKEN_EXPIRATION', 10080),
        'default_name' => env('AGENT_MILLOW_TOKEN_NAME', 'api-client'),
        'max_active_per_user' => (int) env('AGENT_MILLOW_TOKEN_LIMIT', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Account level that can manage other users. Every other capability must be
    | granted through policies and tool risk policy, never through the model.
    |
    */

    'admin_role' => env('AGENT_MILLOW_ADMIN_ROLE', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    |
    | Named limiters are registered in the application service provider. These
    | values keep authentication and general API traffic bounded.
    |
    */

    'rate_limits' => [
        'api_per_minute' => (int) env('AGENT_MILLOW_API_RATE_LIMIT', 60),
        'login_per_minute' => (int) env('AGENT_MILLOW_LOGIN_RATE_LIMIT', 5),
        'register_per_minute' => (int) env('AGENT_MILLOW_REGISTER_RATE_LIMIT', 5),
        'sensitive_per_minute' => (int) env('AGENT_MILLOW_SENSITIVE_RATE_LIMIT', 10),
        'chat_per_minute' => (int) env('AGENT_MILLOW_CHAT_RATE_LIMIT', 30),
        'memory_per_minute' => (int) env('AGENT_MILLOW_MEMORY_RATE_LIMIT', 30),
        'tool_per_minute' => (int) env('AGENT_MILLOW_TOOL_RATE_LIMIT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request and transport hardening
    |--------------------------------------------------------------------------
    |
    | Requests larger than the configured byte limit are rejected before they
    | reach application code. HSTS is opt-in and only applied to secure
    | requests. Trusted proxies must be configured explicitly; no proxy is
    | trusted by default.
    |
    */

    'security' => [
        'request_max_bytes' => (int) env('AGENT_MILLOW_REQUEST_MAX_BYTES', 1048576),
        'hsts' => [
            'enabled' => (bool) env('AGENT_MILLOW_HSTS_ENABLED', false),
            'max_age' => (int) env('AGENT_MILLOW_HSTS_MAX_AGE', 31536000),
        ],
        'trusted_proxies' => env('AGENT_MILLOW_TRUSTED_PROXIES'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool quotas
    |--------------------------------------------------------------------------
    |
    | Per-user quotas bound tool activity independently of the request rate
    | limiter. They prevent a user from stacking pending approvals or call
    | volume, even across multiple clients.
    |
    */

    'tools' => [
        'max_calls_per_hour' => (int) env('AGENT_MILLOW_TOOL_CALLS_PER_HOUR', 60),
        'max_pending_approvals' => (int) env('AGENT_MILLOW_TOOL_MAX_PENDING_APPROVALS', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data retention
    |--------------------------------------------------------------------------
    |
    | Retention is enforced by an explicit maintenance command. Deletion never
    | runs automatically outside production, and the command defaults to a
    | dry run unless --force is supplied. Messages are retained indefinitely.
    |
    */

    'retention' => [
        'audit_days' => (int) env('AGENT_MILLOW_AUDIT_RETENTION_DAYS', 365),
        'failed_jobs_days' => (int) env('AGENT_MILLOW_FAILED_JOBS_RETENTION_DAYS', 30),
        'prune_expired_tokens' => (bool) env('AGENT_MILLOW_PRUNE_EXPIRED_TOKENS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory
    |--------------------------------------------------------------------------
    |
    | User-scoped, inspectable memory. Memories are stored as relational data
    | and can be audited; they are retrieved context, not permanent model
    | training.
    |
    */

    'memory' => [
        'max_title_length' => 200,
        'max_content_length' => 10000,
        'max_key_length' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM providers
    |--------------------------------------------------------------------------
    |
    | Provider adapters implement a common interface. Credentials are never
    | exposed to the client and are supplied server-side. In local and testing
    | environments the deterministic fake provider is used unless a real
    | provider is explicitly enabled.
    |
    */

    'providers' => [
        'default' => env('AGENT_MILLOW_DEFAULT_PROVIDER', 'fake'),
        'enabled' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AGENT_MILLOW_ENABLED_PROVIDERS', 'fake'))
        ))),
        'timeout_seconds' => (int) env('AGENT_MILLOW_PROVIDER_TIMEOUT', 30),
        'connect_timeout_seconds' => (int) env('AGENT_MILLOW_PROVIDER_CONNECT_TIMEOUT', 10),
        'stream_timeout_seconds' => (int) env('AGENT_MILLOW_PROVIDER_STREAM_TIMEOUT', 120),
        'retry_times' => (int) env('AGENT_MILLOW_PROVIDER_RETRIES', 2),

        'openai' => [
            'base_url' => env('AGENT_MILLOW_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'key' => env('AGENT_MILLOW_OPENAI_KEY', ''),
            'default_model' => env('AGENT_MILLOW_OPENAI_MODEL', 'gpt-4o-mini'),
            'allowed_models' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env(
                    'AGENT_MILLOW_OPENAI_ALLOWED_MODELS',
                    env('AGENT_MILLOW_OPENAI_MODEL', 'gpt-4o-mini')
                ))
            ))),
            'max_output_tokens' => (int) env('AGENT_MILLOW_OPENAI_MAX_OUTPUT_TOKENS', 1024),
            'pricing' => [
                'input_per_million' => env('AGENT_MILLOW_OPENAI_INPUT_COST_PER_MILLION'),
                'output_per_million' => env('AGENT_MILLOW_OPENAI_OUTPUT_COST_PER_MILLION'),
            ],
        ],

        'fake' => [
            'default_model' => 'fake-model',
            'allowed_models' => ['fake-model'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversations
    |--------------------------------------------------------------------------
    |
    | Bounds for conversation and message handling. Context is trimmed to the
    | most recent messages so provider calls stay within a predictable budget.
    |
    */

    'conversations' => [
        'max_context_messages' => (int) env('AGENT_MILLOW_MAX_CONTEXT_MESSAGES', 50),
        'max_message_length' => (int) env('AGENT_MILLOW_MAX_MESSAGE_LENGTH', 32000),
    ],

];
