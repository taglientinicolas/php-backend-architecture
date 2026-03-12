# php-backend-architecture

A collection of structural backend architecture patterns for PHP applications.

Each pattern addresses a problem that appears as applications grow beyond simple CRUD: controllers that accumulate logic, inconsistent API responses, unhandled exceptions that reach clients, and data flowing between layers as untyped arrays.

---

## Why application structure matters

Clean structure is not an end in itself. It is what makes a codebase testable, navigable, and maintainable as teams and feature sets grow. These patterns represent practical decisions about where logic lives, how layers communicate, and how errors propagate — decisions that affect every engineer working on the system.

---

## Patterns

| Pattern | Problem addressed |
|---|---|
| [Service Layer](./service-layer-pattern/README.md) | Business logic leaking into controllers |
| [Repository Pattern](./repository-pattern/README.md) | Data access logic scattered across the codebase |
| [DTO Pattern](./dto-pattern/README.md) | Unstructured data flowing between application layers |
| [API Response Standardization](./api-response-standardization/README.md) | Inconsistent response shapes across endpoints |
| [Error Handling](./error-handling-pattern/README.md) | Unhandled exceptions and unpredictable error responses |

---

## Scope

These patterns target the **application layer** of a PHP/Laravel backend. The examples use Laravel conventions where relevant, but the concepts apply broadly to any PHP application with an MVC structure.

All examples target **PHP 8.1+** and **Laravel 9+** unless otherwise stated.

**Related repositories**  
For Laravel-specific performance patterns (N+1 queries, caching, indexing), see [laravel-performance-patterns](https://github.com/taglientinicolas/laravel-performance-patterns).  
For GoF design patterns applied to real backend use cases, see [php-design-patterns-in-practice](https://github.com/taglientinicolas/php-design-patterns-in-practice).

---

## Author

**Nicolas Taglienti** — Backend Engineer, PHP / Laravel  
[linkedin.com/in/nicolastaglienti](https://www.linkedin.com/in/nicolastaglienti/)
