<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Throwable;

final class RedactSensitiveData
{
    private const SENSITIVE_KEY_PATTERN = '/(?:password|passwd|token|secret|api[_-]?key|authorization|cookie|credential|private[_-]?key|(?:^|[_-])key$)/i';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redactString($record->message),
            context: $this->redactArray($record->context),
            extra: $this->redactArray($record->extra),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $data[$key] = '[redacted]';

                continue;
            }

            if ($value instanceof Throwable) {
                $data[$key] = [
                    'class' => $value::class,
                    'message' => $this->redactString($value->getMessage()),
                    'trace' => $this->redactString($value->getTraceAsString()),
                ];
            } elseif (is_array($value)) {
                $data[$key] = $this->redactArray($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->redactString($value);
            }
        }

        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1;
    }

    private function redactString(string $value): string
    {
        $patterns = [
            '/(Bearer\s+)[A-Za-z0-9._~+\/=-]+/i' => '$1[redacted]',
            '/\bsk-[A-Za-z0-9_-]{8,}\b/' => '[redacted]',
            '/\b(?:api[_-]?key|access[_-]?token|refresh[_-]?token|client[_-]?secret|password)\b\s*["\']?\s*[:=]\s*["\']?[^\s,;"\'}]+/i' => '[redacted]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        return $value;
    }
}
