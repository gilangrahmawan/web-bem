<?php

namespace App\Models;

use App\Enums\DriveItemType;
use Illuminate\Database\Eloquent\Model;

/** Bintang Drive per user (item_type + item_id). */
class DriveStar extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'item_type',
        'item_id',
    ];

    protected function casts(): array
    {
        return [
            'item_type' => DriveItemType::class,
        ];
    }
}
