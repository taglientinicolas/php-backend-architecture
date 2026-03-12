<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\OrderLimitExceededException;
use App\Http\Requests\OrderRequest;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// ----------------------------------------------------------------------------
// BEFORE: Fat controller
// Business logic, orchestration, and side effects all live in the controller.
// Cannot be unit-tested without a full HTTP context.
// Cannot be reused from a CLI command or queue job.
// ----------------------------------------------------------------------------

class OrderController extends Controller
{
    public function store(OrderRequest $request): JsonResponse
    {
        $user = auth()->user();

        // ❌ Business rule in the controller
        if ($user->orders()->count() >= 10) {
            return response()->json(['error' => 'Order limit reached'], 422);
        }

        // ❌ Orchestration in the controller
        $order = DB::transaction(function () use ($user, $request) {
            $order = Order::create([
                'user_id' => $user->id,
                'total'   => collect($request->items)->sum('price'),
            ]);

            foreach ($request->items as $item) {
                $order->items()->create($item);
            }

            return $order;
        });

        // ❌ Side effects in the controller
        Mail::to($user)->send(new OrderConfirmation($order));
        event(new OrderPlaced($order));

        return response()->json($order, 201);
    }
}

// ----------------------------------------------------------------------------
// AFTER: Thin controller + Service
// The controller handles HTTP. The service owns business logic.
// ----------------------------------------------------------------------------

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    // ✅ Controller: receive, delegate, respond
    public function store(OrderRequest $request): JsonResponse
    {
        $order = $this->orders->place(auth()->user(), $request->validated());

        return response()->json($order, 201);
    }
}

// ----------------------------------------------------------------------------
// The Service
// Can be called from a controller, an Artisan command, or a queue job.
// Has no dependency on HTTP — accepts and returns plain PHP objects.
// ----------------------------------------------------------------------------

class OrderService
{
    // ✅ Domain exception — not an HTTP exception
    // The caller (controller or exception handler) maps this to an HTTP response.
    public function place(User $user, array $data): Order
    {
        if ($user->orders()->count() >= 10) {
            throw new OrderLimitExceededException("User {$user->id} has reached the order limit.");
        }

        return DB::transaction(function () use ($user, $data) {
            $order = Order::create([
                'user_id' => $user->id,
                'total'   => collect($data['items'])->sum('price'),
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create($item);
            }

            // Dispatch a queued job rather than sending the email inline.
            // This ensures the email is only sent after the transaction commits,
            // and offloads the delivery to the queue worker.
            dispatch(new SendOrderConfirmation($order))->afterCommit();

            event(new OrderPlaced($order));

            return $order;
        });
    }
}

// ----------------------------------------------------------------------------
// Usage from an Artisan command — same service, no HTTP context needed
// ----------------------------------------------------------------------------

class ReprocessPendingOrdersCommand extends Command
{
    public function handle(OrderService $orders): void
    {
        $pendingData = [...]; // loaded from wherever

        foreach ($pendingData as $userId => $data) {
            $user = User::find($userId);

            try {
                $orders->place($user, $data);
            } catch (OrderLimitExceededException $e) {
                $this->warn("Skipped user {$userId}: {$e->getMessage()}");
            }
        }
    }
}
