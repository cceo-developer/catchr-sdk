<?php

namespace CceoDeveloper\Catchr\Support;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Throwable;

class PayloadBuilder
{
    public function build(Throwable $e, ?Request $request = null): array
    {
        $payload = [
            'app' => Config::get('app.name'),
            'env' => Config::get('app.env'),
            'timestamp' => Carbon::now()->toIso8601String(),
            'exception' => [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code_snippet' => $this->codeSnippet($e->getFile(), $e->getLine(), 6),
                'trace' => collect($e->getTrace())->all()
            ],
        ];

        if ($e instanceof QueryException) {
            $payload['db'] = [
                'connection' => $e->getConnectionName(),
                'sql' => $e->getSql(),
            ];
        }

        if ($request) {
            $payload['http'] = [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'route' => optional($request->route())->uri(),
                'route_name' => optional($request->route())->getName(),
                'parameters' => [
                    'query' => $this->sanitize($request->query()),
                    'body' => $this->sanitize($request->all()),
                ],
                'headers' => $this->sanitizeHeaders($request->headers->all()),
                'server' => $this->sanitizeServer($request->server->all()),
            ];

            $user = $request->user() ?? Auth::guard('api')->user() ?? Auth::guard('web')->user();

            if($user) {
                $payload['user'] = $user;
            }
        }

        return $payload;
    }

    private function sanitizeHeaders(array $headers): array
    {
        $deny = array_map('strtolower', Config::get('catchr.redact_headers', [
            'authorization', 'cookie', 'set-cookie', 'x-csrf-token', 'x-xsrf-token',
        ]));

        $out = [];
        foreach ($headers as $key => $value) {
            $k = strtolower((string) $key);

            if (in_array($k, $deny, true)) {
                $out[$key] = ['***'];
                continue;
            }

            $out[$key] = array_map(fn ($v) => $this->clip((string) $v, 400), (array) $value);
        }

        return $out;
    }

    private function sanitizeServer(array $server): array
    {
        $allow = Config::get('catchr.server_allow', [
            'USER', 'PHP_VERSION', 'SERVER_PROTOCOL', 'SERVER_SOFTWARE', 'REQUEST_METHOD',
            'REQUEST_URI', 'REMOTE_ADDR',
        ]);

        $out = [];
        foreach ($allow as $key) {
            if (isset($server[$key])) {
                $out[$key] = $this->clip((string) $server[$key], 400);
            }
        }

        foreach ($server as $key => $value) {
            if (!str_starts_with((string) $key, 'HTTP_')) continue;

            $low = strtolower((string) $key);
            if (str_contains($low, 'authorization') || str_contains($low, 'cookie')) {
                $out[$key] = '***';
                continue;
            }

            if (count($out) > 60) break;

            $out[$key] = $this->clip((string) $value, 400);
        }

        return $out;
    }

    private function sanitize(array $data): array
    {
        $redactKeys = array_map('strtolower', Config::get('catchr.redact_keys', [
            'password', 'password_confirmation', 'token', 'access_token', 'refresh_token',
            'authorization', 'cookie', 'ssn',
        ]));

        $walk = function ($value, $key) use (&$walk, $redactKeys) {
            if (is_array($value)) {
                $out = [];
                foreach ($value as $k => $v) {
                    $out[$k] = $walk($v, (string) $k);
                }
                return $out;
            }

            if (in_array(strtolower((string) $key), $redactKeys, true)) {
                return '***';
            }

            if (is_string($value)) {
                return $this->clip($value, 800);
            }

            return $value;
        };

        $out = [];
        foreach ($data as $k => $v) {
            $out[$k] = $walk($v, (string) $k);
        }

        return $out;
    }

    private function clip(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) . '…' : $value;
    }

    private function codeSnippet(?string $file, ?int $line, int $padding = 6): ?array
    {
        if (!$file || !$line) return null;
        if (!is_file($file) || !is_readable($file)) return null;

        if (str_contains($file, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
            return null;
        }

        $start = max(1, $line - $padding);
        $end = $line + $padding;

        try {
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            if ($lines === false) return null;

            $out = [];
            for ($i = $start; $i <= $end; $i++) {
                $idx = $i - 1;
                if (!isset($lines[$idx])) continue;

                $out[] = [
                    'line' => $i,
                    'code' => $this->clip($lines[$idx], 300),
                    'highlight' => $i === $line,
                ];
            }

            return [
                'file' => $file,
                'line' => $line,
                'start' => $start,
                'end' => $end,
                'lines' => $out,
            ];
        } catch (Throwable $ignored) {
            @error_log(
                '[Catchr] Failed to report exception: ' .
                get_class($ignored) . ' - ' . $ignored->getMessage()
            );
            return null;
        }
    }
}
