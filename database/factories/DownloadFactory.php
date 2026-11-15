<?php

namespace Database\Factories;

use App\Enums\DownloadCategory;
use App\Models\Download;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Download>
 */
class DownloadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Template '.fake()->words(3, true),
            'category' => fake()->randomElement(DownloadCategory::cases()),
            'description' => fake()->sentence(),
            'file_path' => Download::DIRECTORY.'/'.Str::uuid().'.pdf',
            'original_name' => 'dokumen.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
