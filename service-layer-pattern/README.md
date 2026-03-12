# Service Layer Pattern

## Overview

The Service Layer pattern extracts business logic from controllers into dedicated classes. A controller's responsibility is to receive an HTTP request, delegate to a service, and return a response. Business rules — validation logic beyond input format, orchestration of multiple operations, side effects like sending emails or dispatching jobs — belong in a service.

---

## The Problem

As features grow, controllers accumulate logic: database queries, conditional branching, event dispatching, external API calls. The result is a controller that is difficult to test, impossible to reuse, and costly to change.

```php
// ❌ Controller doing too much
class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([...]);

        $user = auth()->user();

        if ($user->orders()->count() >= 10) {
            return response()->json(['error' => 'Order limit reached'], 422);
        }

        $order = Order::create([
            'user_id' => $user->id,
            'total'   => collect($validated['items'])->sum('price'),
        ]);

        foreach ($validated['items'] as $item) {
            $order->items()->create($item);
        }

        Mail::to($user)->send(new OrderConfirmation($order));
        event(new OrderPlaced($order));

        return response()->json($order, 201);
    }
}
```

This controller cannot be tested without an HTTP request, and the order creation logic cannot be reused from a CLI command or a queue job.

---

## Example

See [`example.php`](./example.php) for a full before/after with a realistic order placement flow.

---

## Solution

Move all business logic into a service class. The controller becomes a thin adapter between HTTP and the application layer.

```php
// ✅ Controller delegates entirely
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function store(OrderRequest $request): JsonResponse
    {
        $order = $this->orders->place(auth()->user(), $request->validated());

        return response()->json($order, 201);
    }
}

// ✅ Service owns the business logic
class OrderService
{
    public function place(User $user, array $data): Order
    {
        if ($user->orders()->count() >= 10) {
            throw new OrderLimitExceededException();
        }

        return DB::transaction(function () use ($user, $data) {
            $order = Order::create([
                'user_id' => $user->id,
                'total'   => collect($data['items'])->sum('price'),
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create($item);
            }

            Mail::to($user)->send(new OrderConfirmation($order));
            event(new OrderPlaced($order));

            return $order;
        });
    }
}
```

---

## Why It Matters

- **Testability**: services can be unit-tested without an HTTP context
- **Reusability**: the same service method can be called from a controller, a console command, or a queue job
- **Single responsibility**: the controller handles HTTP; the service handles the domain

---

## Trade-offs

The pattern adds a class. For simple CRUD operations with no business rules — where the controller passes validated data directly to a model — a service layer is ceremony without benefit.

Apply it when there are multiple steps, conditions, or side effects involved, or when the same logic needs to be callable from more than one entry point.

Avoid the model-per-service convention (`UserService`, `OrderService` for every model). Create services around operations or use cases (`OrderPlacementService`, `SubscriptionRenewalService`).

---

## Production Notes

**Inject services via the constructor, not the facade or `app()`.**  
Constructor injection makes dependencies explicit and testable. Laravel's container resolves them automatically.

**Services should not know about HTTP.**  
A service method must not access `request()`, `auth()`, or return a `Response` object. Pass data in as plain PHP values; let the controller handle the HTTP layer.

**Throw domain exceptions, not HTTP exceptions, from services.**  
A service that throws `HttpException` is coupled to the transport layer. Throw `OrderLimitExceededException` and let the controller — or a global exception handler — map it to an HTTP response.

**Avoid making services too coarse-grained.**  
A single `UserService` that handles registration, password reset, profile updates, and account deletion is a god class in disguise. Group by cohesive responsibility, not by model.

**Side effects inside a transaction need careful handling.**  
Sending an email inside a `DB::transaction` block means the email may be sent even if the transaction rolls back. Prefer dispatching a queued job after the transaction commits, or use Laravel's `DB::afterCommit` hook (available in Laravel 8+).
