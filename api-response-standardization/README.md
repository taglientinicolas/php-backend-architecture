# API Response Standardization

## Overview

A standardised API response shape means every endpoint returns data in a predictable structure. Consumers — whether a frontend, a mobile app, or another service — can handle responses uniformly without per-endpoint parsing logic.

This pattern is not about enforcing a specific JSON schema, but about eliminating inconsistency across endpoints within the same application.

---

## The Problem

Without a standard, response shapes drift over time:

```json
// Endpoint A: wraps data in "data"
{ "data": { "id": 1, "name": "Nicolas" } }

// Endpoint B: returns the resource directly
{ "id": 1, "name": "Nicolas" }

// Endpoint C: uses a different error shape
{ "message": "Not found" }

// Endpoint D: uses yet another error shape
{ "error": "User not found", "code": 404 }
```

Consumers must write defensive code for each endpoint. Error handling becomes inconsistent. Adding pagination metadata to endpoint B requires a breaking change.

---

## Example

See [`example.php`](./example.php) for a full implementation using a response wrapper, Laravel API Resources, and a global exception handler.

---

## Solution

Define a consistent envelope for all responses and enforce it through a shared wrapper class and Laravel API Resources.

**Success response:**
```json
{
  "data": { ... },
  "meta": { }
}
```

**Paginated response:**
```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 4,
    "per_page": 15,
    "total": 60
  }
}
```

**Error response:**
```json
{
  "error": {
    "message": "The given data was invalid.",
    "code": "VALIDATION_ERROR",
    "details": { "email": ["The email field is required."] }
  }
}
```

```php
// ✅ Shared response helper
class ApiResponse
{
    public static function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    public static function error(string $message, string $code, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'error' => array_filter([
                'message' => $message,
                'code'    => $code,
                'details' => $details ?: null,
            ]),
        ], $status);
    }
}
```

---

## Why It Matters

- **Predictability**: consumers write one response parser, not one per endpoint
- **Evolvability**: adding `meta` to a success response is non-breaking if the envelope was there from the start
- **Consistent error handling**: a single error shape means clients handle errors once, not case by case
- **API Resources enforce structure at the serialization layer**: the response shape is defined independently from the model

---

## Trade-offs

Standardization requires discipline: every endpoint must use the shared wrapper, and new developers must learn the convention. In applications with a single internal consumer and no external contract, strict envelope enforcement may not be worth the overhead.

The pattern earns its place when multiple consumers (frontend, mobile, third-party integrations) depend on a stable and predictable response contract, or when the API is versioned and breaking changes must be avoided.

---

## Production Notes

**Use Laravel API Resources for response shaping, not `$model->toArray()`.**  
`toArray()` exposes the model's internal attribute structure and can leak sensitive fields. API Resources give explicit control over what is serialized and in what format.

**Distinguish between client errors and server errors in the error shape.**  
A `422 Unprocessable Entity` with validation details is a client error — include the field-level errors. A `500 Internal Server Error` should not expose stack traces or internal messages to the client. Log the details internally; return a generic message externally.

**Keep the `data` key consistent.**  
Return a single object for `show` endpoints and an array for `index` endpoints. Avoid returning an object in some endpoints and a root-level key in others.

**Pagination metadata belongs in `meta`, not `data`.**  
Mixing `total`, `current_page`, and the actual records in the same object makes the response harder to consume. Laravel's `ResourceCollection` with `paginationInformation()` provides a clean way to separate them.

**Do not return `null` for empty responses.**  
A `204 No Content` response should have no body. A `200` response with `{"data": null}` is ambiguous — prefer `{"data": {}}` for empty objects or `{"data": []}` for empty collections if a body is expected.
