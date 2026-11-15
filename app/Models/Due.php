<?php

namespace App\Models;

use App\Enums\DueType;
use Database\Factories\DueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Iuran tambahan. Peserta = semua pengurus wajib kas (applies_to_all) atau yang dipilih (due_members).
 * Perhitungan tagihan & sisa ada di App\Services\KasService.
 */
class Due extends Model
{
    /** @use HasFactory<DueFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'amount',
        'type',
        'due_date',
        'applies_to_all',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'type' => DueType::class,
            'due_date' => 'date',
            'applies_to_all' => 'boolean',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'due_members')->withTimestamps();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DuePayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
