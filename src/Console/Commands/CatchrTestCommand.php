<?php

namespace CceoDeveloper\Catchr\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CatchrTestCommand extends Command
{
    protected $signature = 'catchr:test
        {--type=exception : Type error: exception|sql|typeerror|zero}
        {--message= : Custom message (only for type=exception)}';

    protected $description = 'Trigger a test error to verify Catchr reporting.';

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        $type = (string) $this->option('type');
        $message = (string) ($this->option('message') ?? 'boom from catchr:test');

        $this->info("Triggering test error type={$type}");

        $this->trigger($type, $message);
    }

    private function trigger(string $type, string $message): void
    {
            match ($type) {
                'sql' => $this->triggerSql(),
                'typeerror' => $this->triggerTypeError(),
                'zero' => $this->triggerDivisionByZero(),
                default => throw new Exception($message),
            };
    }

    private function triggerSql(): void
    {
        DB::select('select * from this_table_does_not_exist');
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
