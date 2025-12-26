<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Models\User;

class RoleController extends Controller
{
    /**
     * List all roles
     */
    public function index(): JsonResponse
    {
        $roles = Role::orderBy('name')->get()->map(function ($role) {
            $role->users_count = User::role($role->name)->count();
            return $role;
        });

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Get single role details
     */
    public function show(string $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        // Get users with this role
        $users = User::role($role->name)->get(['id', 'name', 'email', 'created_at']);
        $role->users = $users;
        $role->users_count = $users->count();

        return response()->json([
            'success' => true,
            'data' => $role,
        ]);
    }

    /**
     * Create new role
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role,
        ], 201);
    }

    /**
     * Update role
     */
    public function update(UpdateRoleRequest $request, string $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        // Prevent modifying system roles
        if (in_array($role->name, ['Admin', 'CSKH', 'User'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify system roles',
            ], 403);
        }

        $role->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role->fresh(),
        ]);
    }

    /**
     * Delete role
     */
    public function destroy(string $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        // Prevent deleting system roles
        if (in_array($role->name, ['Admin', 'CSKH', 'User'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete system roles',
            ], 403);
        }

        // Check if role has users
        $userCount = User::role($role->name)->count();
        if ($userCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete role with assigned users',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Get roles for select dropdown
     */
    public function list(): JsonResponse
    {
        $roles = Role::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }
}
