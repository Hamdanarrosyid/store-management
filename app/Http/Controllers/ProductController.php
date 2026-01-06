<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Helper\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, (int) $request->query('per_page', 10));
        $q = $request->query('q');
        $isActive = $request->query('is_active'); 
        $storeId = $request->query('store_id');

        $actor = auth('api')->user();
        if (!$actor) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        $query = Product::query();

        $scopedStoreId = $request->attributes->get('scoped_store_id');
        if (!$actor->isSuperAdmin()) {
            if (!$scopedStoreId) {
                return ApiResponse::error('Invalid store scope', 403);
            }
            $query->where('store_id', (int) $scopedStoreId);
        } else {
            if (!is_null($storeId) && $storeId !== '') {
                $query->where('store_id', (int) $storeId);
            }
        }

        // Search
        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'ILIKE', "%{$q}%")
                    ->orWhere('sku', 'ILIKE', "%{$q}%");
            });
        }

        // Filter active
        if (!is_null($isActive) && $isActive !== '') {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        $products = $query->orderByDesc('id')->paginate($perPage);

        return ApiResponse::pagination($products, 'Product list');
    }

    public function store(Request $request)
    {
        $actor = auth('api')->user();
        if (!$actor) return ApiResponse::error('Unauthenticated', 401);

        if (!($actor->isSuperAdmin() || $actor->isAdmin())) {
            return ApiResponse::error('Forbidden', 403);
        }

        $rules = [
            'sku' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($actor->isSuperAdmin()) {
            $rules['store_id'] = ['required', 'integer', 'exists:stores,id'];
        }

        $data = $request->validate($rules);

        $storeId = $actor->isSuperAdmin()
            ? (int) $data['store_id']
            : (int) ($request->attributes->get('scoped_store_id') ?? 0);

        if (!$storeId) {
            return ApiResponse::error('Invalid store scope', 403);
        }

        // Ensure SKU unique per store
        $request->validate([
            'sku' => [
                'required',
                'string',
                'max:80',
                Rule::unique('products', 'sku')->where(fn($q) => $q->where('store_id', $storeId)),
            ],
        ]);

        $product = DB::transaction(function () use ($data, $storeId) {
            return Product::create([
                'store_id' => $storeId,
                'sku' => $data['sku'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'is_active' => $data['is_active'] ?? true,
            ]);
        });

        return ApiResponse::success($product, 'Product created', 201);
    }

    
    public function show(Request $request, int $id)
    {
        $actor = auth('api')->user();
        if (!$actor) return ApiResponse::error('Unauthenticated', 401);

        $query = Product::query();

        if (!$actor->isSuperAdmin()) {
            $scopedStoreId = (int) ($request->attributes->get('scoped_store_id') ?? 0);
            if (!$scopedStoreId) return ApiResponse::error('Invalid store scope', 403);

            $query->where('store_id', $scopedStoreId);
        }

        $product = $query->findOrFail($id);

        return ApiResponse::success($product, 'Product detail');
    }


    public function update(Request $request, int $id)
    {
        $actor = auth('api')->user();
        if (!$actor) return ApiResponse::error('Unauthenticated', 401);

        if (!($actor->isSuperAdmin() || $actor->isAdmin())) {
            return ApiResponse::error('Forbidden', 403);
        }

        $query = Product::query();
        $scopedStoreId = null;

        if (!$actor->isSuperAdmin()) {
            $scopedStoreId = (int) ($request->attributes->get('scoped_store_id') ?? 0);
            if (!$scopedStoreId) return ApiResponse::error('Invalid store scope', 403);
            $query->where('store_id', $scopedStoreId);
        }

        $product = $query->findOrFail($id);

        $rules = [
            'sku' => ['sometimes', 'string', 'max:80'],
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($actor->isSuperAdmin()) {
            $rules['store_id'] = ['sometimes', 'integer', 'exists:stores,id'];
        }

        $data = $request->validate($rules);

        $targetStoreId = $actor->isSuperAdmin()
            ? (int) ($data['store_id'] ?? $product->store_id)
            : (int) $scopedStoreId;

        if (isset($data['sku'])) {
            $request->validate([
                'sku' => [
                    'string',
                    'max:80',
                    Rule::unique('products', 'sku')
                        ->ignore($product->id)
                        ->where(fn($q) => $q->where('store_id', $targetStoreId)),
                ],
            ]);
        }

        DB::transaction(function () use ($actor, $product, $data) {
            if ($actor->isSuperAdmin() && isset($data['store_id'])) {
                $product->store_id = (int) $data['store_id'];
            }
            if (isset($data['sku'])) $product->sku = $data['sku'];
            if (isset($data['name'])) $product->name = $data['name'];
            if (array_key_exists('description', $data)) $product->description = $data['description'];
            if (isset($data['price'])) $product->price = $data['price'];
            if (array_key_exists('is_active', $data)) $product->is_active = (bool) $data['is_active'];

            $product->save();
        });

        return ApiResponse::success($product->fresh(), 'Product updated');
    }


    public function destroy(Request $request, int $id)
    {
        $actor = auth('api')->user();
        if (!$actor) return ApiResponse::error('Unauthenticated', 401);

        if (!($actor->isSuperAdmin() || $actor->isAdmin())) {
            return ApiResponse::error('Forbidden', 403);
        }

        $query = Product::query();

        if (!$actor->isSuperAdmin()) {
            $scopedStoreId = (int) ($request->attributes->get('scoped_store_id') ?? 0);
            if (!$scopedStoreId) return ApiResponse::error('Invalid store scope', 403);
            $query->where('store_id', $scopedStoreId);
        }

        $product = $query->findOrFail($id);
        $product->delete(); 

        return ApiResponse::success(null, 'Product deleted');
    }
}
