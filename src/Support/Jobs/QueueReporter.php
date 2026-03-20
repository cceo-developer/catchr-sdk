<?php

namespace CceoDeveloper\Catchr\Support\Jobs;

use CceoDeveloper\Catchr\Support\PayloadBuilder;
use CceoDeveloper\Catchr\Support\Reporter\Reporter;
use CceoDeveloper\Catchr\Support\Reporter\ReporterConfigFactory;
use Illuminate\Http\Request;
use Throwable;

class QueueReporter extends Reporter
{
    public function __construct(private readonly PayloadBuilder $builder = new PayloadBuilder(), ?ReporterConfigFactory $configFactory = null)
    {
        $configFactory ??= new ReporterConfigFactory();
        parent::__construct($configFactory->make('queue'));
    }

    public function report(string $event, array $jobMeta, ?Throwable $exception = null): void
    {
        if (!$this->allowed()) {
            return;
        }

        $request = null;
        try {
            $candidate = app()->bound('request') ? app('request') : null;
            if ($candidate instanceof Request && $candidate->method()) {
                $request = $candidate;
            }
        } catch (Throwable $ignored) {
            @error_log(
                '[Catchr] Failed to get request method: ' .
                get_class($ignored) . ' - ' . $ignored->getMessage()
            );
        }

        $payload = $this->builder->buildQueueEvent(
            event: $event,
            jobMeta: $jobMeta,
            exception: $exception,
            request: $request,
        );

        $this->dispatch($payload);
    }
}