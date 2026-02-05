# CATCHR

## Catchr (Laravel) — Error Reporting Package

Catchr is a Laravel package that intercepts exceptions globally and reports them to one or more HTTP endpoints (webhooks/APIs). It includes sanitization of sensitive data, HTTP/Server/DB context, and deduplication (to prevent spam when the same error is repeated).

### Features

* Global exception capture using the `ExceptionHandler` wrapper.

* Sending to **multiple endpoints** (comma-separated list in `.env`).

* Rich payload:

  * exception (type, message, file, line)

  * HTTP context (method, URL, parameters, sanitized headers, server)

  * DB context for `QueryException` (connection and SQL)

  * Authenticated user (when applicable)
* Redacting of sensitive headers/keys (`authorization`, `cookies`, `tokens`, `passwords`, etc.).

* **Persistent Deduplication (Cache)**:

  * If the same error occurs repeatedly within a range (TTL), **it is only sent to HTTP endpoints the first time**.

  * Works across HTTP requests, Artisan commands, and different processes, as long as they share the same cache store.

  * Stable fingerprint (type + normalized message + location and top frame of the trace).

## Requirements

* PHP: 8.2
* Laravel: 10 | 11 | 12