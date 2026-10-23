<?php

declare(strict_types=1);

namespace App\Evaluation\Scenarios;

use App\Evaluation\Contracts\EvaluationScenario;
use App\Evaluation\EvaluationCheck;
use App\Evaluation\EvaluationContext;
use App\Logging\RedactSensitiveData;
use App\Models\AuditEvent;
use App\Services\Audit\AuditLogger;
use App\Services\Tools\Redaction\ToolRedactor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;

final class SecretRedactionScenario implements EvaluationScenario
{
    private const SECRET = 'sk-eval-abcdefgh12345678';

    public function key(): string
    {
        return 'secret-redaction';
    }

    public function label(): string
    {
        return 'Secret redaction';
    }

    /**
     * @return list<EvaluationCheck>
     */
    public function run(EvaluationContext $context): array
    {
        $processor = new RedactSensitiveData;
        $record = new LogRecord(
            new DateTimeImmutable,
            'evaluation',
            Level::Error,
            'Provider failure with Bearer '.self::SECRET,
            [
                'api_key' => self::SECRET,
                'nested' => ['authorization' => 'Bearer '.self::SECRET],
                'exception' => new RuntimeException('Rejected key '.self::SECRET),
                'safe' => 'visible',
            ],
            ['token' => self::SECRET],
        );

        $processed = $processor($record);
        $serializedLog = json_encode([
            'message' => $processed->message,
            'context' => $processed->context,
            'extra' => $processed->extra,
        ], JSON_THROW_ON_ERROR);

        $redactor = app(ToolRedactor::class);
        $redactedTool = $redactor->redact([
            'api_key' => self::SECRET,
            'nested' => ['authorization' => 'Bearer '.self::SECRET],
            'safe' => 'visible',
        ]);

        app(AuditLogger::class)->record('evaluation.secret_redaction', $context->owner, null, [
            'password' => self::SECRET,
            'api_key' => self::SECRET,
            'safe' => 'visible',
        ]);

        $audit = AuditEvent::query()
            ->where('event', 'evaluation.secret_redaction')
            ->where('user_id', $context->owner->id)
            ->firstOrFail();

        return [
            ! str_contains($serializedLog, self::SECRET) && str_contains($serializedLog, '[redacted]')
                ? EvaluationCheck::pass('log_redaction', 'Log messages, context, extras, and exception objects are redacted.')
                : EvaluationCheck::fail('log_redaction', 'A secret survived log redaction.'),
            ($redactedTool['api_key'] ?? null) === '[REDACTED]'
                && ($redactedTool['nested']['authorization'] ?? null) === '[REDACTED]'
                ? EvaluationCheck::pass('tool_redaction', 'Nested tool secrets are redacted without hiding safe fields.')
                : EvaluationCheck::fail('tool_redaction', 'Tool redaction did not behave as expected.'),
            ($redactedTool['safe'] ?? null) === 'visible'
                ? EvaluationCheck::pass('safe_fields_preserved', 'Non-sensitive fields remain visible.')
                : EvaluationCheck::fail('safe_fields_preserved', 'Safe fields were removed by redaction.'),
            is_array($audit->context)
                && ($audit->context['password'] ?? null) === '[redacted]'
                && ($audit->context['api_key'] ?? null) === '[redacted]'
                && ($audit->context['safe'] ?? null) === 'visible'
                ? EvaluationCheck::pass('audit_redaction', 'Audit context redacts blocked keys and preserves safe fields.')
                : EvaluationCheck::fail('audit_redaction', 'Audit context redaction is incomplete.'),
            ! str_contains(json_encode($audit->toArray(), JSON_THROW_ON_ERROR), self::SECRET)
                ? EvaluationCheck::pass('audit_no_secret', 'No secret is stored in the audit record.')
                : EvaluationCheck::fail('audit_no_secret', 'A secret was stored in the audit record.'),
        ];
    }
}
