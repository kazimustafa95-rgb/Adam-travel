<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\ImportStatus;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\TripStatus;
use App\Models\Admin;
use App\Models\AppSetting;
use App\Models\CmsPage;
use App\Models\Import;
use App\Models\Location;
use App\Models\SupportTicket;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\AppSettingSeeder;
use Database\Seeders\CmsPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin/dashboard')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_can_view_dashboard_and_users_directory(): void
    {
        $admin = Admin::factory()->create();
        User::factory()->count(2)->create();

        $this->actingAs($admin, 'admin')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Imports Needing Attention')
            ->assertSee('Open Support Tickets');

        $this->actingAs($admin, 'admin')
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('User Directory');
    }

    public function test_admin_can_manage_profile_settings_and_password(): void
    {
        $admin = Admin::factory()->create([
            'name' => 'Console Owner',
            'email' => 'owner@example.com',
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('Profile Settings')
            ->assertSee('Console Owner');

        $this->actingAs($admin, 'admin')
            ->from('/admin/profile')
            ->patch('/admin/profile', [
                'name' => 'Operations Owner',
                'email' => 'ops.owner@example.com',
            ])
            ->assertRedirect('/admin/profile');

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'name' => 'Operations Owner',
            'email' => 'ops.owner@example.com',
        ]);

        $this->actingAs($admin->fresh(), 'admin')
            ->from('/admin/profile')
            ->patch('/admin/profile/password', [
                'current_password' => 'password',
                'password' => 'NewSecurePassword123!',
                'password_confirmation' => 'NewSecurePassword123!',
            ])
            ->assertRedirect('/admin/profile');

        $this->assertTrue(Hash::check('NewSecurePassword123!', $admin->fresh()->password));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admin.profile_updated',
            'target_type' => 'Admin',
            'target_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admin.password_updated',
            'target_type' => 'Admin',
            'target_id' => $admin->id,
        ]);
    }

    public function test_admin_can_update_user_status_and_revoke_tokens(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $user->createToken('Phone Token');

        $this->actingAs($admin, 'admin')
            ->from('/admin/users/'.$user->id)
            ->patch('/admin/users/'.$user->id.'/status', [
                'status' => AccountStatus::Suspended->value,
            ])
            ->assertRedirect('/admin/users/'.$user->id);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => AccountStatus::Suspended->value,
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.status_updated',
            'target_type' => 'User',
            'target_id' => $user->id,
        ]);
    }

    public function test_admin_can_retry_import_and_moderate_location(): void
    {
        $admin = Admin::factory()->create();
        $import = Import::factory()->create([
            'status' => ImportStatus::Failed,
            'error_code' => 'processing_failed',
            'error_message' => 'Something broke.',
        ]);
        $location = Location::factory()->create([
            'is_moderated_hidden' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->from('/admin/imports/'.$import->id)
            ->post('/admin/imports/'.$import->id.'/retry')
            ->assertRedirect('/admin/imports/'.$import->id);

        $import->refresh();

        $this->assertNull($import->error_code);
        $this->assertNotSame(ImportStatus::Failed, $import->status);

        $this->actingAs($admin, 'admin')
            ->from('/admin/locations')
            ->patch('/admin/locations/'.$location->id, [
                'is_moderated_hidden' => '1',
            ])
            ->assertRedirect('/admin/locations');

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'is_moderated_hidden' => true,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'import.retried',
            'target_type' => 'Import',
            'target_id' => $import->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'location.moderation_updated',
            'target_type' => 'Location',
            'target_id' => $location->id,
        ]);
    }

    public function test_admin_can_update_trip_ticket_cms_and_settings(): void
    {
        $this->seed([AppSettingSeeder::class, CmsPageSeeder::class]);

        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $trip = Trip::factory()->for($user, 'owner')->create([
            'status' => TripStatus::Draft,
        ]);
        $ticket = SupportTicket::query()->create([
            'user_id' => $user->id,
            'subject' => 'Need help with premium restore',
            'message' => 'Restore is not syncing to my other device.',
            'priority' => SupportTicketPriority::High,
            'status' => SupportTicketStatus::Open,
        ]);
        $page = CmsPage::query()->firstOrFail();
        $setting = AppSetting::query()->where('key', 'offline.package_ttl_days')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->from('/admin/trips/'.$trip->id)
            ->patch('/admin/trips/'.$trip->id, [
                'status' => TripStatus::Archived->value,
            ])
            ->assertRedirect('/admin/trips/'.$trip->id);

        $this->actingAs($admin, 'admin')
            ->from('/admin/support-tickets/'.$ticket->id)
            ->patch('/admin/support-tickets/'.$ticket->id, [
                'status' => SupportTicketStatus::Resolved->value,
                'priority' => SupportTicketPriority::Urgent->value,
                'assigned_admin_id' => $admin->id,
                'admin_notes' => 'Restored premium after checking webhook sync.',
            ])
            ->assertRedirect('/admin/support-tickets/'.$ticket->id);

        $this->actingAs($admin, 'admin')
            ->patch('/admin/cms-pages/'.$page->id, [
                'title' => 'Updated '.$page->title,
                'content' => 'Rewritten page content for admin verification.',
                'is_published' => '1',
            ])
            ->assertRedirect('/admin/cms-pages/'.$page->id.'/edit');

        $this->actingAs($admin, 'admin')
            ->from('/admin/app-settings')
            ->patch('/admin/app-settings/'.$setting->id, [
                'value_type' => 'integer',
                'value' => '45',
            ])
            ->assertRedirect('/admin/app-settings');

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'status' => TripStatus::Archived->value,
        ]);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'status' => SupportTicketStatus::Resolved->value,
            'priority' => SupportTicketPriority::Urgent->value,
            'assigned_admin_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('cms_pages', [
            'id' => $page->id,
            'title' => 'Updated '.$page->title,
            'is_published' => true,
        ]);

        $setting->refresh();

        $this->assertSame(45, data_get($setting->value, 'value'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'trip.status_updated']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'support_ticket.updated']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'cms_page.updated']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'app_setting.updated']);
    }
}
