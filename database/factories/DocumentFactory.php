<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'folder_id' => null,
            'title' => 'Dokumen '.fake()->words(2, true),
            'content' => '<p>Isi awal dokumen.</p>',
            'has_letterhead' => false,
        ];
    }
}
