<?php

namespace CceoDeveloper\Catchr\Support;

final readonly class EndpointContext
{
    public function __construct(
        public bool $enabled,
        public bool $subEnabled,
        public bool $envAllowed,
        public array $endpoints,
        public int $timeout,
        public string $publicKey,
        public string $privateKey,
        public string $appEnv,
    ) {}

    public function canSend(): bool
    {
        return $this->enabled
            && $this->subEnabled
            && $this->envAllowed
            && !empty($this->endpoints)
            && $this->publicKey !== ''
            && $this->privateKey !== '';
    }
}