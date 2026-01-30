<?php

namespace CceoDeveloper\Catchr\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Throwable;

readonly class HttpReporter
{
    public function __construct(private PayloadBuilder $builder = new PayloadBuilder()) {}

    public function report(Throwable $e): void
    {
        $endpoint = Config::get('catchr.endpoint');

        if (!Config::get('catchr.enabled', true) || !$endpoint) {
            return;
        }

        $envs = Config::get('catchr.environments', []);
        $appEnv = Config::get('app.env');
        if (!empty($envs) && $appEnv && !in_array($appEnv, $envs, true)) {
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
                '[Catchr] Failed to report exception: ' .
                get_class($ignored) . ' - ' . $ignored->getMessage()
            );
        }

        $payload = $this->builder->build($e, $request);

        Http::timeout((int) Config::get('catchr.timeout', 5))
            ->acceptJson()
            ->asJson()
            ->post($endpoint, $payload);
    }
}
