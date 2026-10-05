<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminAndAdminRoleTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $adminUser;
    protected User $endUser;
    protected Office $office;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = Office::create([
            'code' => 'OPPMO',
            'name' => 'Office of the Provincial Procurement Management Officer',
            'head_name' => 'Test Head',
            'designation' => 'Provincial Officer',
        ]);

        $password = Hash::make('password123');

        $this->superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@example.test',
            'password' => $password,
            'role' => 'super_admin',
            'office_id' => $this->office->id,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $this->adminUser = User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => $password,
            'role' => 'admin',
            'office_id' => $this->office->id,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $this->endUser = User::create([
            'name' => 'End User Test',
            'email' => 'enduser@example.test',
            'password' => $password,
            'role' => 'end_user',
            'office_id' => $this->office->id,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
    }

    public function test_super_admin_has_full_administrative_access(): void
    {
        $this->actingAs($this->superAdmin);

        // Can access offices management
        $response = $this->postJson('/api/offices', [
            'office_name' => 'Provincial Health Office',
            'abbreviation' => 'PHO',
            'head_name' => 'Dr. Health',
            'designation' => 'Doctor',
        ]);
        $response->assertStatus(201);

        // Can access signatories management
        $response = $this->postJson('/api/signatories', [
            'signatory_type' => 'approved_by',
            'name' => 'Hon. Governor',
            'position' => 'Provincial Governor',
        ]);
        $response->assertStatus(201);

        // Can view users
        $response = $this->getJson('/api/users');
        $response->assertOk();

        // Can view activity logs
        $response = $this->getJson('/api/activity-logs');
        $response->assertOk();
    }

    public function test_admin_role_cannot_modify_offices_or_signatories(): void
    {
        $this->actingAs($this->adminUser);

        // Forbidden to create office
        $response = $this->postJson('/api/offices', [
            'office_name' => 'Provincial Legal Office',
            'abbreviation' => 'PLO',
        ]);
        $response->assertStatus(403);

        // Forbidden to configure signatories
        $response = $this->postJson('/api/signatories', [
            'signatory_type' => 'approved_by',
            'name' => 'Illegal Signatory',
            'position' => 'Hacker',
        ]);
        $response->assertStatus(403);
    }

    public function test_admin_role_can_manage_users_except_super_admin(): void
    {
        $this->actingAs($this->adminUser);

        // Create pending user
        $pendingUser = User::create([
            'name' => 'Pending Staff',
            'email' => 'pending@example.test',
            'password' => Hash::make('secret'),
            'role' => 'end_user',
            'office_id' => $this->office->id,
            'is_active' => false,
            'approval_status' => 'pending',
        ]);

        // Admin can approve user
        $response = $this->postJson("/api/users/{$pendingUser->id}/approve");
        $response->assertOk();
        $this->assertTrue($pendingUser->fresh()->is_active);

        // Admin can change password of regular user
        $response = $this->postJson("/api/users/{$pendingUser->id}/change-password", [
            'password' => 'newpassword123',
        ]);
        $response->assertOk();

        // Admin can change role of regular user
        $response = $this->putJson("/api/users/{$pendingUser->id}/role", [
            'role' => 'head',
        ]);
        $response->assertOk();
        $this->assertEquals('head', $pendingUser->fresh()->role);

        // Admin CANNOT change password of Super Admin
        $response = $this->postJson("/api/users/{$this->superAdmin->id}/change-password", [
            'password' => 'hacksuperadmin',
        ]);
        $response->assertStatus(403);

        // Admin CANNOT deactivate Super Admin
        $response = $this->postJson("/api/users/{$this->superAdmin->id}/toggle-status");
        $response->assertStatus(403);

        // Admin CANNOT delete Super Admin
        $response = $this->deleteJson("/api/users/{$this->superAdmin->id}");
        $response->assertStatus(403);
    }

    public function test_admin_role_can_view_activity_logs_and_ppmp_list(): void
    {
        $this->actingAs($this->adminUser);

        // Can view activity logs
        $response = $this->getJson('/api/activity-logs');
        $response->assertOk();
        $response->assertJsonStructure(['logs', 'available_actions']);

        // Can view all PPMPs
        $response = $this->getJson('/api/ppmps');
        $response->assertOk();

        // Can access dashboard
        $response = $this->getJson('/api/dashboard');
        $response->assertOk();
        $response->assertJsonPath('role', 'admin');
    }
}
