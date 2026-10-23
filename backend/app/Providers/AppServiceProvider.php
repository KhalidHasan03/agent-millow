<?php

declare(strict_types=1);

namespace App\Providers;

use App\Logging\QueueFailureLogger;
use App\Models\Conversation;
use App\Models\Memory;
use App\Models\Message;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Policies\ConversationPolicy;
use App\Policies\MemoryPolicy;
use App\Policies\MessagePolicy;
use App\Policies\UserPolicy;
use App\Providers\Llm\Adapters\FakeProvider;
use App\Providers\Llm\Adapters\OpenAiCompatibleProvider;
use App\Providers\Llm\Contracts\ProviderAdapter;
use App\Providers\Llm\ProviderRegistry;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\AccessTokenService;
use App\Services\Auth\AuthService;
use App\Services\Auth\BetaAccessPolicy;
use App\Services\Auth\SessionService;
use App\Services\Conversations\ConversationService;
use App\Services\Conversations\MessageService;
use App\Services\Memories\MemoryService;
use App\Services\Tools\Orchestrator\ToolOrchestrator;
use App\Services\Tools\Policy\ToolRiskPolicy;
use App\Services\Tools\Redaction\ToolRedactor;
use App\Services\Tools\Registry\ToolRegistry;
use App\Services\Tools\Validation\ToolInputValidator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuthService::class, fn ($app): AuthService => new AuthService(
            $app->make(AccessTokenService::class),
            $app->make(BetaAccessPolicy::class),
            $app->make(AuditLogger::class),
        ));

        $this->app->singleton(SessionService::class);

        $this->app->singleton(ProviderRegistry::class, function ($app): ProviderRegistry {
            $adapters = [];

            /** @var list<string> $enabled */
            $enabled = config('agent_millow.providers.enabled', ['fake']);

            foreach ($enabled as $name) {
                $adapter = match ($name) {
                    'fake' => $app->make(FakeProvider::class),
                    'openai' => $app->make(OpenAiCompatibleProvider::class),
                    default => null,
                };

                if ($adapter instanceof ProviderAdapter) {
                    $adapters[] = $adapter;
                }
            }

            if ($adapters === []) {
                $adapters[] = $app->make(FakeProvider::class);
            }

            return new ProviderRegistry($adapters);
        });

        $this->app->singleton(ConversationService::class);
        $this->app->singleton(MessageService::class);
        $this->app->singleton(MemoryService::class);
        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(ToolInputValidator::class);
        $this->app->singleton(ToolRiskPolicy::class);
        $this->app->singleton(ToolRedactor::class);
        $this->app->singleton(ToolOrchestrator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->guardLocalDatabase();
        $this->registerSanctumTokenModel();
        $this->registerPolicies();
        $this->configureRateLimiting();
        $this->configureTrustedProxies();
        $this->registerQueueFailureLogging();
    }

    private function registerQueueFailureLogging(): void
    {
        Queue::failing(function (JobFailed $event): void {
            app(QueueFailureLogger::class)->handle($event);
        });
    }

    private function configureTrustedProxies(): void
    {
        $proxies = config('agent_millow.security.trusted_proxies');

        if (! is_string($proxies) || $proxies === '') {
            return;
        }

        TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
    }

    private function registerSanctumTokenModel(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    private function registerPolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::policy(Memory::class, MemoryPolicy::class);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(
            (int) config('agent_millow.rate_limits.api_per_minute', 60)
        )->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(
            (int) config('agent_millow.rate_limits.login_per_minute', 5)
        )->by($this->throttleKey($request)));

        RateLimiter::for('register', fn (Request $request): Limit => Limit::perMinute(
            (int) config('agent_millow.rate_limits.register_per_minute', 5)
        )->by($request->ip()));

        RateLimiter::for('sensitive', fn (Request $request): Limit => Limit::perMinute(
            (int) config('agent_millow.rate_limits.sensitive_per_minute', 10)
        )->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('chat', fn (Request $request): Limit => Limit::perMinute(
            (int) config('agent_millow.rate_limits.chat_per_minute', 30)
        )->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('memory', fn (Request $request): Limit => Limit::perMinute(
            (int) config('agent_millow.rate_limits.memory_per_minute', 30)
        )->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('tool', fn (Request $request): Limit => Limit::perMinute(
            (int) config('agent_millow.rate_limits.tool_per_minute', 30)
        )->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }

    private function throttleKey(Request $request): string
    {
        $email = mb_strtolower((string) $request->input('email'));

        return $email.'|'.$request->ip();
    }

    private function guardLocalDatabase(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        if (config('database.default') !== 'mysql') {
            throw new RuntimeException('Local and testing environments must use the isolated MySQL connection.');
        }

        $database = (string) config('database.connections.mysql.database');

        if (app()->environment('local') && $database !== 'agent_millow_dev') {
            throw new RuntimeException('Refusing to boot: local must use the agent_millow_dev database.');
        }

        if (app()->environment('testing') && preg_match('/^agent_millow_test(_\d+)?$/', $database) !== 1) {
            throw new RuntimeException('Refusing to boot: testing must use a dedicated agent_millow_test database.');
        }
    }
}
