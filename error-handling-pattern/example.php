<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

// ----------------------------------------------------------------------------
// STEP 1: Exception hierarchy
// Domain exceptions are distinct from infrastructure or framework exceptions.
// ----------------------------------------------------------------------------

// Base class for all domain-level errors in this application
abstract class DomainException extends \RuntimeException {}

// Business rule violation — maps to 422
final class OrderLimitExceededException extends DomainException
{
    public function __construct(int $userId)
    {
        parent::__construct("User {$userId} has reached the maximum order limit.");
    }
}

// Resource conflict — maps to 409
final class DuplicateOrderException extends DomainException
{
    public function __construct(string $reference)
    {
        parent::__construct("Order with reference '{$reference}' already exists.");
    }
}

// Authorisation at the domain level — maps to 403
final class InsufficientPermissionsException extends DomainException
{
    public function __construct(string $action)
    {
        parent::__construct("You do not have permission to perform: {$action}");
    }
}

// ----------------------------------------------------------------------------
// STEP 2: Global exception handler
// One place. All mappings. Uniform response shape.
// ----------------------------------------------------------------------------

// bootstrap/app.php (Laravel 11+):
//
// ->withExceptions(function (Exceptions $exceptions) {
//
//     // --- Domain exceptions ---
//
//     $exceptions->render(function (OrderLimitExceededException $e, Request $request) {
//         return ApiResponse::error($e->getMessage(), 'ORDER_LIMIT_EXCEEDED', 422);
//     });
//
//     $exceptions->render(function (DuplicateOrderException $e, Request $request) {
//         return ApiResponse::error($e->getMessage(), 'DUPLICATE_ORDER', 409);
//     });
//
//     $exceptions->render(function (InsufficientPermissionsException $e, Request $request) {
//         return ApiResponse::error($e->getMessage(), 'FORBIDDEN', 403);
//     });
//
//     // --- Framework exceptions ---
//
//     $exceptions->render(function (ValidationException $e, Request $request) {
//         return ApiResponse::error(
//             message: 'The given data was invalid.',
//             code:    'VALIDATION_ERROR',
//             status:  422,
//             details: $e->errors(),
//         );
//     });
//
//     $exceptions->render(function (ModelNotFoundException $e, Request $request) {
//         return ApiResponse::error('Resource not found.', 'NOT_FOUND', 404);
//     });
//
//     $exceptions->render(function (AuthorizationException $e, Request $request) {
//         return ApiResponse::error('This action is not authorized.', 'FORBIDDEN', 403);
//     });
//
//     // --- Catch-all for unhandled exceptions ---
//     // Logs the full exception internally; returns a safe generic message externally.
//
//     $exceptions->render(function (\Throwable $e, Request $request) {
//         if ($request->expectsJson()) {
//             Log::error('Unhandled exception', [
//                 'message'    => $e->getMessage(),
//                 'url'        => $request->fullUrl(),
//                 'user_id'    => $request->user()?->id,
//                 'exception'  => $e,
//             ]);
//
//             return ApiResponse::error(
//                 message: 'An unexpected error occurred.',
//                 code:    'INTERNAL_ERROR',
//                 status:  500,
//             );
//         }
//     });
//
//     // --- Reporting (logging) rules ---
//
//     // ValidationException is expected behaviour — do not log it as an error
//     $exceptions->dontReport(ValidationException::class);
//
//     // Domain exceptions are handled — log at info level for audit, not as errors
//     $exceptions->report(function (DomainException $e) {
//         Log::info('Domain exception raised', ['message' => $e->getMessage()]);
//         return false; // prevent default logging
//     });
// })

// ----------------------------------------------------------------------------
// STEP 3: Controller — no try/catch required
// Exceptions bubble up to the global handler.
// ----------------------------------------------------------------------------

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    // ✅ Clean. All error handling is in the global handler.
    public function store(OrderRequest $request): JsonResponse
    {
        $order = $this->orders->place(auth()->user(), $request->validated());

        return ApiResponse::created(new OrderResource($order));
    }
}

// ----------------------------------------------------------------------------
// STEP 4: Service throws domain exceptions
// No HTTP knowledge. No response objects.
// ----------------------------------------------------------------------------

class OrderService
{
    public function place(User $user, array $data): Order
    {
        if ($user->orders()->count() >= 10) {
            throw new OrderLimitExceededException($user->id);
        }

        if (Order::where('reference', $data['reference'])->exists()) {
            throw new DuplicateOrderException($data['reference']);
        }

        return DB::transaction(fn () => $this->createOrder($user, $data));
    }

    private function createOrder(User $user, array $data): Order
    {
        $order = Order::create([
            'user_id'   => $user->id,
            'reference' => $data['reference'],
            'total'     => collect($data['items'])->sum('price'),
        ]);

        foreach ($data['items'] as $item) {
            $order->items()->create($item);
        }

        dispatch(new SendOrderConfirmation($order))->afterCommit();

        return $order;
    }
}

// ----------------------------------------------------------------------------
// EXAMPLE RESPONSES
//
// POST /orders (limit exceeded)  →  422
// {
//   "error": { "message": "User 42 has reached the maximum order limit.", "code": "ORDER_LIMIT_EXCEEDED" }
// }
//
// POST /orders (duplicate reference)  →  409
// {
//   "error": { "message": "Order with reference 'REF-001' already exists.", "code": "DUPLICATE_ORDER" }
// }
//
// POST /orders (validation failure)  →  422
// {
//   "error": {
//     "message": "The given data was invalid.",
//     "code": "VALIDATION_ERROR",
//     "details": { "reference": ["The reference field is required."] }
//   }
// }
//
// POST /orders (unhandled server error)  →  500
// {
//   "error": { "message": "An unexpected error occurred.", "code": "INTERNAL_ERROR" }
// }
// (Full exception logged internally with user_id and request URL)
// ----------------------------------------------------------------------------
