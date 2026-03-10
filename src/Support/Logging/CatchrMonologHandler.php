<?php

namespace CceoDeveloper\Catchr\Support\Logging;

use CceoDeveloper\Catchr\Support\PayloadBuilder;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Throwable;

class CatchrMonologHandler extends AbstractProcessingHandler
{
    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true, private PayloadBuilder $builder = new PayloadBuilder())
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $envs = Config::get('catchr.environments', []);
            $appEnv = Config::get('app.env');
            $endpoints = Config::get('catchr.log.endpoints', []);
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

            if(!Config::get('catchr.log.enabled', true)) return;

            $payload = $this->builder->buildLogEvent(
                record: $record
            );

            $http = Http::timeout($timeout)->acceptJson()->asJson()->withBasicAuth($public, $private);

            foreach ($endpoints as $endpoint) {
                try {
                    $http->post($endpoint, $payload);
                } catch (Throwable $ignored) {
                    @error_log('[Catchr] Failed to post log event: ' . $endpoint . ' | ' . get_class($ignored) . ' - ' . $ignored->getMessage());
                }
            }
        } catch (Throwable $ignored) {
            @error_log('[Catchr] CatchrMonologHandler failed: ' . get_class($ignored) . ' - ' . $ignored->getMessage());
        }
    }
}