<?php

namespace CceoDeveloper\Catchr\Support\Exceptions;

use CceoDeveloper\Catchr\Support\CatchrConfig;
use CceoDeveloper\Catchr\Support\PayloadBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

readonly class HttpReporter
{
    public function __construct(private PayloadBuilder $builder = new PayloadBuilder()) {}

    public function report(Throwable $e): void
    {
        $ctx = CatchrConfig::for('exception');
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

        $payload = $this->builder->build($e, $request);

        $http = Http::timeout($ctx->timeout)->acceptJson()->asJson()->withBasicAuth($ctx->publicKey, $ctx->privateKey);

        foreach ($ctx->endpoints as $endpoint) {
            try {
                $http->post($endpoint, $payload);
            } catch (Throwable $ignored) {
                @error_log('[Catchr] Failed to post exception event: ' . $endpoint . ' | ' . get_class($ignored) . ' - ' . $ignored->getMessage());
            }
        }
    }
}
