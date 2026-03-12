<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

// ----------------------------------------------------------------------------
// SHARED RESPONSE HELPER
// Centralises the envelope shape for success and error responses.
// ----------------------------------------------------------------------------

class ApiResponse
{
    public static function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    public static function created(mixed $data): JsonResponse
    {
        return self::success($data, 201);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    public static function error(
        string $message,
        string $code,
        int    $status,
        array  $details = [],
    ): JsonResponse {
        $body = [
            'error' => [
                'message' => $message,
                'code'    => $code,
            ],
        ];

        if (!empty($details)) {
            $body['error']['details'] = $details;
        }

        return response()->json($body, $status);
    }
}

// ----------------------------------------------------------------------------
// API RESOURCE
// Controls what fields are serialized. Independent of the model's attributes.
// ----------------------------------------------------------------------------

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}

// ✅ Collection resource with custom pagination meta
class UserCollection extends ResourceCollection
{
    public $collects = UserResource::class;

    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'meta' => [
                'current_page' => $paginated['meta']['current_page'],
                'last_page'    => $paginated['meta']['last_page'],
                'per_page'     => $paginated['meta']['per_page'],
                'total'        => $paginated['meta']['total'],
            ],
        ];
    }
}

// ----------------------------------------------------------------------------
// CONTROLLER
// Uses ApiResponse and API Resources consistently across all endpoints.
// ----------------------------------------------------------------------------

class UserController extends Controller
{
    // GET /users — paginated list
    public function index(Request $request): JsonResponse
    {
        $users = User::paginate(15);

        return ApiResponse::success(new UserCollection($users));
    }

    // GET /users/{user} — single resource
    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(new UserResource($user));
    }

    // POST /users — create
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return ApiResponse::created(new UserResource($user));
    }

    // DELETE /users/{user} — delete
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return ApiResponse::noContent();
    }
}

// ----------------------------------------------------------------------------
// GLOBAL EXCEPTION HANDLER
// Maps application exceptions to standardised error responses.
// In Laravel 11+: app/Exceptions/Handler.php or bootstrap/app.php withExceptions()
// ----------------------------------------------------------------------------

// bootstrap/app.php (Laravel 11+):
//
// ->withExceptions(function (Exceptions $exceptions) {
//     $exceptions->render(function (ValidationException $e) {
//         return ApiResponse::error(
//             message: 'The given data was invalid.',
//             code:    'VALIDATION_ERROR',
//             status:  422,
//             details: $e->errors(),
//         );
//     });
//
//     $exceptions->render(function (ModelNotFoundException $e) {
//         return ApiResponse::error(
//             message: 'Resource not found.',
//             code:    'NOT_FOUND',
//             status:  404,
//         );
//     });
//
//     $exceptions->render(function (AuthorizationException $e) {
//         return ApiResponse::error(
//             message: 'This action is not authorized.',
//             code:    'FORBIDDEN',
//             status:  403,
//         );
//     });
// })

// ----------------------------------------------------------------------------
// EXAMPLE RESPONSE SHAPES
//
// GET /users/1  →  200
// {
//   "data": {
//     "id": 1,
//     "name": "Nicolas Taglienti",
//     "email": "nicolas@example.com",
//     "created_at": "2024-01-15T10:30:00.000Z"
//   }
// }
//
// GET /users  →  200
// {
//   "data": [ ... ],
//   "meta": { "current_page": 1, "last_page": 4, "per_page": 15, "total": 60 }
// }
//
// POST /users (invalid)  →  422
// {
//   "error": {
//     "message": "The given data was invalid.",
//     "code": "VALIDATION_ERROR",
//     "details": { "email": ["The email field is required."] }
//   }
// }
// ----------------------------------------------------------------------------
