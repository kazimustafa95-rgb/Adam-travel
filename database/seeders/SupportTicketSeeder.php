<?php

namespace Database\Seeders;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupportTicketSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->limit(3)->get();

        if ($users->isEmpty()) {
            $users = collect([
                User::factory()->create([
                    'name' => 'Mina Example',
                    'email' => 'mina@example.com',
                ]),
                User::factory()->create([
                    'name' => 'Jonas Example',
                    'email' => 'jonas@example.com',
                ]),
            ]);
        }

        $tickets = [
            [
                'subject' => 'Offline trip package expired too early',
                'message' => 'My Japan trip package disappeared after a sync even though the trip is next week.',
                'priority' => SupportTicketPriority::High,
                'status' => SupportTicketStatus::Open,
            ],
            [
                'subject' => 'Import could not resolve coordinates from Instagram caption',
                'message' => 'The import found the place name but kept asking me for manual coordinates.',
                'priority' => SupportTicketPriority::Medium,
                'status' => SupportTicketStatus::InProgress,
            ],
            [
                'subject' => 'Need help restoring Premium subscription',
                'message' => 'I renewed on iPhone and the paywall still shows Free on my Android device.',
                'priority' => SupportTicketPriority::Urgent,
                'status' => SupportTicketStatus::Open,
            ],
        ];

        foreach ($tickets as $index => $ticket) {
            $user = $users[$index % $users->count()];

            SupportTicket::query()->updateOrCreate(
                [
                    'subject' => $ticket['subject'],
                ],
                [
                    'user_id' => $user->id,
                    'message' => $ticket['message'],
                    'priority' => $ticket['priority'],
                    'status' => $ticket['status'],
                    'last_user_reply_at' => now()->subDays($index + 1),
                ],
            );
        }
    }
}
