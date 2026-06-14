<?php

namespace App\Logger;

use App\EventSubscriber\CorrelationIdSubscriber;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;

#[AsMonologProcessor]
final class CorrelationIdProcessor
{
    public function __construct(
        private readonly CorrelationIdSubscriber $correlationIdSubscriber,
        private readonly string $appEnv = 'prod',
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(extra: array_merge($record->extra, [
            'correlation_id' => $this->correlationIdSubscriber->getCorrelationId() ?: null,
            'app'            => 'medical-scheduling-api',
            'env'            => $this->appEnv,
        ]));
    }
}
