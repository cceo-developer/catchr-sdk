<?php

namespace CceoDeveloper\Catchr\Support\Jobs;

use CceoDeveloper\Catchr\Support\CatchrConfig;
use CceoDeveloper\Catchr\Support\PayloadBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

readonly class QueueReporter
{
    public function __construct(private PayloadBuilder $builder = new PayloadBuilder()) {}

    public function report(string $event, array $jobMeta, ?Throwable $exception = null): void
    {
        $ctx = CatchrConfig::for('queue');
        if (!$ctx->canSend()) {
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

        $http = Http::timeout($ctx->timeout)->acceptJson()->asJson()->withBasicAuth($ctx->publicKey, $ctx->privateKey);

        foreach ($ctx->endpoints as $endpoint) {
            try {
                $http->post($endpoint, $payload);
            } catch (Throwable $ignored) {
                @error_log('[Catchr] Failed to post queue event: ' . $endpoint . ' | ' . get_class($ignored) . ' - ' . $ignored->getMessage());
            }
        }
    }
}