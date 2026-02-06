<?php

namespace CceoDeveloper\Catchr\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class CatchrTestCommand extends Command
{
    protected $signature = 'catchr:test
        {--type=exception : Type error: exception|sql|typeerror|zero}
        {--message= : Custom message (only for type=exception)}
        {--dedupe : Execute the same error twice to test dedupe}
        {--no-abort : Do not abort even if configuration is missing}';

    protected $description = 'Trigger a test error to verify Catchr reporting.';

    public function handle(): int
    {
        $type = (string) $this->option('type');
        $dedupe = (bool) $this->option('dedupe');
        $noAbort = (bool) $this->option('no-abort');
        $message = (string) ($this->option('message') ?? 'boom from catchr:test');

        $summary = $this->printCatchrSummary();

        $hasCriticalIssues = (
            $summary['enabled'] === false ||
            $summary['endpoints_count'] === 0 ||
            $summary['env_allowed'] === false
        );

        if ($hasCriticalIssues && !$noAbort) {
            $this->error('Catchr is not ready to report (critical config issue). Aborting test. Use --no-abort to force throwing anyway.');
            return self::FAILURE;
        }

        $this->info("Triggering test error (type={$type}, dedupe=" . ($dedupe ? 'true' : 'false') . ")");

        $trigger = function () use ($type, $message) {
            match ($type) {
                'sql' => DB::select('select * from this_table_does_not_exist'),
                'typeerror' => $this->triggerTypeError(),
                'zero' => $this->triggerDivisionByZero(),
                default => throw new \Exception($message),
            };
        };

        $trigger();

        if ($dedupe) {
            $this->warn('Triggering the same error again to test dedupe...');
            $trigger();
        }

        return self::SUCCESS;
    }

    private function printCatchrSummary(): array
    {
        $enabled = (bool) Config::get('catchr.enabled', true);

        $endpoints = Config::get('catchr.endpoints', []);
        if (!is_array($endpoints)) $endpoints = [];
        $endpoints = array_values(array_filter(array_map('trim', $endpoints)));

        $timeout = (int) Config::get('catchr.timeout', 5);

        $appEnv = (string) Config::get('app.env', '');
        $envs = Config::get('catchr.environments', []);
        if (!is_array($envs)) $envs = [];
        $envs = array_values(array_filter(array_map('trim', $envs)));

        $envAllowed = empty($envs) ? true : in_array($appEnv, $envs, true);

        $dedupeEnabled = (bool) Config::get('catchr.dedupe.enabled', true);
        $dedupeTtl = (int) Config::get('catchr.dedupe.ttl_seconds', 300);
        $dedupeStore = Config::get('catchr.dedupe.cache_store');
        $dedupePrefix = (string) Config::get('catchr.dedupe.prefix', 'catchr:seen:');
        $dedupeNormalize = (bool) Config::get('catchr.dedupe.normalize_message', true);

        $this->line('');
        $this->info('Catchr configuration summary');
        $this->line(str_repeat('-', 32));

        $this->line('Enabled: ' . ($enabled ? '<info>true</info>' : '<error>false</error>'));
        $this->line('App env: <comment>' . ($appEnv ?: '(empty)') . '</comment>');
        $this->line('Allowed envs: <comment>' . (empty($envs) ? '(empty => allowed)' : implode(', ', $envs)) . '</comment>');
        $this->line('Env allowed: ' . ($envAllowed ? '<info>true</info>' : '<error>false</error>'));
        $this->line('Timeout: <comment>' . $timeout . "s</comment>");

        $this->line('Endpoints (' . count($endpoints) . '):');
        if (count($endpoints) === 0) {
            $this->line('  - <error>(none configured)</error>');
        } else {
            foreach ($endpoints as $i => $url) {
                $this->line('  - [' . ($i + 1) . '] ' . $url);
            }
        }

        $this->line('');
        $this->info('Dedupe');
        $this->line('  Enabled: ' . ($dedupeEnabled ? '<info>true</info>' : '<comment>false</comment>'));
        $this->line('  TTL: <comment>' . $dedupeTtl . "s</comment>");
        $this->line('  Store: <comment>' . ($dedupeStore ?: '(default)') . '</comment>');
        $this->line('  Prefix: <comment>' . $dedupePrefix . '</comment>');
        $this->line('  Normalize message: ' . ($dedupeNormalize ? '<info>true</info>' : '<comment>false</comment>'));

        $this->line('');

        // Warnings amigables
        if (!$enabled) {
            $this->warn('Catchr is disabled (CATCHR_ENABLED=false).');
        }
        if (count($endpoints) === 0) {
            $this->warn('No endpoints configured (CATCHR_ENDPOINTS is empty).');
        }
        if (!$envAllowed) {
            $this->warn("Current env '{$appEnv}' is not in CATCHR_ENVS. Catchr will not report.");
        }

        return [
            'enabled' => $enabled,
            'endpoints_count' => count($endpoints),
            'env_allowed' => $envAllowed,
        ];
    }

    private function triggerTypeError(): void
    {
        $fn = function (int $x) {};
        $fn("not-an-int"); // TypeError
    }

    private function triggerDivisionByZero(): void
    {
        $x = 1 / 0;
        unset($x);
    }
}
