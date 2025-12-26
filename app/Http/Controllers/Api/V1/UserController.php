<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    protected UserRepository $userRepo;

    public function __construct(UserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    /**
     * List all users with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        $users = $this->userRepo->paginateWithFilters(
            $request->per_page ?? 20,
            $request->search,
            $request->role
        );

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Get single user with roles
     */
    public function show($id): JsonResponse
    {
        $user = $this->userRepo->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user->load(['roles', 'permissions', 'createdTickets', 'assignedTickets']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    /**
     * Create new user
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userRepo->create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // Assign roles
        if ($request->has('roles') && is_array($request->roles)) {
            $user->syncRoles($request->roles);
        } else {
            // Assign default User role if no roles specified
            $user->assignRole('User');
        }

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => [
                'user' => $user->load('roles'),
            ],
        ], 201);
    }

    /**
     * Update user
     */
    public function update(UpdateUserRequest $request, $id): JsonResponse
    {
        $user = $this->userRepo->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $data = $request->only(['name', 'email', 'phone', 'is_active']);

        // Handle password update
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user = $this->userRepo->update($id, $data);

        // Update roles if provided
        if ($request->has('roles') && is_array($request->roles)) {
            $user->syncRoles($request->roles);
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => [
                'user' => $user->load('roles'),
            ],
        ]);
    }

    /**
     * Delete user
     */
    public function destroy($id): JsonResponse
    {
        $user = $this->userRepo->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your own account',
            ], 403);
        }

        $this->userRepo->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Assign roles to user
     */
    public function assignRoles(Request $request, $id): JsonResponse
    {
        $user = $this->userRepo->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Prevent removing all roles from yourself
        if ($user->id === auth()->id() && (empty($request->roles) || !is_array($request->roles))) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove all roles from your own account',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->syncRoles($request->roles);

        return response()->json([
            'success' => true,
            'message' => 'Roles assigned successfully',
            'data' => [
                'user' => $user->load('roles'),
            ],
        ]);
    }

    /**
     * Toggle user active status
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        $user = $this->userRepo->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Prevent deactivating yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deactivate your own account',
            ], 403);
        }

        $user = $this->userRepo->update($id, [
            'is_active' => !$user->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    /**
     * Get CSKH staff list (for assigning tickets)
     */
    public function cskhList(): JsonResponse
    {
        $cskhUsers = $this->userRepo->getCskhForSelect();

        return response()->json([
            'success' => true,
            'data' => $cskhUsers,
        ]);
    }

    /**
     * Get user statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->userRepo->getStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
