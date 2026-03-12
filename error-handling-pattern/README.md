# Error Handling Pattern

## Overview

Structured error handling means exceptions are categorised, mapped to appropriate HTTP responses, and logged with sufficient context — all in one place. The goal is to avoid scattered `try/catch` blocks throughout the codebase and ensure that both API consumers and internal monitoring systems receive the information they need.

---

## The Problem

Without a structured approach, error handling tends to exhibit one or more of these problems:

- `try/catch` blocks in controllers that each return a different error shape
- Unhandled exceptions that leak stack traces to API consumers
- Business exceptions and infrastructure exceptions treated identically
- Missing context in logs: the exception is logged but not the user, request, or operation that caused it
- `500` returned for validation errors or `404` returned for business rule violations

```php
// ❌ Ad-hoc error handling in the controller
public function store(Request $request): JsonResponse
{
    try {
        $order = $this->orders->place($request->validated());
        return response()->json($order, 201);
    } catch (Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
```

This catches everything including infrastructure failures, exposes raw exception messages to the client, and returns `500` for errors that may be `422` or `409`.

---

## Example

See [`example.php`](./example.php) for a complete implementation with custom exception classes, a global handler, and context-aware logging.

---

## Solution

Define a hierarchy of custom exceptions. Map each exception type to an HTTP response in the global exception handler. Log at the appropriate severity with context.

```php
// ✅ Domain exception — carries a semantic error code
class OrderLimitExceededException extends DomainException
{
    public function __construct(int $userId)
    {
        parent::__construct("User {$userId} has reached the maximum order limit.");
    }
}

// ✅ Global handler maps exception types to HTTP responses
// In app/Exceptions/Handler.php or bootstrap/app.php (Laravel 11+)
$exceptions->render(function (OrderLimitExceededException $e, Request $request) {
    return ApiResponse::error(
        message: $e->getMessage(),
        code:    'ORDER_LIMIT_EXCEEDED',
        status:  422,
    );
});
```

The controller stays clean:

```php
public function store(OrderRequest $request): JsonResponse
{
    // OrderLimitExceededException bubbles up to the global handler
    $order = $this->orders->place(auth()->user(), $request->validated());

    return ApiResponse::created(new OrderResource($order));
}
```

---

## Why It Matters

- **Separation of concerns**: controllers handle HTTP flow; the exception handler maps domain errors to HTTP semantics
- **Consistent error responses**: all exceptions are handled in one place, producing a uniform shape
- **Safe for API consumers**: no stack traces, no internal messages in production responses
- **Observability**: centralised logging with structured context makes errors easier to trace

---

## Production Notes

**Never expose raw exception messages to API consumers in production.**  
`$e->getMessage()` is acceptable for domain exceptions that intentionally carry user-facing messages. For infrastructure exceptions (PDO errors, filesystem failures), log the detail internally and return a generic message externally.

**Use different log levels for different exception types.**  
A `ValidationException` is not an error — it is expected behaviour. Log it at `debug` or skip logging it entirely. A database connection failure is a critical error that warrants an immediate alert. Use `Log::error()` or `Log::critical()` with context accordingly.

**Include structured context in logs.**  
`Log::error($e->getMessage())` is marginally better than nothing. `Log::error('Order placement failed', ['user_id' => $userId, 'exception' => $e])` is actually useful during incident investigation.

**Distinguish between recoverable and unrecoverable exceptions.**  
A `ModelNotFoundException` is recoverable — return a `404`. A failed database connection is not recoverable at the application level — return a `503` and alert the team. Treating them the same produces misleading responses and noisy logs.

**In Laravel, prefer `withExceptions()` over overriding `Handler::render()`.**  
Laravel 11 introduced `bootstrap/app.php` `withExceptions()` as the idiomatic registration point. It is more readable and avoids subclassing `Handler` for each exception type.

**Use `report()` and `render()` separately when needed.**  
Some exceptions should be logged (`report()`) but should not generate a custom response — they fall through to the default handler. Others should produce a custom response but not generate noise in the logs. The two concerns are independent.
