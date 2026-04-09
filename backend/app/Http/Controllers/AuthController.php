<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
	public function login(Request $request)
	{
		$credentials = $request->validate([
			'identifier' => ['required','string'],
			'password' => ['required'],
		]);

		$user = $this->findUserByIdentifier($credentials['identifier']);

		if (!$user || !$this->passwordMatches($credentials['password'], $user->password)) {
			throw ValidationException::withMessages([
				'identifier' => ['The provided credentials are incorrect.'],
			]);
		}

		// create personal access token
		$token = $user->createToken('web')->plainTextToken;

		return response()->json([
			'success' => true,
			'data' => [
				'access_token' => $token,
				'token_type' => 'Bearer',
				'user' => $this->userAuthPayload($user),
			],
		]);
	}

	public function me(Request $request)
	{
		$user = $request->user();
		return response()->json([
			'success' => true,
			'data' => $this->userAuthPayload($user),
		]);
	}

	public function logout(Request $request)
	{
		$request->user()->currentAccessToken()?->delete();
		return response()->json(['success' => true]);
	}

	public function refresh(Request $request)
	{
		$user = $request->user();
		
		// Delete current token
		$request->user()->currentAccessToken()?->delete();
		
		// Create new token
		$token = $user->createToken('web')->plainTextToken;
		
		return response()->json([
			'success' => true,
			'data' => [
				'access_token' => $token,
				'token_type' => 'Bearer',
				'user' => $this->userAuthPayload($user),
			],
		]);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function userAuthPayload(User $user): array
	{
		$user->loadMissing(['role', 'permissions', 'wilayahUnit']);
		$user->forgetPermissionCache();

		return [
			'id' => $user->id,
			'name' => $user->name,
			'email' => $user->email ?? null,
			'role' => $this->resolveRoleName($user),
			'permissions' => $user->permissionKeysForClient(),
			'wilayah_unit' => $user->wilayahUnit ? [
				'id' => $user->wilayahUnit->id,
				'slug' => $user->wilayahUnit->slug,
				'name' => $user->wilayahUnit->name,
				'kind' => $user->wilayahUnit->kind,
			] : null,
		];
	}

	private function findUserByIdentifier(string $identifier): ?User
	{
		$normalized = strtolower(trim($identifier));
		$hasEmail = Schema::hasColumn('users', 'email');
		$hasUsername = Schema::hasColumn('users', 'username');
		$hasName = Schema::hasColumn('users', 'name');

		return User::query()
			->where(function (Builder $query) use ($normalized, $hasEmail, $hasUsername, $hasName) {
				$hasCondition = false;

				if ($hasEmail) {
					$query->whereRaw('LOWER(email) = ?', [$normalized]);
					$hasCondition = true;
				}

				if ($hasUsername) {
					if ($hasCondition) {
						$query->orWhereRaw('LOWER(username) = ?', [$normalized]);
					} else {
						$query->whereRaw('LOWER(username) = ?', [$normalized]);
					}
					$hasCondition = true;
				}

				if ($hasName) {
					if ($hasCondition) {
						$query->orWhereRaw('LOWER(name) = ?', [$normalized]);
					} else {
						$query->whereRaw('LOWER(name) = ?', [$normalized]);
					}
					$hasCondition = true;
				}

				if (!$hasCondition) {
					$query->whereRaw('1 = 0');
				}
			})
			->first();
	}

	private function resolveRoleName(User $user): ?string
	{
		if (Schema::hasColumn('users', 'role')) {
			return $user->role;
		}

		if (!Schema::hasColumn('users', 'role_id')) {
			return null;
		}

		try {
			$user->loadMissing('role');
			return $user->role?->name;
		} catch (\Throwable) {
			return null;
		}
	}

	private function passwordMatches(string $plain, ?string $hashed): bool
	{
		if (!$hashed) {
			return false;
		}

		try {
			return Hash::check($plain, $hashed);
		} catch (\RuntimeException) {
			// Support legacy hashes that fail Laravel algorithm verification.
			return password_verify($plain, $hashed);
		}
	}
}
