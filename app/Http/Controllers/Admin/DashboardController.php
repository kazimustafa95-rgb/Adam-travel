<?php

namespace App\Http\Controllers\Admin;

use App\Models\ActivityLog;
use App\Models\Import;
use App\Models\Location;
use App\Models\SavedPlace;
use App\Models\SupportTicket;
use App\Models\Trip;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends BaseAdminController
{
    public function __invoke(): View
    {
        $metrics = [
            [
                'label' => 'Registered Users',
                'value' => User::query()->count(),
                'description' => 'All mobile users with accounts in the platform.',
            ],
            [
                'label' => 'Saved Locations',
                'value' => SavedPlace::query()->count(),
                'description' => 'User-owned saved places currently in the system.',
            ],
            [
                'label' => 'Imports Needing Attention',
                'value' => Import::query()->whereIn('status', ['manual_review', 'failed'])->count(),
                'description' => 'Imports currently blocked by failure or manual review.',
            ],
            [
                'label' => 'Open Support Tickets',
                'value' => SupportTicket::query()->whereIn('status', ['open', 'in_progress'])->count(),
                'description' => 'Tickets waiting on admin action or customer resolution.',
            ],
            [
                'label' => 'Hidden Locations',
                'value' => Location::query()->where('is_moderated_hidden', true)->count(),
                'description' => 'Locations currently removed from user-facing discovery surfaces.',
            ],
        ];

        $recentImports = Import::query()->with('user')->latest('id')->limit(5)->get();
        $recentTrips = Trip::query()->with('owner')->withCount(['members', 'pool'])->latest('id')->limit(5)->get();
        $recentTickets = SupportTicket::query()->with(['user', 'assignedAdmin'])->latest('id')->limit(5)->get();
        $recentActivity = ActivityLog::query()->with('admin')->latest('id')->limit(8)->get();

        return view('admin.dashboard', compact('metrics', 'recentImports', 'recentTrips', 'recentTickets', 'recentActivity'));
    }
}
