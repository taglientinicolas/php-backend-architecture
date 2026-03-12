# php-backend-architecture

A curated collection of backend architecture patterns for PHP applications.

Each pattern addresses a structural problem that emerges as applications grow. The focus is on practical, production-oriented solutions — not theoretical abstractions.

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

---

## Author

**Nicolas Taglienti** — Backend Engineer, PHP / Laravel  
[linkedin.com/in/nicolastaglienti](https://www.linkedin.com/in/nicolastaglienti/)
