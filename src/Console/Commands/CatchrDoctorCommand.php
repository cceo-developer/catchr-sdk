<?php

namespace CceoDeveloper\Catchr\Console\Commands;

use CceoDeveloper\Catchr\Support\Reporter\ReporterConfig;
use CceoDeveloper\Catchr\Support\Reporter\ReporterConfigFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class CatchrDoctorCommand extends Command
{
    protected $signature = 'catchr:doctor';
    protected $description = 'Diagnose Catchr configuration (required, dedupe, endpoints, queue).';

    private int $fails = 0;
    private int $warns = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->components->info('Catchr Doctor');

        $factory = new ReporterConfigFactory();

        $channels = [
            'exceptions' => $factory->make('exception'),
            'jobs' => $factory->make('queue'),
            'logs' => $factory->make('log'),
        ];

        $this->checkGlobalConfig($channels);
        $this->checkDedupeConfig();

        foreach ($channels as $name => $config) {
            $this->checkChannel($name, $config);
        }

        $this->checkQueueTable();

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

    /**
     * @param array<string, ReporterConfig> $channels
     */
    private function checkGlobalConfig(array $channels): void
    {
        $enabled = (bool) Config::get('catchr.enabled', true);
        $envs = (array) Config::get('catchr.environments', []);
        $appEnv = (string) Config::get('app.env', 'production');

        // We use any config to check shared keys (they are global in Factory)
        $anyConfig = reset($channels);

        $this->section('Global Configuration');

        $rows = [];

        // Enabled
        $rows[] = $enabled
            ? $this->rowOk('Enabled', 'true')
            : $this->rowFail('Enabled', 'false', 'Set CATCHR_ENABLED=true');

        // Environments
        if (empty($envs)) {
            $rows[] = $this->rowWarn('Allowed Environments', '(any)', 'Set CATCHR_ENVS to limit reporting');
        } else {
            $status = in_array($appEnv, $envs, true) ? 'OK' : 'WARN';
            $val = implode(', ', $envs) . " (Current: {$appEnv})";
            if ($status === 'OK') {
                $rows[] = $this->rowOk('Allowed Environments', $val);
            } else {
                $rows[] = $this->rowWarn('Allowed Environments', $val, "Current env '{$appEnv}' is NOT in the list");
            }
        }

        // Keys
        if ($anyConfig->hasCredentials()) {
            $rows[] = $this->rowOk('Public Key', $this->mask($anyConfig->publicKey));
            $rows[] = $this->rowOk('Private Key', $this->mask($anyConfig->privateKey));
        } else {
            $rows[] = $this->rowFail('Server Keys', 'missing', 'Set CATCHR_PUBLIC_KEY & CATCHR_PRIVATE_KEY');
        }

        $this->table(['Check', 'Status', 'Value', 'Hint'], $rows);
    }

    private function checkDedupeConfig(): void
    {
        $this->section('Deduplication (Exceptions Only)');

        $enabled = (bool) Config::get('catchr.exception.dedupe.enabled', true);
        $ttl = (int) Config::get('catchr.exception.dedupe.ttl_seconds', 300);
        $store = Config::get('catchr.exception.dedupe.cache_store');
        $prefix = (string) Config::get('catchr.exception.dedupe.prefix', 'catchr:seen:');
        $normalize = (bool) Config::get('catchr.exception.dedupe.normalize_message', true);

        $rows = [];

        if (!$enabled) {
            $rows[] = $this->rowWarn('Dedupe enabled', 'false', 'Repeated exceptions will all be reported');
        } else {
            $rows[] = $this->rowOk('Dedupe enabled', 'true');
        }

        if ($ttl <= 30) {
            $rows[] = $this->rowWarn('TTL window', "{$ttl}s", 'Very short. Recommended 60–300s');
        } else {
            $rows[] = $this->rowOk('TTL window', "{$ttl}s");
        }

        $rows[] = $this->rowOk('Cache store', $store ?: '(default)');
        $rows[] = $this->rowOk('Prefix', $prefix);
        $rows[] = $normalize ? $this->rowOk('Normalize message', 'true') : $this->rowWarn('Normalize message', 'false');

        $this->table(['Check', 'Status', 'Value', 'Hint'], $rows);
    }

    private function checkChannel(string $name, ReporterConfig $config): void
    {
        $this->section("Channel: " . ucfirst($name));

        $rows = [];

        // Channel Enabled
        if (!$config->channelEnabled) {
            $rows[] = $this->rowWarn('Channel enabled', 'false', "Reporting for {$name} is disabled");
        } else {
            $rows[] = $this->rowOk('Channel enabled', 'true');
        }

        // Endpoints
        if (!$config->hasEndpoints()) {
            $rows[] = $this->rowFail('Endpoints', '(none)', "No endpoints for {$name}");
        } else {
            $rows[] = $this->rowOk('Endpoints count', (string) count($config->endpoints));
        }

        // Timeout
        if (!$config->isValidTimeout()) {
            $rows[] = $this->rowFail('Timeout', "{$config->timeout}s", 'Must be > 0');
        } else {
            $rows[] = $this->rowOk('Timeout', "{$config->timeout}s");
        }

        $this->table(['Check', 'Status', 'Value', 'Hint'], $rows);

        if ($config->hasEndpoints() && $config->globalEnabled && $config->channelEnabled) {
            $this->pingEndpoints($config);
        }
    }

    private function pingEndpoints(ReporterConfig $config): void
    {
        $this->line("  <info>Pinging endpoints...</info>");

        $payload = [
            'type' => 'catchr.ping',
            'channel' => $config->channel,
            'app' => Config::get('app.name'),
            'env' => $config->appEnv,
            'timestamp' => Carbon::now()->toIso8601String(),
        ];

        $http = Http::timeout(max(1, $config->timeout))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($config->publicKey, $config->privateKey);

        $rows = [];

        foreach ($config->endpoints as $i => $endpoint) {
            $label = '[' . ($i + 1) . ']';
            $start = microtime(true);

            try {
                $response = $http->post($endpoint, $payload);
                $ms = (int) round((microtime(true) - $start) * 1000);
                $status = $response->status();

                if ($response->successful()) {
                    $rows[] = [$label, '<info>OK</info>', $endpoint, (string) $status, "{$ms}ms", ''];
                } else {
                    $this->fails++;
                    $rows[] = [$label, '<error>FAIL</error>', $endpoint, (string) $status, "{$ms}ms", 'Check credentials or URL'];
                }
            } catch (Throwable $e) {
                $this->fails++;
                $ms = (int) round((microtime(true) - $start) * 1000);
                $rows[] = [$label, '<error>FAIL</error>', $endpoint, 'Error', "{$ms}ms", Str::limit($e->getMessage(), 40)];
            }
        }

        $this->table(['#', 'Status', 'Endpoint', 'Code', 'Time', 'Hint'], $rows);
    }

    private function checkQueueTable(): void
    {
        $this->section('Database');
        if (!Schema::hasTable('catchr_job_runs')) {
            $this->table(['Check', 'Status', 'Value', 'Hint'], [
                $this->rowFail('Table catchr_job_runs', 'missing', 'Run: php artisan migrate')
            ]);
        } else {
            $this->table(['Check', 'Status', 'Value', 'Hint'], [
                $this->rowOk('Table catchr_job_runs', 'exists')
            ]);
        }
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
        return str_repeat('•', max(0, strlen($value) - 4)) . substr($value, -4);
    }
}
