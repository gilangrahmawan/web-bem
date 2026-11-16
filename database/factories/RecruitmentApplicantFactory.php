<?php

namespace Database\Factories;

use App\Enums\ApplicantStatus;
use App\Models\Division;
use App\Models\Recruitment;
use App\Models\RecruitmentApplicant;
use App\Support\TrackingCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecruitmentApplicant>
 */
class RecruitmentApplicantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recruitment_id' => Recruitment::factory(),
            'registration_code' => TrackingCode::generate('REG', fn (string $c) => RecruitmentApplicant::where('registration_code', $c)->exists()),
            'name' => fake()->name(),
            'nim' => (string) fake()->unique()->numerify('2025########'),
            'prodi' => 'Informatika',
            'batch_year' => 2025,
            'phone' => '0812'.fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'first_choice_division_id' => fn () => Division::where('slug', 'kajian')->value('id'),
            'second_choice_division_id' => null,
            'motivation' => fake()->paragraph(),
            'status' => ApplicantStatus::Registered,
            'consent_at' => now(),
        ];
    }

    public function status(ApplicantStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
