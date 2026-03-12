<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;

// ----------------------------------------------------------------------------
// BASIC DTO — PHP 8.1 readonly properties
// Immutable by default. No setters needed.
// ----------------------------------------------------------------------------

final class RegisterUserData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
    ) {}

    // Named constructor: constructs from a validated Form Request
    // Keeps construction logic in one place, not scattered across controllers
    public static function fromRequest(RegisterRequest $request): self
    {
        return new self(
            name:     $request->validated('name'),
            email:    $request->validated('email'),
            password: $request->validated('password'),
        );
    }
}

// ----------------------------------------------------------------------------
// PHP 8.2 READONLY CLASS
// All properties are implicitly readonly — less boilerplate for simple DTOs
// ----------------------------------------------------------------------------

readonly class UpdateProfileData
{
    public function __construct(
        public string  $name,
        public string  $email,
        public ?string $bio,
    ) {}

    public static function fromRequest(UpdateProfileRequest $request): self
    {
        return new self(
            name:  $request->validated('name'),
            email: $request->validated('email'),
            bio:   $request->validated('bio'),
        );
    }
}

// ----------------------------------------------------------------------------
// CONTROLLER — constructs DTO, delegates to service
// The controller has no knowledge of the DTO's internal structure
// ----------------------------------------------------------------------------

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function store(RegisterRequest $request): JsonResponse
    {
        // ✅ DTO built at the HTTP boundary
        $user = $this->users->register(RegisterUserData::fromRequest($request));

        return response()->json($user, 201);
    }

    public function update(UpdateProfileRequest $request, User $user): JsonResponse
    {
        $updated = $this->users->updateProfile($user, UpdateProfileData::fromRequest($request));

        return response()->json($updated);
    }
}

// ----------------------------------------------------------------------------
// SERVICE — receives DTO, not raw array or request
// Fully decoupled from HTTP. Can be called from CLI or queue jobs.
// ----------------------------------------------------------------------------

class UserService
{
    // ✅ Explicit contract: the caller must provide a RegisterUserData
    public function register(RegisterUserData $data): User
    {
        return User::create([
            'name'     => $data->name,
            'email'    => $data->email,
            'password' => bcrypt($data->password),
        ]);
    }

    public function updateProfile(User $user, UpdateProfileData $data): User
    {
        $user->update([
            'name'  => $data->name,
            'email' => $data->email,
            'bio'   => $data->bio,
        ]);

        return $user->fresh();
    }
}

// ----------------------------------------------------------------------------
// USAGE FROM ARTISAN COMMAND
// Same service, same DTO — no HTTP context required
// ----------------------------------------------------------------------------

class CreateAdminCommand extends Command
{
    public function handle(UserService $users): void
    {
        $data = new RegisterUserData(
            name:     'Admin',
            email:    'admin@example.com',
            password: 'secret',
        );

        $users->register($data);

        $this->info('Admin user created.');
    }
}
