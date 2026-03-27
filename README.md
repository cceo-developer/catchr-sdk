# CATCHR

## Catchr (Laravel) — Error Reporting Package

Catchr is a Laravel package that intercepts exceptions, monitoring queue jobs and logs globally and reports them to one or more HTTP endpoints (webhooks/APIs). It includes sanitization of sensitive data, HTTP/Server/DB context, and deduplication (to prevent spam when the same error is repeated).

### Features

* **Global Exception Capture**: Intercepts all unhandled exceptions.
* **Queue Job Monitoring**: Automatically tracks job processing, success, and failures.
* **Log Reporting**: Forwards application logs to your configured endpoints.
* **Multi-endpoint Support**: Send data to multiple URLs simultaneously.
* **Rich Payload**:
  * Exception details (type, message, file, line, trace).
  * HTTP context (method, URL, parameters, sanitized headers, server).
  * DB context for `QueryException` (connection and SQL).
  * Authenticated user information.
* **Security & Privacy**: Automatically redacts sensitive headers and keys (e.g., `authorization`, `passwords`, `tokens`).
* **Persistent Deduplication**: Prevents notification storms by caching repeated errors within a configurable TTL.

## Installation

You can install the package via composer:

```bash
composer require cceo-developer/catchr-sdk
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="catchr-config"
```

## Configuration

### Exceptions
Capture and report exceptions globally.

* `CATCHR_EXCEPTION_ENABLED`: Enable/disable exception reporting (default: `true`).
* `CATCHR_EXCEPTION_ENDPOINTS`: Comma-separated list of URLs to report exceptions.
* `CATCHR_EXCEPTION_DEDUPE_ENABLED`: Enable/disable deduplication (default: `true`).

### Queue Jobs
Monitor your background jobs lifecycle.

* `CATCHR_QUEUE_ENABLED`: Enable/disable queue monitoring (default: `true`).
* `CATCHR_QUEUE_REPORT_PROCESSING`: Report when a job starts (default: `true`).
* `CATCHR_QUEUE_REPORT_PROCESSED`: Report when a job finishes successfully (default: `true`).
* `CATCHR_QUEUE_REPORT_FAILED`: Report when a job fails (default: `true`).
* `CATCHR_QUEUE_ENDPOINTS`: Comma-separated list of URLs for job reports.

### Logs
Forward your application logs to external services.

* `CATCHR_LOG_ENABLED`: Enable/disable log reporting (default: `true`).
* `CATCHR_LOG_ENDPOINTS`: Comma-separated list of URLs for log reports.

## Requirements

* PHP: 8.2+
* Laravel: 10 | 11 | 12
