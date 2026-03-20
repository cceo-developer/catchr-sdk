<?php

namespace CceoDeveloper\Catchr\Support\Jobs;

use Illuminate\Queue\Jobs\Job;

class QueueJobMeta
{
    public static function extract(Job $job, ?string $connectionName = null): array
    {
        $payload = $job->payload();

        $jobName = method_exists($job, 'resolveName')
            ? $job->resolveName()
            : ($payload['displayName'] ?? null);

        $queue = method_exists($job, 'getQueue')
            ? $job->getQueue()
            : ($payload['queue'] ?? null);

        $attempts = method_exists($job, 'attempts') ? (int) $job->attempts() : 0;

        $uuid = $payload['uuid'] ?? null;
        $maxTries = $payload['maxTries'] ?? null;
        $timeout = $payload['timeout'] ?? null;

        $jobId = method_exists($job, 'getJobId') ? (string) $job->getJobId() : null;

        $runKey = $uuid ?: ($jobId ?: hash('sha256', ($connectionName . '|' . $queue . '|' . $jobName . '|' . microtime(true))));

        $fingerprintBase = implode('|', array_filter([
            (string) $connectionName,
            (string) $queue,
            (string) $jobName,
            (string) ($payload['maxTries'] ?? ''),
            (string) ($payload['timeout'] ?? ''),
        ]));

        return [
            'run_key' => $runKey,
            'fingerprint' => hash('sha256', $fingerprintBase ?: json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),

            'connection' => $connectionName,
            'queue' => $queue,
            'job_name' => $jobName,
            'job_id' => $jobId,
            'uuid' => $uuid,

            'attempts' => $attempts,
            'max_tries' => is_null($maxTries) ? null : (int) $maxTries,
            'timeout' => is_null($timeout) ? null : (int) $timeout,
        ];
    }

    public static function jobPayload(Job $job): array
    {
        return $job->payload();
    }

}