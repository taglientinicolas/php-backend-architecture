<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Support\Collection;

// ----------------------------------------------------------------------------
// STEP 1: Define the interface
// Services depend on this contract, not on Eloquent directly.
// ----------------------------------------------------------------------------

interface OrderRepositoryInterface
{
    public function findPendingByUser(int $userId): Collection;
    public function countPendingByUser(int $userId): int;
    public function findById(int $id): ?Order;
    public function save(Order $order): Order;
}

// ----------------------------------------------------------------------------
// STEP 2: Eloquent implementation
// All Eloquent-specific logic lives here.
// ----------------------------------------------------------------------------

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

    public function findById(int $id): ?Order
    {
        return Order::find($id);
    }

    public function save(Order $order): Order
    {
        $order->save();

        return $order;
    }
}

// ----------------------------------------------------------------------------
// STEP 3: Bind in a service provider
// Swap the implementation without touching any service.
// ----------------------------------------------------------------------------

// In AppServiceProvider::register():
// $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);

// ----------------------------------------------------------------------------
// STEP 4: Service depends on the interface
// No Eloquent import, no direct DB calls.
// ----------------------------------------------------------------------------

class OrderService
{
    public function __construct(private readonly OrderRepositoryInterface $orders) {}

    public function getPendingSummary(int $userId): array
    {
        return [
            'orders' => $this->orders->findPendingByUser($userId),
            'count'  => $this->orders->countPendingByUser($userId),
        ];
    }
}

// ----------------------------------------------------------------------------
// STEP 5: In-memory test double
// Unit-test the service without touching the database.
// ----------------------------------------------------------------------------

class InMemoryOrderRepository implements OrderRepositoryInterface
{
    private array $orders = [];

    public function seed(array $orders): void
    {
        $this->orders = $orders;
    }

    public function findPendingByUser(int $userId): Collection
    {
        return collect($this->orders)
            ->filter(fn ($o) => $o->user_id === $userId && $o->status === 'pending')
            ->values();
    }

    public function countPendingByUser(int $userId): int
    {
        return $this->findPendingByUser($userId)->count();
    }

    public function findById(int $id): ?Order
    {
        return collect($this->orders)->firstWhere('id', $id);
    }

    public function save(Order $order): Order
    {
        $this->orders[] = $order;

        return $order;
    }
}

// ----------------------------------------------------------------------------
// Test example (PHPUnit)
// ----------------------------------------------------------------------------

// class OrderServiceTest extends TestCase
// {
//     public function test_pending_summary_returns_correct_count(): void
//     {
//         $repo = new InMemoryOrderRepository();
//         $repo->seed([
//             (object) ['id' => 1, 'user_id' => 42, 'status' => 'pending'],
//             (object) ['id' => 2, 'user_id' => 42, 'status' => 'completed'],
//             (object) ['id' => 3, 'user_id' => 42, 'status' => 'pending'],
//         ]);
//
//         $service = new OrderService($repo);
//         $summary = $service->getPendingSummary(42);
//
//         $this->assertCount(2, $summary['orders']);
//         $this->assertSame(2, $summary['count']);
//     }
// }
