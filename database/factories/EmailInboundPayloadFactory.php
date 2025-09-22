<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailInboundPayload>
 */
class EmailInboundPayloadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $emailData = [
            'message_id' => fake()->uuid(),
            'subject' => fake()->sentence(),
            'from_email' => fake()->email(),
            'from_name' => fake()->name(),
            'text_body' => fake()->paragraphs(2, true),
            'html_body' => '<p>' . fake()->paragraphs(2, true) . '</p>',
            'headers' => [
                ['Name' => 'From', 'Value' => fake()->name() . ' <' . fake()->email() . '>'],
            ],
        ];

        return [
            'ciphertext' => Crypt::encryptString(json_encode($emailData)),
            'signature_verified' => true,
            'received_at' => now(),
            'purge_after' => now()->addDays(30),
        ];
    }
}
