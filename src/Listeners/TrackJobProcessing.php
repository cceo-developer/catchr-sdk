<?php

namespace CceoDeveloper\Catchr\Listeners;

use CceoDeveloper\Catchr\Support\Jobs\JobRunStore;
use CceoDeveloper\Catchr\Support\Jobs\QueueJobMeta;
use CceoDeveloper\Catchr\Support\Jobs\QueueReporter;
use Illuminate\Support\Facades\Config;
use Throwable;

class TrackJobProcessing
{
    public function handle(\Illuminate\Queue\Events\JobProcessing $event): void
    {
        try {
            if (!Config::get('catchr.queue.report_processing', true)) {
                return;
            }

            $meta = QueueJobMeta::extract($event->job, $event->connectionName);
            $job = QueueJobMeta::jobPayload($event->job);

            (new JobRunStore())->markProcessing($meta);

            (new QueueReporter())->report(
                event: 'queue.processing',
                jobMeta: [
                    'connection' => $meta['connection'],
                    'queue' => $meta['queue'],
                    'job_name' => $meta['job_name'],
                    'job_id' => $meta['job_id'],
                    'attempts' => $meta['attempts'],
                    'max_tries' => $meta['max_tries'],
                    'timeout' => $meta['timeout'],
                    'run_key' => $meta['run_key'],
                    'status' => 'processing',
                    'job' => $job,
                ],
                exception: null
            );
        } catch (Throwable $ignored) {
            @error_log('[Catchr] Failed to track job processing: ' . get_class($ignored) . ' - ' . $ignored->getMessage());
        }
    }
}