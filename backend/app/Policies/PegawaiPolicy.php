<?php

namespace App\Policies;

use App\Models\Pegawai;
use App\Models\User;
use App\Support\PegawaiWilayah;

class PegawaiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pegawai.view');
    }

    public function view(User $user, Pegawai $pegawai): bool
    {
        return $user->hasPermission('pegawai.view');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('pegawai.export');
    }

    /**
     * Reserved untuk fitur tulis pegawai v2 (belum ada route).
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function update(User $user, Pegawai $pegawai): bool
    {
        if (!$user->hasPermission('pegawai.edit') && !$user->hasPermission('pegawai.edit_all')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $slug = trim((string) ($pegawai->source_unit_slug ?? ''));

        if ($user->hasPegawaiKanwilLingkup()) {
            return $slug === PegawaiWilayah::kanwilSourceUnitSlug();
        }

        if ($user->hasPermission('pegawai.lingkup.kabupaten')) {
            $user->loadMissing('wilayahUnit');

            return $slug !== '' && $slug === $user->wilayahUnit?->slug;
        }

        if ($user->shouldRestrictToWilayah()) {
            $user->loadMissing('wilayahUnit');

            return $slug !== '' && $slug === $user->wilayahUnit?->slug;
        }

        return true;
    }

    public function delete(User $user, Pegawai $pegawai): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function restore(User $user, Pegawai $pegawai): bool
    {
        return false;
    }

    public function forceDelete(User $user, Pegawai $pegawai): bool
    {
        return false;
    }
}
