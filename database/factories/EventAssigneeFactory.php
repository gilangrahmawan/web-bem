<?php

namespace Database\Factories;

use App\Enums\EventCategory;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\EventAssignee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAssignee>
 */
class EventAssigneeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory()->category(EventCategory::Content),
            'user_id' => User::factory(),
            'status' => TaskStatus::Todo,
        ];
    }
}
