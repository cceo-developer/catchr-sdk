<?php

namespace CceoDeveloper\Catchr\Support\Jobs;

use CceoDeveloper\Catchr\Support\PayloadBuilder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Throwable;

readonly class QueueReporter
{
    public function __construct(private PayloadBuilder $builder = new PayloadBuilder()) {}

    public function report(string $event, array $jobMeta, ?Throwable $exception = null): void
    {
        $envs = Config::get('catchr.environments', []);
        $appEnv = Config::get('app.env');
        $endpoints = Config::get('catchr.queue.endpoints', []);
        $timeout = (int) Config::get('catchr.timeout', 5);
        $public = trim((string) Config::get('catchr.public_key'));
        $private = trim((string) Config::get('catchr.private_key'));

        if ($public === '' || $private === '') {
            return;
        }

        if (!is_array($endpoints)) {
            $endpoints = [];
        }

        if (!Config::get('catchr.enabled', true) || empty($endpoints)) {
            return;
        }

        if (!empty($envs) && $appEnv && !in_array($appEnv, $envs, true)) {
            return;
        }

        if (!Config::get('catchr.queue.enabled', true)) return;

        $payload = $this->builder->buildQueueEvent(
            event: $event,
            jobMeta: $jobMeta,
            exception: $exception,
        );

        $http = Http::timeout($timeout)->acceptJson()->asJson()->withBasicAuth($public, $private);

        foreach ($endpoints as $endpoint) {
            try {
                $http->post($endpoint, $payload);
            } catch (Throwable $ignored) {
                @error_log('[Catchr] Failed to post queue event: ' . $endpoint . ' | ' . get_class($ignored) . ' - ' . $ignored->getMessage());
            }
        }
    }
}