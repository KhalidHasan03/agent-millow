<?php

declare(strict_types=1);

namespace App\Services\Tools\Redaction;

final class ToolRedactor
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function redact(array $data): array
    {
        $sensitive = [
            'password',
            'passphrase',
            'secret',
            'token',
            'api_key',
            'apikey',
            'authorization',
            'auth',
            'key',
            'private_key',
            'access_token',
            'refresh_token',
            'cookie',
        ];

        return $this->redactRecursive($data, $sensitive);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $sensitive
     * @return array<string, mixed>
     */
    private function redactRecursive(array $data, array $sensitive): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);
            $isSensitive = false;
            foreach ($sensitive as $pattern) {
                if (str_contains($lowerKey, $pattern)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive && is_scalar($value)) {
                $result[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->redactRecursive($value, $sensitive);

                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
