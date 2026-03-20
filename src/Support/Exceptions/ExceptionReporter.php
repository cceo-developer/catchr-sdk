<?php

namespace CceoDeveloper\Catchr\Support\Exceptions;

use CceoDeveloper\Catchr\Support\PayloadBuilder;
use CceoDeveloper\Catchr\Support\Reporter\Reporter;
use CceoDeveloper\Catchr\Support\Reporter\ReporterConfigFactory;
use Illuminate\Http\Request;
use Throwable;

class ExceptionReporter extends Reporter
{
    public function __construct(private readonly PayloadBuilder $builder = new PayloadBuilder(), ?ReporterConfigFactory $configFactory = null)
    {
        $configFactory ??= new ReporterConfigFactory();
        parent::__construct($configFactory->make('exception'));
    }

    public function report(Throwable $e): void
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

        $payload = $this->builder->build($e, $request);

        $this->dispatch($payload);
    }
}