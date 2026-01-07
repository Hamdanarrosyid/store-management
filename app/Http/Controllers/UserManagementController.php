<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Helper\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    private function actor(): User
    {
        return auth('api')->user();
    }

    private function allowedTargetRoles(): array
    {
        $actor = $this->actor();

        if ($actor->isSuperAdmin()) {
            return ['ADMIN', 'CASHIER']; // support legacy if needed
        }

        if ($actor->isAdmin()) {
            return ['CASHIER'];
        }

        return [];
    }

    private function scopedStoreId(Request $request): ?int
    {
        $actor = $this->actor();

        if ($actor->isSuperAdmin()) {
            return null; 
        }

        // For ADMIN, store.scope middleware must set this
        $scoped = $request->attributes->get('scoped_store_id');
        return $scoped ? (int) $scoped : null;
    }

    private function resolveRoleId(string $roleName): ?int
    {
        return Role::query()->where('name', $roleName)->value('id');
    }


    public function index(Request $request)
    {
        $allowed = $this->allowedTargetRoles();
        if (empty($allowed)) {
            return ApiResponse::error('Forbidden', 403);
        }

        $perPage = max(1, (int) $request->query('per_page', 10));
        $q = $request->query('q');
        $roleFilter = $request->query('role');    // super admin only (optional)
        $storeId = $request->query('store_id');   // super admin only (optional)

        $query = User::query()
            ->with(['role:id,name', 'store:id,name,level'])
            ->whereHas('role', fn($r) => $r->whereIn('name', $allowed));

        $scopedStoreId = $this->scopedStoreId($request);

        if (!is_null($scopedStoreId)) {
            // ADMIN scope
            $query->where('store_id', $scopedStoreId);
        } else {
            // SUPER_ADMIN optional store filter
            if (!is_null($storeId) && $storeId !== '') {
                $query->where('store_id', (int) $storeId);
            }
        }

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'ILIKE', "%{$q}%")
                    ->orWhere('email', 'ILIKE', "%{$q}%");
            });
        }

        if ($roleFilter) {
            // still restricted to allowed roles
            $query->whereHas('role', fn($r) => $r->where('name', $roleFilter));
        }

        $users = $query->orderByDesc('id')->paginate($perPage);

        return ApiResponse::pagination($users);
    }


    public function store(Request $request)
    {
        $actor = $this->actor();
        $allowed = $this->allowedTargetRoles();

        if (empty($allowed)) {
            return ApiResponse::error('Forbidden', 403);
        }


        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc,dns', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:72'],
            'is_active' => ['sometimes', 'boolean'],
        ];


        if ($actor->isSuperAdmin()) {
            $rules['store_id'] = ['required', 'integer', 'exists:stores,id'];
            $rules['role'] = ['required', 'string', Rule::in($allowed)];
        }

        $data = $request->validate($rules);

        if ($actor->isAdmin()) {
            // Admin cannot create non-cashier
            $data['role'] = 'CASHIER';
        }

        $storeId = $actor->isSuperAdmin()
            ? (int) $data['store_id']
            : (int) ($this->scopedStoreId($request) ?? 0);

        if (!$storeId) {
            return ApiResponse::error('Invalid store scope', 403);
        }

        // For ADMIN, enforce role is cashier only even if legacy allowed list includes variants
        if ($actor->isAdmin() && !in_array($data['role'], ['CASHIER'], true)) {
            return ApiResponse::error('You can only create cashier users.', 403);
        }

        $roleId = $this->resolveRoleId($data['role']);
        if (!$roleId) {
            return ApiResponse::error('Role not found.', 422, ['role' => ['The selected role is invalid.']]);
        }

        $user = User::create([
            'store_id' => $storeId,
            'role_id' => $roleId,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        $user->load(['role:id,name', 'store:id,name,level']);

        return ApiResponse::success($user, 'User created', 201);
    }


    public function show(Request $request, int $id)
    {
        $allowed = $this->allowedTargetRoles();
        if (empty($allowed)) {
            return ApiResponse::error('Forbidden', 403);
        }

        $query = User::query()
            ->with(['role:id,name', 'store:id,name,level'])
            ->whereHas('role', fn($r) => $r->whereIn('name', $allowed));

        $scopedStoreId = $this->scopedStoreId($request);
        if (!is_null($scopedStoreId)) {
            $query->where('store_id', $scopedStoreId);
        }

        $user = $query->findOrFail($id);

        return ApiResponse::success($user, 'User detail');
    }


    public function update(Request $request, int $id)
    {
        $actor = $this->actor();
        $allowed = $this->allowedTargetRoles();

        if (empty($allowed)) {
            return ApiResponse::error('Forbidden', 403);
        }

        $query = User::query()
            ->with(['role:id,name', 'store:id,name,level'])
            ->whereHas('role', fn($r) => $r->whereIn('name', $allowed));

        $scopedStoreId = $this->scopedStoreId($request);
        if (!is_null($scopedStoreId)) {
            $query->where('store_id', $scopedStoreId);
        }

        $user = $query->findOrFail($id);

        $rules = [
            'role' => ['sometimes', 'string', Rule::in($allowed)],
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'email:rfc,dns', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:6', 'max:72'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($actor->isSuperAdmin()) {
            $rules['store_id'] = ['sometimes', 'integer', 'exists:stores,id'];
        }

        $data = $request->validate($rules);

        if ($actor->isAdmin()) {
            // Admin cannot move store or change role to non-cashier
            if (isset($data['store_id'])) {
                return ApiResponse::error('You are not allowed to change store assignment.', 403);
            }
            if (isset($data['role']) && !in_array($data['role'], ['KASIR', 'kasir'], true)) {
                return ApiResponse::error('You can only manage cashier users.', 403);
            }
        }

        if (isset($data['role'])) {
            $roleId = $this->resolveRoleId($data['role']);
            if (!$roleId) {
                return ApiResponse::error('Role not found.', 422, ['role' => ['The selected role is invalid.']]);
            }
            $user->role_id = $roleId;
        }

        if ($actor->isSuperAdmin() && isset($data['store_id'])) {
            $user->store_id = (int) $data['store_id'];
        }

        if (isset($data['name'])) $user->name = $data['name'];
        if (isset($data['email'])) $user->email = $data['email'];
        if (array_key_exists('is_active', $data)) $user->is_active = (bool) $data['is_active'];

        if (isset($data['password'])) {
            $user->password = bcrypt($data['password']);
        }

        $user->save();
        $user->load(['role:id,name', 'store:id,name,level']);

        return ApiResponse::success($user, 'User updated');
    }


    public function destroy(Request $request, int $id)
    {
        $allowed = $this->allowedTargetRoles();
        if (empty($allowed)) {
            return ApiResponse::error('Forbidden', 403);
        }

        $query = User::query()
            ->whereHas('role', fn($r) => $r->whereIn('name', $allowed));

        $scopedStoreId = $this->scopedStoreId($request);
        if (!is_null($scopedStoreId)) {
            $query->where('store_id', $scopedStoreId);
        }

        $user = $query->findOrFail($id);

        if ($user->id === $this->actor()->id) {
            return ApiResponse::error('You cannot delete your own account.', 403);
        }

        $user->delete();

        return ApiResponse::success(null, 'User deleted');
    }
}
