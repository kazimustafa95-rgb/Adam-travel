<?php

namespace App\Services\Support;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\AppSetting;
use App\Models\CmsPage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class SupportCenterService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function faqs(string|null $search = null): array
    {
        $faqSetting = AppSetting::query()->where('key', 'support.faqs')->first();
        $faqs = is_array($faqSetting?->value) ? ($faqSetting->value['items'] ?? []) : [];

        if (! is_array($faqs)) {
            $faqs = [];
        }

        $search = filled($search) ? mb_strtolower(trim((string) $search)) : null;

        return array_values(array_filter($faqs, function ($faq) use ($search): bool {
            if (! is_array($faq)) {
                return false;
            }

            if ($search === null) {
                return true;
            }

            $haystack = mb_strtolower(($faq['question'] ?? '').' '.($faq['answer'] ?? ''));

            return str_contains($haystack, $search);
        }));
    }

    /**
     * @return Collection<int, CmsPage>
     */
    public function pages(): Collection
    {
        return CmsPage::query()
            ->whereIn('slug', ['privacy-policy', 'terms-of-service', 'help-center'])
            ->where('is_published', true)
            ->orderBy('title')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createTicket(User $user, array $payload): SupportTicket
    {
        return SupportTicket::query()->create([
            'user_id' => $user->id,
            'subject' => $payload['subject'],
            'message' => $payload['message'],
            'priority' => $payload['priority'] ?? SupportTicketPriority::Medium,
            'status' => SupportTicketStatus::Open,
            'metadata' => $payload['metadata'] ?? null,
            'last_user_reply_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, SupportTicket>
     */
    public function ticketsForUser(User $user): Collection
    {
        return SupportTicket::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function screenData(User $user, string|null $search = null): array
    {
        $contactSetting = AppSetting::query()->where('key', 'support.contact')->first();
        $contact = is_array($contactSetting?->value) ? $contactSetting->value : [];

        return [
            'faqs' => $this->faqs($search),
            'pages' => $this->pages()->map(fn (CmsPage $page) => [
                'slug' => $page->slug,
                'title' => $page->title,
                'content' => $page->content,
            ])->values()->all(),
            'contact' => [
                'email' => $contact['email'] ?? 'support@adamtravel.app',
                'response_time' => $contact['response_time'] ?? 'Usually replies within 24 hours.',
            ],
            'app' => [
                'name' => config('app.name'),
                'version' => config('app.version', '2.4.1'),
            ],
            'recent_tickets' => $this->ticketsForUser($user)->take(3)->map(
                fn (SupportTicket $ticket) => [
                    'id' => $ticket->id,
                    'uuid' => $ticket->uuid,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status?->value,
                    'priority' => $ticket->priority?->value,
                    'created_at' => optional($ticket->created_at)?->toIso8601String(),
                ],
            )->values()->all(),
        ];
    }
}
