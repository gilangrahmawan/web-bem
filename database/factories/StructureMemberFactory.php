<?php

namespace Database\Factories;

use App\Models\Period;
use App\Models\StructureMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StructureMember>
 */
class StructureMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'period_id' => Period::factory(),
            'name' => fake()->name(),
            'position' => 'Anggota',
            'prodi' => fake()->randomElement(['Informatika', 'Sistem Informasi', 'Manajemen', 'Akuntansi', 'Ilmu Hukum']),
            'sort_order' => 0,
        ];
    }
}
