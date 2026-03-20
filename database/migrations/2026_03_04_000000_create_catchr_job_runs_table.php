<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catchr_job_runs', function ($table) {
            $table->id();

            // Job identity
            $table->string('connection')->nullable();
            $table->string('queue')->nullable();
            $table->string('job_name')->nullable();
            $table->string('job_id')->nullable();
            $table->string('uuid')->nullable();
            $table->string('fingerprint', 64)->index();
            $table->string('run_key')->unique();

            $table->string('status')->index(); // processing|processed|failed
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_tries')->nullable();
            $table->unsignedInteger('timeout')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            // Timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // Error
            $table->string('exception_class')->nullable();
            $table->text('exception_message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catchr_job_runs');
    }
};
