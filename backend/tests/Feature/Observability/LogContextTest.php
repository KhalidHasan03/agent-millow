<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Logging\RequestContext;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

final class LogContextTest extends TestCase
{
    public function test_request_context_adds_only_safe_identifiers(): void
    {
        $user = new User;
        $user->forceFill(['id' => 42]);

        $request = Request::create('/api/v1/tools?token=query-secret', 'POST', ['password' => 'body-secret']);
        $request->attributes->set('request_id', 'req-safe-123');

        $this->app->instance('request', $request);
        $request->setUserResolver(fn (): User => $user);

        $record = new LogRecord(
            new DateTimeImmutable,
            'testing',
            Level::Info,
            'Event happened.',
            ['password' => 'context-secret'],
            [],
        );

        $processed = (new RequestContext)($record);

        $this->assertSame('req-safe-123', $processed->context['request_id']);
        $this->assertSame('POST', $processed->context['method']);
        $this->assertSame('/api/v1/tools', $processed->context['path']);
        $this->assertSame(42, $processed->context['user_id']);
        $this->assertStringNotContainsString('query-secret', json_encode($processed->context, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('body-secret', json_encode($processed->context, JSON_THROW_ON_ERROR));
    }

    public function test_request_context_is_skipped_without_a_request_id(): void
    {
        $request = Request::create('/api/v1/tools', 'GET');
        $this->app->instance('request', $request);

        $record = new LogRecord(
            new DateTimeImmutable,
            'testing',
            Level::Info,
            'Queue event.',
            ['job' => 'example'],
            [],
        );

        $processed = (new RequestContext)($record);

        $this->assertSame(['job' => 'example'], $processed->context);
    }

    public function test_the_single_channel_redacts_records(): void
    {
        $path = storage_path('logs/test-single-'.uniqid().'.log');

        config([
            'logging.default' => 'single',
            'logging.channels.single.path' => $path,
        ]);

        Log::forgetChannel('single');
        Log::channel('single')->warning('single.test', ['api_key' => 'plain-secret']);

        $contents = (string) file_get_contents($path);

        @unlink($path);

        $this->assertStringNotContainsString('plain-secret', $contents);
        $this->assertStringContainsString('[redacted]', $contents);
    }

    public function test_the_json_channel_writes_structured_and_redacted_records(): void
    {
        $path = storage_path('logs/test-json-'.uniqid().'.log');

        config([
            'logging.channels.json.driver' => 'single',
            'logging.channels.json.path' => $path,
        ]);

        $request = Request::create('/api/v1/me', 'GET');
        $request->attributes->set('request_id', 'req-json-1');
        $this->app->instance('request', $request);

        Log::channel('json')->info('observability.test', [
            'password' => 'super-secret',
            'safe' => 'visible',
        ]);

        $this->assertFileExists($path);

        $decoded = json_decode(trim((string) file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);

        @unlink($path);

        $this->assertIsArray($decoded);
        $this->assertSame('observability.test', $decoded['message']);
        $this->assertSame('INFO', $decoded['level_name']);
        $this->assertSame('[redacted]', $decoded['context']['password']);
        $this->assertSame('visible', $decoded['context']['safe']);
        $this->assertSame('req-json-1', $decoded['context']['request_id']);
        $this->assertSame('GET', $decoded['context']['method']);
        $this->assertSame('/api/v1/me', $decoded['context']['path']);
    }
}
