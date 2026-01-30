<?php

namespace CceoDeveloper\Catchr\Support;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class WrappedExceptionHandler implements ExceptionHandler
{
    /** @var array<int, int> id => unix_ts */
    private static array $seen = [];

    private const SEEN_TTL_SECONDS = 30;
    private const SEEN_MAX = 256;
    private const GC_EVERY = 25;

    private static int $counter = 0;

    public function __construct(private readonly ExceptionHandler $inner) {}

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
        $id = spl_object_id($e);
        $now = time();

        self::$counter++;
        if (self::$counter % self::GC_EVERY === 0) {
            $this->prune($now);
        }

        if (isset(self::$seen[$id]) && ($now - self::$seen[$id]) <= self::SEEN_TTL_SECONDS) {
            return true;
        }

        self::$seen[$id] = $now;

        if (count(self::$seen) > self::SEEN_MAX) {
            $this->prune($now, aggressive: true);
        }

        return false;
    }

    private function prune(int $now, bool $aggressive = false): void
    {
        foreach (self::$seen as $id => $ts) {
            if (($now - $ts) > self::SEEN_TTL_SECONDS) {
                unset(self::$seen[$id]);
            }
        }

        if ($aggressive && count(self::$seen) > self::SEEN_MAX) {
            arsort(self::$seen);
            self::$seen = array_slice(self::$seen, 0, self::SEEN_MAX, true);
        }
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