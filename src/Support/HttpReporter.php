<?php

namespace CceoDeveloper\Catchr\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpReporter
{
    public function report(Throwable $e): void
    {
        $endpoint = Config::get('catchr.endpoint');

        if (!Config::get('catchr.enabled', true)) {
            return;
        }

        if (!$endpoint) {
            return;
        }

        $envs = Config::get('catchr.environments', []);
        $appEnv = Config::get('app.env');

        if (!empty($envs) && $appEnv && !in_array($appEnv, $envs, true)) {
            return;
        }

        $payload = [
            'app' => Config::get('app.name'),
            'env' => $appEnv,
            'timestamp' => Carbon::now()->toDateTimeString(),
            'exception' => [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ],
        ];

        //$payload['exception']['trace'] = collect($e->getTrace())->take(20)->all();

        Http::timeout((int) Config::get('catchr.timeout', 5))
            ->acceptJson()
            ->asJson()
            ->post($endpoint, $payload);
    }
}
