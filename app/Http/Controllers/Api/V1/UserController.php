<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    protected UserRepository $userRepo;

    public function __construct(UserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
        $this->middleware('role:Admin');
    }

    /**
     * List all users
     */
    public function index(Request $request): JsonResponse
    {
        $users = $this->userRepo->paginateWithFilters(
            20,
            $request->search,
            $request->role
        );

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Get single user
     */
    public function show($id): JsonResponse
    {
        $user = $this->userRepo->with(['roles', 'permissions', 'createdTickets', 'assignedTickets'], $id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    /**
     * Update user
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $this->userRepo->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'sometimes|nullable|string|max:20',
            'password' => 'sometimes|nullable|string|min:6|confirmed',
            'role' => 'sometimes|exists:roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only(['name', 'email', 'phone']);

        if ($request->filled('password')) {
            $this->userRepo->updatePassword($id, Hash::make($request->password));
        }

        $user = $this->userRepo->updateProfile($id, $data);

        // Update role if specified
        if ($request->has('role')) {
            $user = $this->userRepo->updateRole($id, $request->role);
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => [
                'user' => $user->load('roles', 'permissions'),
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
            ], 422);
        }

        $this->userRepo->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
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
