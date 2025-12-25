<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\CategoryRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    protected CategoryRepository $categoryRepo;

    public function __construct(CategoryRepository $categoryRepo)
    {
        $this->categoryRepo = $categoryRepo;
    }

    /**
     * List all active categories
     */
    public function index(): JsonResponse
    {
        $categories = $this->categoryRepo->getTree();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Create new category (Admin only)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = $this->categoryRepo->createCategory([
            'name' => $request->name,
            'description' => $request->description,
            'parent_id' => $request->parent_id,
            'is_active' => $request->get('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => [
                'category' => $category,
            ],
        ], 201);
    }

    /**
     * Update category (Admin only)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $category = $this->categoryRepo->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'parent_id' => 'sometimes|nullable|exists:categories,id',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only(['name', 'description', 'parent_id', 'is_active']);
        $category = $this->categoryRepo->updateCategory($id, $data);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => [
                'category' => $category,
            ],
        ]);
    }

    /**
     * Delete category (Admin only)
     */
    public function destroy($id): JsonResponse
    {
        $category = $this->categoryRepo->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        // Check if category has tickets
        if ($this->categoryRepo->hasTickets($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with tickets',
            ], 422);
        }

        $this->categoryRepo->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }
}
