<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\RedactSensitiveData;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RedactSensitiveDataTest extends TestCase
{
    public function test_it_redacts_sensitive_context_keys_recursively(): void
    {
        $processor = new RedactSensitiveData;

        $record = new LogRecord(
            new DateTimeImmutable,
            'testing',
            Level::Info,
            'Request handled.',
            [
                'password' => 'super-secret',
                'nested' => [
                    'token' => 'abc123',
                    'safe' => 'visible',
                    'authorization' => 'Bearer abc',
                    'openai_key' => 'provider-key',
                ],
            ],
            ['api-key' => 'key-value', 'x-api-key' => 'header-value', 'monkey' => 'not-secret'],
        );

        $processed = $processor($record);

        $this->assertSame('[redacted]', $processed->context['password']);
        $this->assertSame('[redacted]', $processed->context['nested']['token']);
        $this->assertSame('[redacted]', $processed->context['nested']['authorization']);
        $this->assertSame('[redacted]', $processed->context['nested']['openai_key']);
        $this->assertSame('visible', $processed->context['nested']['safe']);
        $this->assertSame('[redacted]', $processed->extra['api-key']);
        $this->assertSame('[redacted]', $processed->extra['x-api-key']);
        $this->assertSame('not-secret', $processed->extra['monkey']);
    }

    public function test_it_redacts_provider_exception_context(): void
    {
        $processor = new RedactSensitiveData;

        $record = new LogRecord(
            new DateTimeImmutable,
            'testing',
            Level::Warning,
            'Provider reply failed.',
            [
                'exception' => 'App\Providers\Llm\Exceptions\ProviderException',
                'reason' => 'HTTP 401: {"api_key":"sk-abcdefgh12345678","message":"Unauthorized"}',
                'headers' => ['Authorization' => 'Bearer abc.def.ghi'],
            ],
            [],
        );

        $processed = $processor($record);

        $this->assertStringNotContainsString('sk-abcdefgh12345678', $processed->context['reason']);
        $this->assertStringNotContainsString('abc.def.ghi', $processed->context['headers']['Authorization']);
        $this->assertStringContainsString('[redacted]', $processed->context['reason']);
    }

    public function test_it_redacts_exception_objects_in_context(): void
    {
        $processor = new RedactSensitiveData;

        $exception = new RuntimeException('Provider rejected key sk-abcdefgh12345678');
        $record = new LogRecord(
            new DateTimeImmutable,
            'testing',
            Level::Error,
            'Unhandled exception.',
            ['exception' => $exception],
            [],
        );

        $processed = $processor($record);
        $context = $processed->context['exception'];

        $this->assertIsArray($context);
        $this->assertSame(RuntimeException::class, $context['class']);
        $this->assertStringNotContainsString('sk-abcdefgh12345678', $context['message']);
        $this->assertStringContainsString('[redacted]', $context['message']);
    }

    public function test_it_redacts_json_style_credentials_in_messages(): void
    {
        $processor = new RedactSensitiveData;

        $record = new LogRecord(
            new DateTimeImmutable,
            'testing',
            Level::Warning,
            'Provider said {"api_key":"plain-value","password":"hunter2"}',
            [],
            [],
        );

        $processed = $processor($record);

        $this->assertStringNotContainsString('plain-value', $processed->message);
        $this->assertStringNotContainsString('hunter2', $processed->message);
    }

    public function test_it_redacts_tokens_embedded_in_messages(): void
    {
        $processor = new RedactSensitiveData;

        $record = new LogRecord(
            new DateTimeImmutable,
            'testing',
            Level::Info,
            'Calling with Bearer abc.def.ghi and key sk-1234567890abcdef',
            [],
            [],
        );

        $processed = $processor($record);

        $this->assertStringNotContainsString('abc.def.ghi', $processed->message);
        $this->assertStringNotContainsString('sk-1234567890abcdef', $processed->message);
        $this->assertStringContainsString('[redacted]', $processed->message);
    }
}
