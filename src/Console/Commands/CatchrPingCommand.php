<?php

namespace CceoDeveloper\Catchr\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Throwable;

class CatchrPingCommand extends Command
{
    protected $signature = 'catchr:ping
        {--timeout= : Override timeout in seconds (default: config catchr.timeout)}
        {--no-abort : No abort even if Catchr is disabled or env not allowed}';

    protected $description = 'Send a lightweight ping payload to all Catchr endpoints.';

    public function handle(): int
    {
        $enabled = (bool) Config::get('catchr.enabled', true);
        $endpoints = Config::get('catchr.endpoints', []);
        $envs = Config::get('catchr.environments', []);
        $appEnv = (string) Config::get('app.env', '');
        $noAbort = (bool) $this->option('no-abort');

        if (!is_array($endpoints)) $endpoints = [];
        $endpoints = array_values(array_filter(array_map('trim', $endpoints)));

        if (!is_array($envs)) $envs = [];
        $envs = array_values(array_filter(array_map('trim', $envs)));
        $envAllowed = empty($envs) ? true : in_array($appEnv, $envs, true);

        $timeoutConfig = Config::get('catchr.timeout', 5);
        $timeout = is_numeric($timeoutConfig) && (int) $timeoutConfig >= 1
            ? (int) $timeoutConfig
            : 5;

        $timeoutOption = $this->option('timeout');
        if ($timeoutOption !== null && $timeoutOption !== '') {
            if (is_numeric($timeoutOption) && (int) $timeoutOption >= 1) {
                $timeout = (int) $timeoutOption;
            } else {
                $this->warn('Invalid --timeout option; using default timeout.');
            }
        }
        $this->info('Catchr ping');
        $this->line(str_repeat('-', 24));
        $this->line('Enabled: ' . ($enabled ? '<info>true</info>' : '<comment>false</comment>'));
        $this->line('Env: <comment>' . ($appEnv ?: '(empty)') . '</comment>');
        $this->line('Env allowed: ' . ($envAllowed ? '<info>true</info>' : '<comment>false</comment>'));
        $this->line('Timeout: <comment>' . $timeout . "s</comment>");
        $this->line('Endpoints: <comment>' . count($endpoints) . '</comment>');
        $this->line('');

        if (count($endpoints) === 0) {
            $this->error('No endpoints configured (CATCHR_ENDPOINTS is empty).');
            return self::FAILURE;
        }

        if ((!$enabled || !$envAllowed) && !$noAbort) {
            $this->error('Catchr is not ready to send (disabled or env not allowed). Use --no-abort to force ping.');
            return self::FAILURE;
        }

        $payload = [
            'type' => 'catchr.ping',
            'app' => Config::get('app.name'),
            'env' => $appEnv,
            'timestamp' => Carbon::now()->toIso8601String(),
            'meta' => [
                'php' => PHP_VERSION,
            ],
        ];

        $ok = 0;
        $fail = 0;

        foreach ($endpoints as $endpoint) {
            $start = microtime(true);

            try {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->asJson()
                    ->post($endpoint, $payload);

                $ms = (int) round((microtime(true) - $start) * 1000);

                if ($response->successful()) {
                    $ok++;
                    $this->line("<info>OK</info>  {$endpoint}  <comment>{$response->status()}</comment>  <comment>{$ms}ms</comment>");
                } else {
                    $fail++;
                    $this->line("<comment>NON-2XX</comment>  {$endpoint}  <comment>{$response->status()}</comment>  <comment>{$ms}ms</comment>");
                }
            } catch (Throwable $e) {
                $fail++;
                $ms = (int) round((microtime(true) - $start) * 1000);
                $this->line("<error>FAIL</error>  {$endpoint}  <comment>{$ms}ms</comment>  <comment>" . get_class($e) . '</comment>: ' . $e->getMessage());
            }
        }

        $this->line('');
        $this->info("Summary: OK={$ok}, FAIL={$fail}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
