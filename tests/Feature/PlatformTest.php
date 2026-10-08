<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function project(User $client): int
    {
        return DB::table('projects')->insertGetId(['client_id' => $client->id, 'name' => 'Inventory', 'description' => 'Stock and sales', 'status' => 'planning', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function quote(User $client, string $status = 'sent'): int
    {
        return DB::table('quotations')->insertGetId(['client_id' => $client->id, 'title' => 'Business platform', 'scope' => 'Inventory and sales', 'amount' => 150000, 'status' => $status, 'valid_until' => now()->addMonth()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function booking(): array
    {
        return ['name' => 'Test Client', 'email' => 'client@example.com', 'organization' => 'Test Business', 'sector' => 'Business', 'requirements' => 'Inventory system', 'date' => now()->addDay()->toDateString(), 'slot' => '09:00'];
    }

    public function test_public_content_does_not_expose_workspace_data(): void
    {
        $this->getJson('/api/content')->assertOk()->assertJsonPath('about.founder_name', 'Thenmarck V. Dulos')->assertJsonMissingPath('clients');
        $this->getJson('/api/workspace')->assertUnauthorized();
        $this->getJson('/api/admin/data')->assertUnauthorized();
    }

    public function test_admin_screen_requires_admin_role(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/admin/data')->assertForbidden();
        $this->postJson('/api/admin/projects', ['client_id' => 1])->assertForbidden();
    }

    public function test_booking_conflicts_and_cancelled_slot_reuse(): void
    {
        $payload = $this->booking();
        $this->postJson('/api/bookings', $payload)->assertCreated()->assertJsonStructure(['reference']);
        $this->postJson('/api/bookings', $payload)->assertUnprocessable()->assertJsonValidationErrors('slot');
        $this->getJson('/api/availability?date='.$payload['date'])->assertOk()->assertJsonMissing(['09:00']);
        $booking = DB::table('bookings')->first();
        $this->actingAs($this->admin())->patchJson('/api/admin/bookings/'.$booking->id, ['status' => 'cancelled', 'notes' => 'Client cancelled'])->assertOk();
        $this->postJson('/api/bookings', $payload)->assertCreated();
        $this->patchJson('/api/admin/bookings/'.$booking->id, ['status' => 'confirmed'])->assertUnprocessable();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled', 'reservation_key' => null]);
    }

    public function test_booking_rejects_past_and_unlisted_times(): void
    {
        $payload = $this->booking();
        $payload['date'] = now()->subDay()->toDateString();
        $this->postJson('/api/bookings', $payload)->assertUnprocessable();
        $payload = $this->booking();
        $payload['slot'] = '23:00';
        $this->postJson('/api/bookings', $payload)->assertUnprocessable();
    }

    public function test_client_can_only_see_owned_projects_and_issued_invoices(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $own = $this->project($a);
        $other = $this->project($b);
        DB::table('invoices')->insert(['project_id' => $own, 'reference' => 'DRAFT', 'description' => 'Planning', 'amount' => 100, 'due_date' => now()->toDateString(), 'status' => 'draft']);
        DB::table('invoices')->insert(['project_id' => $own, 'reference' => 'SENT', 'description' => 'Planning', 'amount' => 100, 'due_date' => now()->toDateString(), 'status' => 'sent']);
        $this->quote($a, 'draft');
        $this->quote($b);
        $this->actingAs($a)->getJson('/api/workspace')->assertOk()->assertJsonCount(1, 'projects')->assertJsonPath('projects.0.id', $own)->assertJsonCount(1, 'invoices')->assertJsonPath('invoices.0.reference', 'SENT')->assertJsonCount(0, 'quotations');
        $this->postJson('/api/tickets', ['project_id' => $other, 'subject' => 'Hidden project', 'details' => 'Should fail'])->assertNotFound();
    }

    public function test_quotation_acceptance_creates_exactly_one_project_and_keeps_agreement(): void
    {
        $client = User::factory()->create();
        $id = $this->quote($client);
        $this->actingAs($client)->postJson('/api/quotations/'.$id.'/decision', ['decision' => 'accepted'])->assertOk();
        $this->postJson('/api/quotations/'.$id.'/decision', ['decision' => 'accepted'])->assertUnprocessable();
        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseHas('projects', ['client_id' => $client->id, 'quotation_id' => $id]);
        $this->actingAs($this->admin())->putJson('/api/admin/quotations/'.$id, ['client_id' => $client->id, 'title' => 'Changed', 'scope' => 'Changed', 'amount' => 1, 'valid_until' => now()->addMonth()->toDateString(), 'status' => 'sent'])->assertUnprocessable();
    }

    public function test_clients_cannot_accept_other_clients_or_draft_or_expired_quotations(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $id = $this->quote($b);
        $this->actingAs($a)->postJson('/api/quotations/'.$id.'/decision', ['decision' => 'accepted'])->assertNotFound();
        $draft = $this->quote($a, 'draft');
        $this->postJson('/api/quotations/'.$draft.'/decision', ['decision' => 'accepted'])->assertUnprocessable();
        $expired = $this->quote($a);
        DB::table('quotations')->where('id', $expired)->update(['valid_until' => now()->subDay()->toDateString()]);
        $this->postJson('/api/quotations/'.$expired.'/decision', ['decision' => 'accepted'])->assertUnprocessable();
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_invitation_is_hashed_single_use_and_cannot_promote_a_client(): void
    {
        $admin = $this->admin();
        $client = $this->actingAs($admin)->postJson('/api/admin/clients', ['name' => 'New client', 'email' => 'new@example.com', 'organization' => 'Client Org', 'role' => 'admin'])->assertCreated()->json('user');
        $this->assertSame('client', $client['role']);
        $url = $this->postJson('/api/admin/clients/'.$client['id'].'/invitation')->assertOk()->json('url');
        $token = basename($url);
        $this->assertDatabaseHas('users', ['id' => $client['id'], 'invitation_token' => hash('sha256', $token)]);
        $this->postJson('/api/logout')->assertOk();
        $password = 'SecurePassword123';
        $this->postJson('/api/accept-invitation', ['token' => $token, 'password' => $password, 'password_confirmation' => $password])->assertOk()->assertJsonPath('user.role', 'client')->assertJsonStructure(['csrf']);
        $this->postJson('/api/accept-invitation', ['token' => $token, 'password' => $password, 'password_confirmation' => $password])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $client['id'], 'invitation_token' => null]);
    }

    public function test_expired_invitation_cannot_activate_account(): void
    {
        $user = User::factory()->create(['invitation_token' => hash('sha256', 'a'.str_repeat('b', 63)), 'invitation_expires_at' => now()->subMinute()]);
        $this->postJson('/api/accept-invitation', ['token' => 'a'.str_repeat('b', 63), 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123'])->assertUnprocessable();
    }

    public function test_login_returns_fresh_csrf_and_logout_removes_authentication(): void
    {
        $client = User::factory()->create(['password' => Hash::make('SecurePassword123')]);
        $this->postJson('/api/login', ['email' => $client->email, 'password' => 'SecurePassword123'])->assertOk()->assertJsonStructure(['csrf', 'user']);
        $this->getJson('/api/workspace')->assertOk();
        $this->postJson('/api/logout')->assertOk();
        $this->getJson('/api/workspace')->assertUnauthorized();
    }

    public function test_pending_invited_account_cannot_bypass_activation(): void
    {
        $client = User::factory()->create(['password' => Hash::make('SecurePassword123'), 'invitation_token' => hash('sha256', Str::random(64))]);
        $this->postJson('/api/login', ['email' => $client->email, 'password' => 'SecurePassword123'])->assertUnprocessable();
        $this->getJson('/api/workspace')->assertUnauthorized();
    }

    public function test_csrf_protects_public_booking_and_authentication_posts(): void
    {
        $this->app['env'] = 'production';
        $this->withSession(['_token' => 'known-session-token'])->postJson('/api/bookings', $this->booking())->assertStatus(419);
        $this->withSession(['_token' => 'known-session-token'])->postJson('/api/login', ['email' => 'test@example.com', 'password' => 'wrong'])->assertStatus(419);
    }

    public function test_milestone_review_requires_owner_and_ready_status_and_keeps_revision(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = $this->project($owner);
        $id = DB::table('milestones')->insertGetId(['project_id' => $project, 'title' => 'Inventory', 'status' => 'planned']);
        $this->actingAs($owner)->postJson('/api/milestones/'.$id.'/decision', ['decision' => 'approved'])->assertUnprocessable();
        DB::table('milestones')->where('id', $id)->update(['status' => 'ready_for_review']);
        $this->actingAs($other)->postJson('/api/milestones/'.$id.'/decision', ['decision' => 'approved'])->assertNotFound();
        $this->actingAs($owner)->postJson('/api/milestones/'.$id.'/decision', ['decision' => 'revision_requested', 'note' => 'Add a stock filter'])->assertOk();
        $this->assertDatabaseHas('revisions', ['milestone_id' => $id, 'note' => 'Add a stock filter']);
        $this->postJson('/api/milestones/'.$id.'/decision', ['decision' => 'approved'])->assertUnprocessable();
    }

    public function test_files_are_private_and_cannot_be_downloaded_or_deleted_by_other_clients(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = $this->project($owner);
        $id = $this->actingAs($owner)->post('/api/files', ['project_id' => $project, 'file' => UploadedFile::fake()->createWithContent('brief.txt', 'Private project brief')], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $this->get('/api/files/'.$id.'/download')->assertOk()->assertDownload('brief.txt');
        $this->actingAs($other)->getJson('/api/files/'.$id.'/download')->assertNotFound();
        $this->deleteJson('/api/files/'.$id)->assertNotFound();
        $this->actingAs($owner)->deleteJson('/api/files/'.$id)->assertOk();
        $this->assertDatabaseCount('project_files', 0);
    }

    public function test_file_uploads_reject_unapproved_types_and_other_projects(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = $this->project($other);
        $this->actingAs($owner)->post('/api/files', ['project_id' => $project, 'file' => UploadedFile::fake()->createWithContent('brief.txt', 'test')], ['Accept' => 'application/json'])->assertNotFound();
        $this->post('/api/files', ['project_id' => $project, 'file' => UploadedFile::fake()->create('script.php', 1, 'application/x-php')], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_support_threads_are_scoped_and_status_can_be_reopened(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = $this->project($owner);
        $id = $this->actingAs($owner)->postJson('/api/tickets', ['project_id' => $project, 'subject' => 'Export issue', 'details' => 'Cannot export report'])->assertCreated()->json('id');
        $this->postJson('/api/tickets/'.$id.'/replies', ['body' => 'More details'])->assertCreated();
        $this->actingAs($other)->postJson('/api/tickets/'.$id.'/replies', ['body' => 'Unauthorized'])->assertNotFound();
        $this->patchJson('/api/tickets/'.$id.'/status', ['status' => 'resolved'])->assertNotFound();
        $this->actingAs($owner)->patchJson('/api/tickets/'.$id.'/status', ['status' => 'resolved'])->assertOk();
        $this->patchJson('/api/tickets/'.$id.'/status', ['status' => 'open'])->assertOk();
        $this->getJson('/api/workspace')->assertJsonPath('replies.0.body', 'More details');
    }

    public function test_admin_content_is_persistent_and_validated(): void
    {
        $content = SiteContent::defaults();
        $content['home']['title'] = 'Better systems for your team.';
        $this->actingAs($this->admin())->putJson('/api/admin/content', $content)->assertOk();
        $this->getJson('/api/content')->assertJsonPath('home.title', 'Better systems for your team.');
        $content['home']['script'] = '<script>alert(1)</script>';
        $this->putJson('/api/admin/content', $content)->assertUnprocessable();
    }

    public function test_admin_resource_relationships_and_roles_are_validated(): void
    {
        $admin = $this->admin();
        $client = User::factory()->create();
        $this->actingAs($admin)->postJson('/api/admin/projects', ['client_id' => $admin->id, 'name' => 'Test', 'description' => 'Test', 'status' => 'planning'])->assertUnprocessable();
        $this->postJson('/api/admin/projects', ['client_id' => $client->id, 'name' => 'Test', 'description' => 'Test', 'status' => 'planning', 'role' => 'admin'])->assertCreated();
        $this->assertDatabaseCount('projects', 1);
    }

    public function test_existing_project_records_cannot_be_moved_to_another_client_project(): void
    {
        $admin = $this->admin();
        $client = User::factory()->create();
        $a = $this->project($client);
        $b = $this->project($client);
        $id = DB::table('milestones')->insertGetId(['project_id' => $a, 'title' => 'Original', 'status' => 'planned']);
        $this->actingAs($admin)->putJson('/api/admin/milestones/'.$id, ['project_id' => $b, 'title' => 'Moved', 'status' => 'ready_for_review', 'position' => 0])->assertUnprocessable();
        $this->assertDatabaseHas('milestones', ['id' => $id, 'project_id' => $a]);
    }

    public function test_unknown_api_routes_return_json_404_instead_of_the_spa(): void
    {
        $this->getJson('/api/not-a-route')->assertNotFound();
    }

    public function test_admin_cannot_approve_a_clients_deliverable_on_their_behalf(): void
    {
        $client = User::factory()->create();
        $project = $this->project($client);
        $id = DB::table('milestones')->insertGetId(['project_id' => $project, 'title' => 'Deliverable', 'status' => 'ready_for_review']);
        $this->actingAs($this->admin())->postJson('/api/milestones/'.$id.'/decision', ['decision' => 'approved'])->assertForbidden();
        $this->assertDatabaseHas('milestones', ['id' => $id, 'status' => 'ready_for_review', 'approved_at' => null]);
    }

    public function test_founder_photo_upload_is_admin_only_and_content_stays_public(): void
    {
        Storage::fake('public');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6KsAAAAASUVORK5CYII=');
        $this->actingAs(User::factory()->create())->post('/api/admin/founder-image', ['image' => UploadedFile::fake()->createWithContent('photo.png', $image)], ['Accept' => 'application/json'])->assertForbidden();
        $url = $this->actingAs($this->admin())->post('/api/admin/founder-image', ['image' => UploadedFile::fake()->createWithContent('photo.png', $image)], ['Accept' => 'application/json'])->assertOk()->json('url');
        $this->assertStringStartsWith('/storage/founder/', $url);
        $this->getJson('/api/content')->assertJsonPath('about.founder_image', $url);
    }

    public function test_admin_cannot_publish_external_founder_image_urls(): void
    {
        $content = SiteContent::defaults();
        $content['about']['founder_image'] = 'https://example.com/tracker.jpg';
        $this->actingAs($this->admin())->putJson('/api/admin/content', $content)->assertUnprocessable()->assertJsonValidationErrors('about.founder_image');
    }
}
