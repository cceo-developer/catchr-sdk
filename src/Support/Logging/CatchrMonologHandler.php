<?php

namespace CceoDeveloper\Catchr\Support\Logging;

use CceoDeveloper\Catchr\Support\CatchrConfig;
use CceoDeveloper\Catchr\Support\PayloadBuilder;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
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
            $ctx = CatchrConfig::for('log');
            if (!$ctx->canSend()) {
                return;
            }

            $payload = $this->builder->buildLogEvent(
                record: $record
            );

            $http = Http::timeout($ctx->timeout)->acceptJson()->asJson()->withBasicAuth($ctx->publicKey, $ctx->privateKey);

            foreach ($ctx->endpoints as $endpoint) {
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