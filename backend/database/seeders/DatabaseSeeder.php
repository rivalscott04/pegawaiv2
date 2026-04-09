<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
	public function run(): void
	{
		$this->call(PermissionSeeder::class);
		$this->call(WilayahUnitSeeder::class);

		$roles = collect(['superadmin', 'admin_kanwil', 'admin_kab', 'user'])->mapWithKeys(function ($name) {
			return [$name => Role::firstOrCreate(['name' => $name])];
		});

		$permissionIds = fn (array $keys) => Permission::query()->whereIn('key', $keys)->pluck('id')->all();

		User::firstOrCreate(
			['email' => 'admin@example.com'],
			['name' => 'Rival', 'password' => Hash::make('password'), 'role_id' => $roles['superadmin']->id]
		);

		$kanwilAdmin = User::firstOrCreate(
			['email' => 'kanwil@example.com'],
			['name' => 'Admin Kanwil', 'password' => Hash::make('kanwil123#'), 'role_id' => $roles['admin_kanwil']->id]
		);
		$kanwilAdmin->permissions()->sync($permissionIds([
			'pegawai.lingkup.kanwil',
			'pegawai.view',
			'pegawai.edit',
			'pegawai.export',
		]));

		$operator = User::firstOrCreate(
			['email' => 'operator@example.com'],
			['name' => 'gekrama', 'password' => Hash::make('rama321#'), 'role_id' => $roles['admin_kab']->id]
		);
		$operator->permissions()->sync($permissionIds([
			'pegawai.lingkup.kanwil',
			'pegawai.view',
			'pegawai.edit',
			'pegawai.export',
		]));

		$endUser = User::firstOrCreate(
			['email' => 'user@example.com'],
			['name' => 'pegawai', 'password' => Hash::make('pegawai*123'), 'role_id' => $roles['user']->id]
		);
		$endUser->permissions()->sync($permissionIds([
			'pegawai.lingkup.kanwil',
			'pegawai.view',
		]));

		$this->call(HrmsMysqlDumpSeeder::class);
		$this->call(CoordinateSeeder::class);
	}
}
