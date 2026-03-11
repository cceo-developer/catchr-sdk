<?php

namespace CceoDeveloper\Catchr\Support;

use Illuminate\Support\Facades\Config;

final class CatchrConfig
{
    /**
     * Strings allowed for function: 'exception' | 'queue' | 'log'
     *
     * @param string $scope
     * @return EndpointContext
     */
    public static function for(string $scope = 'exception'): EndpointContext
    {
        $appEnv = (string) Config::get('app.env');
        $enabled = (bool) Config::get("catchr.enabled", true);
        $envs = Config::get("catchr.environments", []);
        $publicKey = trim((string) Config::get('catchr.public_key', ''));
        $privateKey = trim((string) Config::get('catchr.private_key', ''));
        $subEnabled = (bool) Config::get("catchr.{$scope}.enabled", true);
        $endpoints = Config::get("catchr.{$scope}.endpoints", []);
        $timeout = (int) Config::get("catchr.{$scope}.timeout", 5);

        if (!is_array($envs)) $envs = [];
        $envs = array_values(array_filter(array_map('trim', $envs)));

        $envAllowed = empty($envs) || in_array($appEnv, $envs, true);

        if (!is_array($endpoints)) $endpoints = [];
        $endpoints = array_values(array_filter(array_map('trim', $endpoints)));

        return new EndpointContext(
            enabled: $enabled,
            subEnabled: $subEnabled,
            envAllowed: $envAllowed,
            endpoints: $endpoints,
            timeout: $timeout,
            publicKey: $publicKey,
            privateKey: $privateKey,
            appEnv: $appEnv,
        );
    }
}