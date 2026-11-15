<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'color',
        'description',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function structureMembers(): HasMany
    {
        return $this->hasMany(StructureMember::class);
    }
}
