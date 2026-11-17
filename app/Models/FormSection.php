<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bagian form (halaman). Pertanyaan tanpa bagian ada di bagian pertama. */
class FormSection extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'form_id',
        'title',
        'description',
        'position',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FormQuestion::class)->orderBy('position');
    }
}
