<?php

namespace CceoDeveloper\Catchr\Support\Jobs;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class JobRunStore
{
    private const TABLE = 'catchr_job_runs';

    private static ?bool $tableExists = null;

    public function markProcessing(array $meta): void
    {
        $this->safe(function () use ($meta) {
            $now = Carbon::now();

            DB::table(self::TABLE)->updateOrInsert(
                ['run_key' => $meta['run_key']],
                [
                    'fingerprint' => $meta['fingerprint'] ?? null,
                    'connection' => $meta['connection'] ?? null,
                    'queue' => $meta['queue'] ?? null,
                    'job_name' => $meta['job_name'] ?? null,
                    'job_id' => $meta['job_id'] ?? null,
                    'uuid' => $meta['uuid'] ?? null,

                    'status' => 'processing',
                    'attempts' => $meta['attempts'] ?? 0,
                    'max_tries' => $meta['max_tries'] ?? null,
                    'timeout' => $meta['timeout'] ?? null,

                    'started_at' => $now,
                    'finished_at' => null,
                    'duration_ms' => null,

                    'exception_class' => null,
                    'exception_message' => null,

                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        });
    }

    public function markProcessed(array $meta): ?int
    {
        return $this->safe(function () use ($meta) {
            $now = Carbon::now();

            $row = DB::table(self::TABLE)
                ->where('run_key', $meta['run_key'])
                ->first();

            $startedAt = $row?->started_at ? Carbon::parse($row->started_at) : null;
            $durationMs = $startedAt ? $startedAt->diffInMilliseconds($now) : null;

            DB::table(self::TABLE)->updateOrInsert(
                ['run_key' => $meta['run_key']],
                [
                    'fingerprint' => $meta['fingerprint'] ?? null,
                    'connection' => $meta['connection'] ?? null,
                    'queue' => $meta['queue'] ?? null,
                    'job_name' => $meta['job_name'] ?? null,
                    'job_id' => $meta['job_id'] ?? null,
                    'uuid' => $meta['uuid'] ?? null,

                    'status' => 'processed',
                    'attempts' => $meta['attempts'] ?? 0,
                    'max_tries' => $meta['max_tries'] ?? null,
                    'timeout' => $meta['timeout'] ?? null,

                    'finished_at' => $now,
                    'duration_ms' => $durationMs,

                    'updated_at' => $now,
                    'created_at' => $row?->created_at ?? $now,
                ]
            );

            return $durationMs;
        });
    }

    public function markFailed(array $meta, Throwable $e): ?int
    {
        return $this->safe(function () use ($meta, $e) {
            $now = Carbon::now();

            $row = DB::table(self::TABLE)
                ->where('run_key', $meta['run_key'])
                ->first();

            $startedAt = $row?->started_at ? Carbon::parse($row->started_at) : null;
            $durationMs = $startedAt?->diffInMilliseconds($now);

            DB::table(self::TABLE)->updateOrInsert(
                ['run_key' => $meta['run_key']],
                [
                    'fingerprint' => $meta['fingerprint'] ?? null,
                    'connection' => $meta['connection'] ?? null,
                    'queue' => $meta['queue'] ?? null,
                    'job_name' => $meta['job_name'] ?? null,
                    'job_id' => $meta['job_id'] ?? null,
                    'uuid' => $meta['uuid'] ?? null,

                    'status' => 'failed',
                    'attempts' => $meta['attempts'] ?? 0,
                    'max_tries' => $meta['max_tries'] ?? null,
                    'timeout' => $meta['timeout'] ?? null,

                    'finished_at' => $now,
                    'duration_ms' => $durationMs,

                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),

                    'updated_at' => $now,
                    'created_at' => $row?->created_at ?? $now,
                ]
            );

            return $durationMs;
        });
    }

    /**
     * Perform a secure operation on data dabe.
     * - if the table does not exist: the function does nothing.
     * - If DB fails: it does not crash (it only records error_log).
     */
    private function safe(callable $fn)
    {
        try {
            if (!$this->tableExists()) {
                return null;
            }

            return $fn();
        } catch (Throwable $ignored) {
            @error_log('[Catchr] JobRunStore failed: ' . get_class($ignored) . ' - ' . $ignored->getMessage());
            return null;
        }
    }

    private function tableExists(): bool
    {
        if (self::$tableExists !== null) {
            return self::$tableExists;
        }

        try {
            return self::$tableExists = Schema::hasTable(self::TABLE);
        } catch (Throwable $e) {
            @error_log('[Catchr] Schema check failed: ' . get_class($e) . ' - ' . $e->getMessage());
            return self::$tableExists = false;
        }
    }
}