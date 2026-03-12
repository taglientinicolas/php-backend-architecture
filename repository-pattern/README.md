# Repository Pattern

## Overview

The Repository pattern introduces an abstraction layer between the application and the data source. Instead of calling Eloquent directly throughout the codebase, data access is centralised in repository classes that expose a domain-oriented interface.

This pattern is genuinely useful in specific contexts — it is not universally required. In applications where Eloquent is the only data source and is unlikely to change, adding a repository layer may introduce indirection without meaningful benefit. The pattern earns its place when testability, data source flexibility, or query complexity justify the abstraction.

---

## The Problem

When Eloquent query logic is scattered across controllers, services, and jobs, several problems emerge:

- The same query is duplicated in multiple places with minor variations
- Changing a query (adding a global scope, changing the sort order) requires finding every occurrence
- Unit testing a service that calls Eloquent directly requires a database connection or complex mocking

```php
// ❌ Query logic duplicated across the codebase
// In OrderController:
$orders = Order::where('user_id', $userId)->where('status', 'pending')->latest()->get();

// In OrderExportJob:
$orders = Order::where('user_id', $userId)->where('status', 'pending')->latest()->get();

// In OrderService:
$count = Order::where('user_id', $userId)->where('status', 'pending')->count();
```

---

## Example

See [`example.php`](./example.php) for a full implementation with an interface, an Eloquent implementation, and a test double.

---

## Solution

Define a repository interface and provide an Eloquent implementation. Services depend on the interface, not the concrete class.

```php
// ✅ Interface defines the contract
interface OrderRepositoryInterface
{
    public function findPendingByUser(int $userId): Collection;
    public function countPendingByUser(int $userId): int;
}

// ✅ Eloquent implementation
class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function findPendingByUser(int $userId): Collection
    {
        return Order::where('user_id', $userId)
            ->where('status', 'pending')
            ->latest()
            ->get();
    }

    public function countPendingByUser(int $userId): int
    {
        return Order::where('user_id', $userId)
            ->where('status', 'pending')
            ->count();
    }
}

// ✅ Service depends on the interface
class OrderService
{
    public function __construct(private readonly OrderRepositoryInterface $orders) {}

    public function getPendingSummary(int $userId): array
    {
        return [
            'items' => $this->orders->findPendingByUser($userId),
            'count' => $this->orders->countPendingByUser($userId),
        ];
    }
}
```

Bind the interface in a service provider:

```php
$this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
```

---

## Why It Matters

- **Single source of truth for queries**: changes to query logic are made in one place
- **Testability**: services can be unit-tested by swapping in an in-memory or mock repository
- **Explicit API**: the repository interface documents what data operations the application actually performs

---

## Trade-offs

The repository layer adds an interface and a class per entity. If the application has a single data source with no plans to change it, the abstraction primarily adds testing value. In that case, evaluate whether feature tests against an in-memory SQLite database provide sufficient coverage at lower complexity cost.

The pattern earns its place when:
- the same query logic appears in multiple services or jobs
- fast unit tests requiring test doubles are a stated goal
- there is a realistic possibility of swapping the data source (e.g., read models backed by Elasticsearch or Redis)

Do not use repositories as a convention applied to every model. Use them where the abstraction provides measurable benefit.

---

## Production Notes

**Do not make repositories generic CRUD wrappers.**  
A repository with `find()`, `findAll()`, `save()`, `delete()` is just a thin wrapper over Eloquent with no domain value. Repository methods should reflect the application's actual query needs: `findPendingByUser()`, `findOverdueInvoices()`.

**Repositories should return domain objects, not query builders.**  
Returning a query builder from a repository defeats the purpose of the abstraction — the caller can still modify the query. Return collections, models, or DTOs.

**Avoid injecting repositories into controllers.**  
A controller that calls a repository directly is a controller doing the service's job. Repositories belong one layer below services, not at the HTTP boundary.

**In Laravel, `$model->newQuery()` in tests is often sufficient.**  
Laravel's Eloquent models can be bound to an in-memory SQLite database in tests. This is simpler than a full repository mock for many scenarios. Use the repository pattern when the complexity of your queries or the need for non-database test doubles genuinely justifies it.
