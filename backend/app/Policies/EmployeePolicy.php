<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Support\EmployeeIndukUnit;
use App\Support\PegawaiWilayah;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pegawai.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->hasPermission('pegawai.view');
    }

    public function create(User $user): bool
    {
        if ($user->hasPegawaiKanwilLingkup()) {
            return false;
        }

        return $user->hasPermission('pegawai.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        if (!$user->hasPermission('pegawai.edit') && !$user->hasPermission('pegawai.edit_all')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->hasPegawaiKanwilLingkup()) {
            return EmployeeIndukUnit::forEmployee($employee) === PegawaiWilayah::canonicalKanwilIndukName();
        }

        if ($user->shouldRestrictToWilayah()) {
            $user->loadMissing('wilayahUnit');
            $canonical = PegawaiWilayah::canonicalIndukFromSourceUnitSlug($user->wilayahUnit?->slug);
            if ($canonical === null) {
                return false;
            }

            return EmployeeIndukUnit::forEmployee($employee) === $canonical;
        }

        return true;
    }

    public function delete(User $user, Employee $employee): bool
    {
        if (!$user->hasPermission('pegawai.delete')) {
            return false;
        }

        if ($user->hasPegawaiKanwilLingkup()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->shouldRestrictToWilayah()) {
            $user->loadMissing('wilayahUnit');
            $canonical = PegawaiWilayah::canonicalIndukFromSourceUnitSlug($user->wilayahUnit?->slug);
            if ($canonical === null) {
                return false;
            }

            return EmployeeIndukUnit::forEmployee($employee) === $canonical;
        }

        return true;
    }

    public function restore(User $user, Employee $employee): bool
    {
        return false;
    }

    public function forceDelete(User $user, Employee $employee): bool
    {
        return false;
    }
}
