<?php

namespace CceoDeveloper\Catchr\Support;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class WrappedExceptionHandler implements ExceptionHandler
{
    /** @var array<int, true> */
    private static array $seen = [];

    public function __construct(private readonly ExceptionHandler $inner) {}

    public function report(Throwable $e): void
    {
        $id = spl_object_id($e);
        if (isset(self::$seen[$id])) {
            $this->inner->report($e);
            return;
        }
        self::$seen[$id] = true;

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
        } catch (Throwable $ignored) {}

        $this->inner->report($e);
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