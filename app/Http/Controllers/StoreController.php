<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Helper\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) ($request->query('per_page', 10));
        $q = $request->query('q');
        $level = $request->query('level');
        $parentStoreId = $request->query('parent_store_id');

        $query = Store::query();

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'ILIKE', "%{$q}%")
                    ->orWhere('code', 'ILIKE', "%{$q}%");
            });
        }

        if ($level) {
            $query->where('level', $level);
        }

        if (!is_null($parentStoreId) && $parentStoreId !== '') {
            $query->where('parent_store_id', (int) $parentStoreId);
        }

        $stores = $query
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::pagination($stores);
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:stores,code'],
            'name' => ['required', 'string', 'max:150'],
            'level' => ['required', 'string', Rule::in(['PUSAT', 'CABANG', 'RETAIL'])],
            'parent_store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'address' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->validateHierarchy($data['level'], $data['parent_store_id'] ?? null);

        $store = DB::transaction(function () use ($data) {
            return Store::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'level' => $data['level'],
                'parent_store_id' => $data['parent_store_id'] ?? null,
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });

        return ApiResponse::success($store, 'Store created', 201);
    }

    /**
     * GET /api/v1/stores/{id}
     */
    public function show(int $id)
    {
        $store = Store::query()->findOrFail($id);

        return ApiResponse::success($store, 'Store detail');
    }


    public function update(Request $request, int $id)
    {
        $store = Store::query()->findOrFail($id);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('stores', 'code')->ignore($store->id)],
            'name' => ['sometimes', 'string', 'max:150'],
            'level' => ['sometimes', 'string', Rule::in(['PUSAT', 'CABANG', 'RETAIL'])],
            'parent_store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'address' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $newLevel = $data['level'] ?? $store->level;
        $newParentId = array_key_exists('parent_store_id', $data) ? $data['parent_store_id'] : $store->parent_store_id;

        $this->validateHierarchy($newLevel, $newParentId, $store->id);

        DB::transaction(function () use ($store, $data) {
            $store->fill($data);
            $store->save();
        });

        return ApiResponse::success($store->fresh(), 'Store updated');
    }


    public function destroy(int $id)
    {
        $store = Store::query()->findOrFail($id);

        $store->delete();

        return ApiResponse::success(null, 'Store soft deleted', 204);
    }

    /**
     * Validasi hirarki level toko.
     * - PUSAT: parent_store_id wajib NULL
     * - CABANG: parent_store_id wajib menunjuk PUSAT
     * - RETAIL: parent_store_id wajib menunjuk CABANG
     */
    private function validateHierarchy(string $level, ?int $parentStoreId, ?int $selfId = null): void
    {
        if ($level === 'PUSAT') {
            if (!is_null($parentStoreId)) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Invalid hierarchy',
                    'errors' => ['parent_store_id' => ['Toko PUSAT tidak boleh memiliki parent_store_id.']],
                ], 422));
            }
            return;
        }

        if (is_null($parentStoreId)) {
            abort(response()->json([
                'success' => false,
                'message' => 'Invalid hierarchy',
                'errors' => ['parent_store_id' => ['parent_store_id wajib diisi untuk level CABANG/RETAIL.']],
            ], 422));
        }

        if (!is_null($selfId) && $parentStoreId === $selfId) {
            abort(response()->json([
                'success' => false,
                'message' => 'Invalid hierarchy',
                'errors' => ['parent_store_id' => ['parent_store_id tidak boleh menunjuk dirinya sendiri.']],
            ], 422));
        }

        $parent = Store::query()->select(['id', 'level'])->findOrFail($parentStoreId);

        if ($level === 'CABANG' && $parent->level !== 'PUSAT') {
            abort(response()->json([
                'success' => false,
                'message' => 'Invalid hierarchy',
                'errors' => ['parent_store_id' => ['CABANG harus memiliki parent dengan level PUSAT.']],
            ], 422));
        }

        if ($level === 'RETAIL' && $parent->level !== 'CABANG') {
            abort(response()->json([
                'success' => false,
                'message' => 'Invalid hierarchy',
                'errors' => ['parent_store_id' => ['RETAIL harus memiliki parent dengan level CABANG.']],
            ], 422));
        }
    }
}
