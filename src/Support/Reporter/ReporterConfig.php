<?php

namespace CceoDeveloper\Catchr\Support\Reporter;

final readonly class ReporterConfig
{
    public function __construct(
        public string $channel,
        public string $appEnv,
        public bool   $globalEnabled,
        public array  $envs,
        public string $publicKey,
        public string $privateKey,
        public bool   $channelEnabled,
        public array  $endpoints,
        public int    $timeout,
    ) {}

    public function isEnvAllowed(): bool
    {
        return empty($this->envs) || in_array($this->appEnv, $this->envs, true);
    }

    public function hasCredentials(): bool
    {
        return $this->publicKey !== '' && $this->privateKey !== '';
    }

    public function hasEndpoints(): bool
    {
        return !empty($this->endpoints);
    }

    public function isValidTimeout(): bool
    {
        return $this->timeout > 0;
    }

    public function isAllowed(): bool
    {
        return $this->globalEnabled
            && $this->channelEnabled
            && $this->isEnvAllowed()
            && $this->hasCredentials()
            && $this->hasEndpoints()
            && $this->isValidTimeout();
    }
}