<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WilayahUnit extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'kind',
        'sort_order',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'wilayah_unit_id');
    }
}
