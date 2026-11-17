<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pertanyaan form. correct_answer ($hidden) TIDAK PERNAH dikirim ke peserta — tampilan peserta
 * memakai App\Support\Forms\ParticipantFormView yang hanya memuat field aman.
 */
class FormQuestion extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'form_id',
        'form_section_id',
        'type',
        'label',
        'help_text',
        'is_required',
        'position',
        'options',
        'validation',
        'points',
        'correct_answer',
    ];

    /** @var list<string> */
    protected $hidden = ['correct_answer'];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'is_required' => 'boolean',
            'options' => 'array',
            'validation' => 'array',
            'correct_answer' => 'array',
            'points' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(FormSection::class, 'form_section_id');
    }

    /** @return list<string> */
    public function choices(): array
    {
        return array_values(array_filter(array_map('strval', $this->options['choices'] ?? []), fn ($c) => $c !== ''));
    }

    public function allowsOther(): bool
    {
        return $this->type->allowsOther() && (bool) ($this->options['allow_other'] ?? false);
    }

    public function rule(string $key, mixed $default = null): mixed
    {
        return $this->validation[$key] ?? $default;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
