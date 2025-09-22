<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Thread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Action>
 */
class ActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'thread_id' => Thread::factory(),
            'type' => fake()->randomElement(['info_request', 'approve', 'reject', 'revise']),
            'payload_json' => ['question' => fake()->sentence()],
            'status' => 'pending',
            'locale' => 'en_US',
        ];
    }
}
