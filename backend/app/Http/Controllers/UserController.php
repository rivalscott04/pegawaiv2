<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WilayahUnit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) ($request->query('per_page', 15));
        $search = trim($request->query('search', ''));

        $query = User::with(['role', 'permissions', 'wilayahUnit']);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        $paginated = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginated,
        ]);
    }

    public function roles()
    {
        $preferred = ['superadmin' => 1, 'admin_kanwil' => 2, 'admin_kab' => 3, 'user' => 4];
        $roles = Role::query()
            ->select('id', 'name')
            ->get()
            ->sortBy(fn ($r) => [$preferred[$r->name] ?? 100, $r->name])
            ->values();

        return response()->json(['success' => true, 'data' => $roles]);
    }

    public function permissions()
    {
        $rows = Permission::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'key', 'label', 'group', 'sort_order']);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function wilayahUnits()
    {
        $rows = WilayahUnit::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'kind']);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role_id' => ['required', Rule::exists('roles', 'id')],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => [Rule::exists('permissions', 'id')],
            'wilayah_unit_id' => ['nullable', Rule::exists('wilayah_units', 'id')],
        ]);

        $permissionIds = $data['permission_ids'] ?? [];
        unset($data['permission_ids']);

        $this->validatePegawaiLingkupForPermissions(
            $permissionIds,
            (int) $data['role_id'],
            $data['wilayah_unit_id'] ?? null
        );

        $user = User::create($data);
        $user->permissions()->sync($permissionIds);
        $user->forgetPermissionCache();
        $user->load(['role', 'permissions', 'wilayahUnit']);

        return response()->json(['success' => true, 'data' => $user], 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role_id' => ['sometimes', 'required', Rule::exists('roles', 'id')],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => [Rule::exists('permissions', 'id')],
            'wilayah_unit_id' => ['nullable', Rule::exists('wilayah_units', 'id')],
        ]);

        if (array_key_exists('password', $data) && ($data['password'] === null || $data['password'] === '')) {
            unset($data['password']);
        }

        $permissionIds = array_key_exists('permission_ids', $data) ? $data['permission_ids'] : null;
        unset($data['permission_ids']);

        $effectiveRoleId = isset($data['role_id']) ? (int) $data['role_id'] : (int) $user->role_id;
        $effectiveWilayahId = array_key_exists('wilayah_unit_id', $data)
            ? $data['wilayah_unit_id']
            : $user->wilayah_unit_id;
        $effectivePermIds = $permissionIds !== null
            ? array_map('intval', $permissionIds)
            : $user->permissions()->pluck('id')->all();

        $this->validatePegawaiLingkupForPermissions($effectivePermIds, $effectiveRoleId, $effectiveWilayahId);

        $user->fill($data);
        $user->save();

        if ($permissionIds !== null) {
            $user->permissions()->sync($permissionIds);
        }
        $user->forgetPermissionCache();
        $user->load(['role', 'permissions', 'wilayahUnit']);

        return response()->json(['success' => true, 'data' => $user]);
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @param  list<int|string>  $permissionIds
     */
    private function validatePegawaiLingkupForPermissions(array $permissionIds, int $roleId, ?int $wilayahUnitId): void
    {
        $role = Role::query()->find($roleId);
        if ($role && ($role->name === 'superadmin' || $role->name === 'admin')) {
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $permissionIds)));
        $keys = Permission::query()->whereIn('id', $ids)->pluck('key');

        $hasKab = $keys->contains('pegawai.lingkup.kabupaten');
        $hasKanwil = $keys->contains('pegawai.lingkup.kanwil');

        if ($hasKab && $hasKanwil) {
            throw ValidationException::withMessages([
                'permission_ids' => ['Pilih hanya satu lingkup pegawai: kabupaten/kota atau seluruh Kanwil.'],
            ]);
        }

        $pegawaiActions = $keys->filter(fn (string $k) => str_starts_with($k, 'pegawai.') && ! str_starts_with($k, 'pegawai.lingkup.'));

        if ($pegawaiActions->isNotEmpty() && ! $hasKab && ! $hasKanwil) {
            throw ValidationException::withMessages([
                'permission_ids' => ['Centang salah satu: «Lingkup pegawai: satu kabupaten/kota» atau «Lingkup pegawai: seluruh wilayah Kanwil» bersama izin pegawai lainnya.'],
            ]);
        }

        if ($hasKab && $wilayahUnitId === null) {
            throw ValidationException::withMessages([
                'wilayah_unit_id' => ['Lingkup kabupaten/kota wajib dipasangkan dengan pemilihan unit wilayah pada tab data akun.'],
            ]);
        }
    }
}


