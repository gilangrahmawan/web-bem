<?php

namespace Database\Factories;

use App\Enums\PostCategory;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = rtrim(fake()->sentence(6), '.');

        return [
            'type' => PostType::Article,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'excerpt' => fake()->sentence(18),
            'body' => '<p>'.implode('</p><p>', fake()->paragraphs(4)).'</p>',
            'category' => fake()->randomElement(PostCategory::cases()),
            'status' => PostStatus::Draft,
            'author_id' => User::factory(),
        ];
    }

    public function published(?\DateTimeInterface $at = null): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Published,
            'published_at' => $at ?? fake()->dateTimeBetween('-3 months', '-1 hour'),
        ]);
    }

    public function status(PostStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function type(PostType $type): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'category' => in_array($type->value, PostType::newsTypes(), true) ? fake()->randomElement(PostCategory::cases()) : null,
        ]);
    }
}
