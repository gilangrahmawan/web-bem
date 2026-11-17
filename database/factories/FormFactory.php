<?php

namespace Database\Factories;

use App\Enums\FormStatus;
use App\Models\Form;
use App\Services\Forms\FormBuilderService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Form> */
class FormFactory extends Factory
{
    protected $model = Form::class;

    public function definition(): array
    {
        return [
            'title' => 'Survei '.fake()->words(3, true),
            'description' => fake()->sentence(),
            'is_quiz' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Form $form): void {
            $form->public_id ??= FormBuilderService::newPublicId();
            $form->status ??= FormStatus::Draft;
            $form->settings ??= Form::DEFAULT_SETTINGS;
        })->afterCreating(function (Form $form): void {
            if (! $form->sections()->exists()) {
                $form->sections()->create(['position' => 0]);
            }
        });
    }

    public function published(): static
    {
        return $this->afterMaking(fn (Form $form) => $form->status = FormStatus::Published);
    }

    public function closed(): static
    {
        return $this->afterMaking(function (Form $form): void {
            $form->status = FormStatus::Closed;
            $form->closed_at = now();
        });
    }

    /** @param  array<string, mixed>  $settings */
    public function settings(array $settings): static
    {
        return $this->afterMaking(fn (Form $form) => $form->settings = [...Form::DEFAULT_SETTINGS, ...$settings]);
    }
}
