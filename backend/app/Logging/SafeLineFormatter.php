<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;

/**
 * Line formatter that always applies request context and redaction.
 *
 * Laravel's built-in single/daily/slack/syslog/errorlog drivers do not apply
 * the `processors` configuration, so redaction is enforced at the formatter
 * layer instead.
 */
final class SafeLineFormatter extends LineFormatter
{
    private readonly RequestContext $requestContext;

    private readonly RedactSensitiveData $redactor;

    public function __construct(
        ?string $format = null,
        ?string $dateFormat = null,
        bool $allowInlineLineBreaks = true,
        bool $ignoreEmptyContextAndExtra = true,
        bool $includeStacktraces = true,
    ) {
        parent::__construct($format, $dateFormat, $allowInlineLineBreaks, $ignoreEmptyContextAndExtra, $includeStacktraces);

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
