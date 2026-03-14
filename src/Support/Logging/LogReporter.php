<?php

namespace CceoDeveloper\Catchr\Support\Logging;

use CceoDeveloper\Catchr\Support\PayloadBuilder;
use CceoDeveloper\Catchr\Support\Reporter\Reporter;
use CceoDeveloper\Catchr\Support\Reporter\ReporterConfigFactory;
use Monolog\LogRecord;

class LogReporter extends Reporter
{
    public function __construct(private readonly PayloadBuilder $builder = new PayloadBuilder(), ?ReporterConfigFactory $configFactory = null)
    {
        $configFactory ??= new ReporterConfigFactory();
        parent::__construct($configFactory->make('log'));
    }

    public function report(LogRecord $record): void
    {
        if (!$this->allowed()) {
            return;
        }

        $payload = $this->builder->buildLogEvent(
            record: $record
        );

        $this->dispatch($payload);
    }
}