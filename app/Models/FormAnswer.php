<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jawaban per pertanyaan. files = [{path, name, mime, size}] di disk privat. grader_note internal. */
class FormAnswer extends Model
{
    public const DISK = 'local';

    public const DIRECTORY = 'forms';

    /** @var list<string> */
    protected $fillable = [
        'form_response_id',
        'form_question_id',
        'value',
        'files',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'files' => 'array',
            'points_awarded' => 'float',
        ];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(FormResponse::class, 'form_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(FormQuestion::class, 'form_question_id')->withTrashed();
    }
}
