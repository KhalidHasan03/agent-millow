<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * JSON formatter that always applies request context and redaction.
 */
final class SafeJsonFormatter extends JsonFormatter
{
    private readonly RequestContext $requestContext;

    private readonly RedactSensitiveData $redactor;

    public function __construct(
        int $batchMode = self::BATCH_MODE_JSON,
        bool $appendNewline = true,
        bool $ignoreEmptyContextAndExtra = false,
        bool $includeStacktraces = false,
    ) {
        parent::__construct($batchMode, $appendNewline, $ignoreEmptyContextAndExtra, $includeStacktraces);

        $this->requestContext = new RequestContext;
        $this->redactor = new RedactSensitiveData;
    }

    public function format(LogRecord $record): string
    {
        return parent::format($this->process($record));
    }

    /**
     * @param  array<LogRecord>  $records
     */
    public function formatBatch(array $records): string
    {
        return parent::formatBatch(array_map(fn (LogRecord $record): LogRecord => $this->process($record), $records));
    }

    private function process(LogRecord $record): LogRecord
    {
        return ($this->redactor)(($this->requestContext)($record));
    }
}
