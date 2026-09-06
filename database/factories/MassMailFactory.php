<?php

namespace Database\Factories;

use App\Models\MassMail;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MassMail>
 */
class MassMailFactory extends Factory
{
    protected $model = MassMail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'subject' => fake()->sentence(4),
            'body' => "Hello {{user_name}},\n\nThis is a message from {{app_name}}.",
            'audience_type' => MassMail::AUDIENCE_ALL_CUSTOMERS,
            'filters' => null,
            'status' => MassMail::STATUS_QUEUED,
            'recipient_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'scheduled_at' => now(),
        ];
    }

    public function sending(): static
    {
        return $this->state(fn (): array => [
            'status' => MassMail::STATUS_SENDING,
            'started_at' => now(),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => MassMail::STATUS_SENT,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => MassMail::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
