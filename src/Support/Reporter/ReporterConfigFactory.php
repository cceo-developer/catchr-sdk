<?php

namespace CceoDeveloper\Catchr\Support\Reporter;

use Illuminate\Support\Facades\Config;

final class ReporterConfigFactory
{
    public function make(string $channel): ReporterConfig
    {
        $channelConfig = (array) Config::get("catchr.{$channel}", []);

        $endpoints = array_values(array_filter(
            array_map(
                static fn ($value) => trim((string) $value),
                (array) ($channelConfig['endpoints'] ?? [])
            ),
            static fn (string $endpoint) => $endpoint !== ''
        ));

        return new ReporterConfig(
            channel: $channel,
            appEnv: (string) Config::get('app.env', 'production'),
            globalEnabled: (bool) Config::get('catchr.enabled', true),
            envs: (array) Config::get('catchr.environments', []),
            publicKey: trim((string) Config::get('catchr.public_key', '')),
            privateKey: trim((string) Config::get('catchr.private_key', '')),
            channelEnabled: (bool) ($channelConfig['enabled'] ?? true),
            endpoints: $endpoints,
            timeout: (int) ($channelConfig['timeout'] ?? 5),
        );
    }
}