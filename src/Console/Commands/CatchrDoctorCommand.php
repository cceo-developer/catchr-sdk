<?php

namespace CceoDeveloper\Catchr\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;


class CatchrDoctorCommand extends Command
{
    protected $signature = 'catchr:doctor';
    protected $description = 'Diagnose Catchr configuration (required, dedupe, endpoints).';

    private int $fails = 0;
    private int $warns = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->components->info('Catchr Doctor');

        $this->checkRequiredConfig();
        $this->checkDedupeConfig();
        $this->checkEndpoints();
        $this->checkConnectivity();

        $this->newLine();

        // Summary
        if ($this->fails > 0) {
            $this->components->error("Doctor result: {$this->fails} critical issue(s), {$this->warns} warning(s).");
            return self::FAILURE;
        }

        if ($this->warns > 0) {
            $this->components->warn("Doctor result: OK with {$this->warns} warning(s).");
            return self::SUCCESS;
        }

        $this->components->info('Doctor result: ✅ All good.');
        return self::SUCCESS;
    }

    private function checkRequiredConfig(): void
    {
        $enabled   = (bool) Config::get('catchr.enabled', true);
        $public    = (string) (Config::get('catchr.public_key') ?? '');
        $private   = (string) (Config::get('catchr.private_key') ?? '');
        $timeout   = (int) Config::get('catchr.timeout', 5);
        $envs      = (array) Config::get('catchr.environments', []);
        $endpoints = (array) Config::get('catchr.endpoints', []);

        $this->section('Required config');

        $rows = [];

        // enabled
        if (!$enabled) {
            $rows[] = $this->rowFail('Enabled', 'false', 'Set CATCHR_ENABLED=true');
        } else {
            $rows[] = $this->rowOk('Enabled', 'true');
        }

        // keys
        if ($public === '' || $private === '') {
            $rows[] = $this->rowFail('Server keys', 'missing', 'Set CATCHR_PUBLIC_KEY & CATCHR_PRIVATE_KEY');
        } else {
            $rows[] = $this->rowOk('Public key', $this->mask($public));
            $rows[] = $this->rowOk('Private key', $this->mask($private));
        }

        // endpoints basic
        if (count($endpoints) === 0) {
            $rows[] = $this->rowFail('Endpoints', '(none)', 'Set CATCHR_ENDPOINTS="https://.../api,https://.../api"');
        } else {
            $rows[] = $this->rowOk('Endpoints count', (string) count($endpoints));
        }

        // timeout
        if ($timeout <= 0) {
            $rows[] = $this->rowWarn('Timeout', "{$timeout}s", 'Should be a positive number (recommended 3–10s)');
        } else {
            $rows[] = $this->rowOk('Timeout', "{$timeout}s");
        }

        // envs
        if (empty($envs)) {
            $rows[] = $this->rowWarn('Environments', '(empty)', 'Set CATCHR_ENVS="local,staging,production"');
        } else {
            $rows[] = $this->rowOk('Environments', implode(', ', $envs));
        }

        $this->table(['Check', 'Status', 'Value', 'Hint'], $rows);
    }

    private function checkDedupeConfig(): void
    {
        $enabled    = (bool) Config::get('catchr.dedupe.enabled', true);
        $ttl        = (int) Config::get('catchr.dedupe.ttl_seconds', 300);
        $store      = Config::get('catchr.dedupe.cache_store', null);
        $prefix     = (string) Config::get('catchr.dedupe.prefix', 'catchr:seen:');
        $normalize  = (bool) Config::get('catchr.dedupe.normalize_message', true);

        $this->section('Dedupe config');

        $rows = [];

        if (!$enabled) {
            $rows[] = $this->rowWarn('Dedupe enabled', 'false', 'Same error may be reported repeatedly');
        } else {
            $rows[] = $this->rowOk('Dedupe enabled', 'true');
        }

        // TTL recommendations
        if ($ttl <= 30) {
            $rows[] = $this->rowWarn('TTL window', "{$ttl}s", 'Very short. Recommended 60–300s');
        } elseif ($ttl > 86400) {
            $rows[] = $this->rowWarn('TTL window', "{$ttl}s", 'Very long. Consider <= 86400s (1 day)');
        } else {
            $rows[] = $this->rowOk('TTL window', "{$ttl}s");
        }

        $rows[] = $this->rowOk('Cache store', $store ? (string) $store : '(default)');
        $rows[] = $this->rowOk('Prefix', $prefix !== '' ? $prefix : '(empty)');

        if (!$normalize) {
            $rows[] = $this->rowWarn('Normalize message', 'false', 'Deduping will be less effective');
        } else {
            $rows[] = $this->rowOk('Normalize message', 'true');
        }

        $this->table(['Check', 'Status', 'Value', 'Hint'], $rows);
    }

    private function checkEndpoints(): void
    {
        $endpoints = (array) Config::get('catchr.endpoints', []);
        $public    = (string) (Config::get('catchr.public_key') ?? '');
        $private   = (string) (Config::get('catchr.private_key') ?? '');

        $this->section('Endpoints');

        if (count($endpoints) === 0) {
            // already failed in required, but keep section consistent
            $this->components->error('No endpoints configured.');
            return;
        }

        // Validate format + show list
        $rows = [];
        foreach ($endpoints as $i => $url) {
            $idx = (string) ($i + 1);

            if (!is_string($url) || trim($url) === '') {
                $rows[] = $this->rowFail("[$idx] URL", '(empty)', 'Remove empty values from CATCHR_ENDPOINTS');
                continue;
            }

            $trimmed = trim($url);
            if (!Str::startsWith($trimmed, ['http://', 'https://'])) {
                $rows[] = $this->rowWarn("[$idx] URL", $trimmed, 'URL should start with https://');
                continue;
            }

            $rows[] = $this->rowOk("[$idx] URL", $trimmed);
        }

        $this->table(['Endpoint', 'Status', 'Value', 'Hint'], $rows);
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->components->info($title);
    }

    private function rowOk(string $check, string $value, string $hint = ''): array
    {
        return [$check, '<info>OK</info>', $value, $hint];
    }

    private function rowWarn(string $check, string $value, string $hint = ''): array
    {
        $this->warns++;
        return [$check, '<comment>WARN</comment>', $value, $hint];
    }

    private function rowFail(string $check, string $value, string $hint = ''): array
    {
        $this->fails++;
        return [$check, '<error>FAIL</error>', $value, $hint];
    }

    private function mask(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '(empty)';

        // show only last 4 chars
        $last4 = substr($value, -4);
        return str_repeat('•', max(0, strlen($value) - 4)) . $last4;
    }

    private function checkConnectivity(): void
    {
        $enabled   = (bool) Config::get('catchr.enabled');
        $public    = (string) (Config::get('catchr.public_key') ?? '');
        $private   = (string) (Config::get('catchr.private_key') ?? '');
        $timeout   = (int) Config::get('catchr.timeout', 5);
        $endpoints = (array) Config::get('catchr.endpoints', []);
        $appEnv    = (string) Config::get('app.env', '');

        $this->section('Connectivity (ping)');

        // Preconditions
        if (!$enabled) {
            $this->table(['Check', 'Status', 'Value', 'Hint'], [
                $this->rowFail('Catchr enabled', 'false', 'Enable CATCHR_ENABLED=true to test connectivity'),
            ]);
            return;
        }

        if (count($endpoints) === 0) {
            $this->table(['Check', 'Status', 'Value', 'Hint'], [
                $this->rowFail('Endpoints', '(none)', 'Set CATCHR_ENDPOINTS="https://.../api,https://.../api"'),
            ]);
            return;
        }

        if ($public === '' || $private === '') {
            $this->table(['Check', 'Status', 'Value', 'Hint'], [
                $this->rowFail('Server keys', 'missing', 'Set CATCHR_PUBLIC_KEY & CATCHR_PRIVATE_KEY (required for ping auth)'),
            ]);
            return;
        }

        // Payload
        $payload = [
            'type' => 'catchr.ping',
            'app' => Config::get('app.name'),
            'env' => $appEnv,
            'timestamp' => Carbon::now()->toIso8601String(),
            'meta' => [
                'php' => PHP_VERSION,
            ],
        ];

        $http = Http::timeout(max(1, $timeout))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($public, $private);

        $rows = [];
        $ok = 0;
        $warn = 0;
        $fail = 0;

        $times = [];

        foreach ($endpoints as $i => $endpoint) {
            $endpoint = trim((string) $endpoint);
            $label = '[' . ($i + 1) . ']';

            // Basic URL check (format)
            if ($endpoint === '') {
                $fail++;
                $rows[] = [$label, '<error>FAIL</error>', '(empty)', '-', 'Remove empty endpoint from CATCHR_ENDPOINTS'];
                continue;
            }

            if (!Str::startsWith($endpoint, ['http://', 'https://'])) {
                $warn++;
                $rows[] = [$label, '<comment>WARN</comment>', $endpoint, '-', 'URL should start with https://'];
                continue;
            }

            $start = microtime(true);

            try {
                $response = $http->post($endpoint, $payload);
                $ms = (int) round((microtime(true) - $start) * 1000);
                $times[] = $ms;

                $status = $response->status();

                // Severity rules
                if ($response->successful()) {
                    $ok++;
                    $rows[] = [$label, '<info>OK</info>', $endpoint, (string) $status, "{$ms}ms", ''];
                    continue;
                }

                // Critical: auth / forbidden (most common setup issues)
                if (in_array($status, [401, 403], true)) {
                    $fail++;
                    $rows[] = [$label, '<error>FAIL</error>', $endpoint, (string) $status, "{$ms}ms", 'Auth failed: check keys and server credentials'];
                    continue;
                }

                // Critical: not found / method not allowed (endpoint path wrong)
                if (in_array($status, [404, 405], true)) {
                    $fail++;
                    $rows[] = [$label, '<error>FAIL</error>', $endpoint, (string) $status, "{$ms}ms", 'Endpoint URL/path is likely wrong'];
                    continue;
                }

                // Moderate: rate limit
                if ($status === 429) {
                    $warn++;
                    $rows[] = [$label, '<comment>WARN</comment>', $endpoint, (string) $status, "{$ms}ms", 'Rate limited: try later or increase server capacity'];
                    continue;
                }

                // Moderate: server errors
                if ($status >= 500) {
                    $warn++;
                    $rows[] = [$label, '<comment>WARN</comment>', $endpoint, (string) $status, "{$ms}ms", 'Server error: check Catchr server logs'];
                    continue;
                }

                // Other 4xx: treat as warn (depends on server validation)
                if ($status >= 400) {
                    $warn++;
                    $rows[] = [$label, '<comment>WARN</comment>', $endpoint, (string) $status, "{$ms}ms", 'Client error: check payload expectations on server'];
                    continue;
                }

                // Fallback
                $warn++;
                $rows[] = [$label, '<comment>WARN</comment>', $endpoint, (string) $status, "{$ms}ms", 'Unexpected response status'];
            } catch (Throwable $e) {
                $ms = (int) round((microtime(true) - $start) * 1000);
                $times[] = $ms;

                $fail++;
                $rows[] = [
                    $label,
                    '<error>FAIL</error>',
                    $endpoint,
                    get_class($e),
                    "{$ms}ms",
                    $this->shortExceptionHint($e),
                ];
            }
        }

        $this->table(['#', 'Status', 'Endpoint', 'Code/Class', 'Time', 'Hint'], $rows);

        // Summary + update global counters
        $this->fails += $fail;
        $this->warns += $warn;

        $avg = count($times) ? (int) round(array_sum($times) / count($times)) : 0;
        $min = count($times) ? min($times) : 0;
        $max = count($times) ? max($times) : 0;

        $this->newLine();
        $this->components->twoColumnDetail('Ping summary', "OK={$ok}  WARN={$warn}  FAIL={$fail}  |  avg={$avg}ms  min={$min}ms  max={$max}ms");
    }

    private function shortExceptionHint(Throwable $e): string
    {
        $msg = strtolower($e->getMessage());

        // Common Laravel HTTP client exceptions/messages
        if (str_contains($msg, 'could not resolve host') || str_contains($msg, 'could not resolve host')) {
            return 'DNS issue: check domain name and network';
        }

        if (str_contains($msg, 'timed out') || str_contains($msg, 'timeout')) {
            return 'Timeout: increase CATCHR_TIMEOUT or check server latency';
        }

        if (str_contains($msg, 'ssl') || str_contains($msg, 'certificate')) {
            return 'TLS/SSL issue: verify certificate chain and https configuration';
        }

        if (str_contains($msg, 'connection refused')) {
            return 'Connection refused: server down or port blocked';
        }

        return 'Network/HTTP error: check connectivity and endpoint availability';
    }
}
