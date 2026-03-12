# DTO Pattern

## Overview

A Data Transfer Object (DTO) is a simple, immutable object that carries structured data between layers of an application. It has no behaviour — only typed properties. DTOs make the data contract between layers explicit and verifiable at development time rather than at runtime.

---

## The Problem

Passing raw arrays between layers is common in PHP, but it has clear drawbacks:

- There is no type safety: a missing key or a wrong type fails only at runtime
- The shape of the data is invisible to static analysis and IDE tooling
- Any layer can add, remove, or rename keys without the compiler or linter catching it

```php
// ❌ What is the shape of $data? No way to know without reading the entire call chain.
public function registerUser(array $data): User
{
    return User::create([
        'name'     => $data['name'],
        'email'    => $data['email'],
        'password' => bcrypt($data['password']),
    ]);
}
```

If `$data['email']` is missing, the error surfaces at database level — not at the boundary where the data entered the system.

---

## Example

See [`example.php`](./example.php) for a full implementation using PHP 8.1 readonly properties.

---

## Solution

Define a DTO class with typed, readonly properties. Construct it at the boundary (controller or form request), and pass it through the application.

```php
// ✅ Shape is explicit and type-safe
final class RegisterUserData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
    ) {}
}

// ✅ Service receives a DTO, not a raw array
public function registerUser(RegisterUserData $data): User
{
    return User::create([
        'name'     => $data->name,
        'email'    => $data->email,
        'password' => bcrypt($data->password),
    ]);
}

// ✅ Controller constructs the DTO from the validated request
public function store(RegisterRequest $request): JsonResponse
{
    $user = $this->users->registerUser(new RegisterUserData(
        name:     $request->validated('name'),
        email:    $request->validated('email'),
        password: $request->validated('password'),
    ));

    return response()->json($user, 201);
}
```

---

## Why It Matters

- **Explicit contracts**: the DTO is the documented interface between layers — no implicit array shape to guess
- **Static analysis**: tools like PHPStan and Psalm can verify that all required properties are provided and correctly typed
- **Refactoring safety**: renaming a property causes a compile-time error in every caller, not a silent runtime bug
- **Readability**: `RegisterUserData` is more descriptive than `array $data` in a method signature

---

## Trade-offs

For simple operations with one or two parameters unlikely to change, a dedicated class may add overhead without meaningful benefit. Passing `string $name, string $email` directly is clearer than constructing a DTO wrapping two strings.

The pattern earns its place when the data structure is complex, shared across multiple layers, or used from multiple entry points (controller, CLI command, queue job). The value compounds as the number of properties grows and the DTO is passed deeper through the system.

---

## Production Notes

**Use PHP 8.1 readonly properties.**  
`readonly` properties enforce immutability at the language level without requiring explicit getter methods or private properties. A DTO modified after construction is a source of subtle bugs.

**Keep DTOs free of behaviour.**  
A DTO with a `validate()`, `save()`, or `toResponse()` method is no longer a DTO. If transformation logic is needed, put it in a dedicated class or in the service that consumes the DTO. The only acceptable method on a DTO is a named constructor (e.g., `fromRequest()`) that constructs it from an external source.

**A `fromRequest()` factory keeps the controller clean.**  
Rather than constructing the DTO inline in the controller, add a static factory method on the DTO itself:

```php
public static function fromRequest(RegisterRequest $request): self
{
    return new self(
        name:     $request->validated('name'),
        email:    $request->validated('email'),
        password: $request->validated('password'),
    );
}
```

**DTOs and validation are separate concerns.**  
The DTO assumes its input is already valid. Validation (format, uniqueness, business rules) belongs in a Form Request or in the service. The DTO is the clean data carrier after validation has passed.

**Consider PHP 8.2 readonly classes for simple DTOs.**  
`readonly class` makes all properties implicitly readonly without annotating each one individually, reducing boilerplate for simple data structures.
