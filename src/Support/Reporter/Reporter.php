<?php

namespace CceoDeveloper\Catchr\Support\Reporter;

use Illuminate\Support\Facades\Http;
use Throwable;

abstract class Reporter
{
    public function __construct(protected ReporterConfig $config) {}

    public function allowed(): bool
    {
        return $this->config->isAllowed();
    }

    protected function dispatch(array $payload): void
    {
        $http = Http::timeout($this->config->timeout)
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($this->config->publicKey, $this->config->privateKey);

        foreach ($this->config->endpoints as $endpoint) {
            try {
                $http->post($endpoint, $payload);
            } catch (Throwable $ignored) {
                @error_log(
                    '[Catchr] Failed to post ' . $this->config->channel . ' event: ' .
                    $endpoint . ' | ' .
                    get_class($ignored) . ' - ' .
                    $ignored->getMessage()
                );
            }
        }
    }
}