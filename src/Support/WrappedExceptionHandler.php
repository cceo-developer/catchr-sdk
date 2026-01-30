<?php

namespace CceoDeveloper\Catchr\Support;

use Carbon\Carbon;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

readonly class WrappedExceptionHandler implements ExceptionHandler
{
    public function __construct(private ExceptionHandler $inner) {}

    public function report(Throwable $e): void
    {
        if ($this->alreadySeen($e)) {
            $this->inner->report($e);
            return;
        }

        try {
            if ($this->inner->shouldReport($e)) {
                (new HttpReporter())->report($e);
                Log::error('[Catchr] Captured exception', [
                    'type' => get_class($e),
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        } catch (Throwable $ignored) {
            @error_log('[Catchr] Failed to report exception: ' . get_class($ignored) . ' - ' . $ignored->getMessage());
        }

        $this->inner->report($e);
    }

    private function alreadySeen(Throwable $e): bool
    {
        $dedupeEnabled = (bool) Config::get('catchr.dedupe.enabled', true);
        if (! $dedupeEnabled) {
            return false;
        }

        $ttl = (int) Config::get('catchr.dedupe.ttl_seconds', 300);
        $prefix = (string) Config::get('catchr.dedupe.prefix', 'catchr:seen:');
        $store = Config::get('catchr.dedupe.cache_store');

        $key = $prefix . $this->fingerprint($e);

        $cache = $store ? Cache::store($store) : Cache::store();

        $firstTime = $cache->add($key, 1, Carbon::now()->addSeconds($ttl));

        return $firstTime === false;
    }


    private function fingerprint(Throwable $e): string
    {
        $top = $e->getTrace()[0] ?? [];

        $message = $this->normalizeMessage($e->getMessage());

        $payload = [
            'type' => get_class($e),
            'message' => $message,
            'code' => (string) $e->getCode(),
            'file' => $e->getFile(),
            'line' => (string) $e->getLine(),
            'top_file' => $top['file'] ?? null,
            'top_line' => isset($top['line']) ? (string) $top['line'] : null,
            'top_fn' => $top['function'] ?? null,
            'top_class' => $top['class'] ?? null,
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function normalizeMessage(string $message): string
    {
        $doNormalize = (bool) Config::get('catchr.dedupe.normalize_message', true);
        if (! $doNormalize) {
            return $message;
        }

        $uuidNormalized = preg_replace(
            '/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i',
            '{uuid}',
            $message
        );

        if ($uuidNormalized === null) {
            return $message;
        }

        $numbersNormalized = preg_replace('/\b\d{4,}\b/', '{n}', $uuidNormalized);

        return $numbersNormalized ?? $uuidNormalized;
    }


    public function shouldReport(Throwable $e): bool
    {
        return $this->inner->shouldReport($e);
    }

    public function render($request, Throwable $e)
    {
        return $this->inner->render($request, $e);
    }

    public function renderForConsole($output, Throwable $e): void
    {
        $this->inner->renderForConsole($output, $e);
    }
}