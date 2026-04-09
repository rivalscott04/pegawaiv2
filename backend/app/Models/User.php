<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'wilayah_unit_id',
    ];

    /** @var list<string>|null */
    protected ?array $permissionKeyCache = null;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function wilayahUnit(): BelongsTo
    {
        return $this->belongsTo(WilayahUnit::class, 'wilayah_unit_id');
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function forgetPermissionCache(): void
    {
        $this->permissionKeyCache = null;
    }

    public function isSuperAdmin(): bool
    {
        $this->loadMissing('role');
        $name = $this->role?->name;

        return $name === 'superadmin' || $name === 'admin';
    }

    /**
     * Admin Kanwil: lihat semua kab/kota + Kanwil; ubah hanya baris yang termasuk induk Kanwil.
     */
    public function isKanwilAdmin(): bool
    {
        $this->loadMissing('role');

        return $this->role?->name === 'admin_kanwil';
    }

    /**
     * Admin kabupaten/kota (dulu `operator` di seed lama).
     */
    public function isAdminKab(): bool
    {
        $this->loadMissing('role');
        $name = $this->role?->name;

        return $name === 'admin_kab' || $name === 'operator';
    }

    /**
     * true = data pegawai/heatmap dibatasi ke satu unit (source_unit_slug / canonical induk).
     * Izin pegawai.lingkup.* menentukan apakah batas mengikuti kabupaten atau akses penuh kanwil.
     */
    public function shouldRestrictToWilayah(): bool
    {
        if ($this->isSuperAdmin()) {
            return false;
        }

        if ($this->hasPermission('pegawai.lingkup.kanwil')) {
            return false;
        }

        if ($this->hasPermission('pegawai.lingkup.kabupaten')) {
            return true;
        }

        if ($this->isKanwilAdmin()) {
            return false;
        }

        return $this->wilayah_unit_id !== null;
    }

    /**
     * Akses baris pegawai induk Kanwil untuk edit (setara admin_kanwil). Superadmin tidak memakai flag ini.
     */
    public function hasPegawaiKanwilLingkup(): bool
    {
        return $this->hasPermission('pegawai.lingkup.kanwil') || $this->isKanwilAdmin();
    }

    /**
     * @return list<string>
     */
    public function permissionKeySet(): array
    {
        if ($this->permissionKeyCache === null) {
            $this->permissionKeyCache = $this->permissions()->pluck('key')->all();
        }

        return $this->permissionKeyCache;
    }

    public function hasPermission(string $key): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($key, $this->permissionKeySet(), true);
    }

    /**
     * Hak akses untuk dikirim ke client (admin = wildcard).
     *
     * @return list<string>
     */
    public function permissionKeysForClient(): array
    {
        if ($this->isSuperAdmin()) {
            return ['*'];
        }

        return array_values(array_unique($this->permissionKeySet()));
    }
}
