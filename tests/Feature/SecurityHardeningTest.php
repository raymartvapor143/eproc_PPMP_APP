<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $endUserA;
    protected User $headUserA;
    protected User $headUserB;
    protected Office $officeA;
    protected Office $officeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officeA = Office::create([
            'code' => 'OFFICE-A',
            'name' => 'Department A',
        ]);

        $this->officeB = Office::create([
            'code' => 'OFFICE-B',
            'name' => 'Department B',
        ]);

        $password = Hash::make('password123');

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => $password,
            'role' => 'admin',
            'office_id' => $this->officeA->id,
            'is_active' => true,
        ]);

        $this->endUserA = User::create([
            'name' => 'End User A',
            'email' => 'enduserA@example.test',
            'password' => $password,
            'role' => 'end_user',
            'office_id' => $this->officeA->id,
            'is_active' => true,
        ]);

        $this->headUserA = User::create([
            'name' => 'Head User A',
            'email' => 'headA@example.test',
            'password' => $password,
            'role' => 'head',
            'office_id' => $this->officeA->id,
            'is_active' => true,
        ]);
        $this->officeA->update(['head_user_id' => $this->headUserA->id]);

        $this->headUserB = User::create([
            'name' => 'Head User B',
            'email' => 'headB@example.test',
            'password' => $password,
            'role' => 'head',
            'office_id' => $this->officeB->id,
            'is_active' => true,
        ]);
        $this->officeB->update(['head_user_id' => $this->headUserB->id]);
    }

    /**
     * Test defensive HTTP security headers are attached to responses.
     */
    public function test_defensive_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * Test CSRF token endpoint returns fresh token.
     */
    public function test_csrf_token_endpoint_returns_token(): void
    {
        $response = $this->getJson('/api/csrf-token');

        $response->assertStatus(200);
        $response->assertJsonStructure(['csrf_token']);
        $this->assertNotEmpty($response->json('csrf_token'));
    }

    /**
     * Test session hijacking detection triggers when User-Agent changes mid-session.
     */
    public function test_session_hijacking_detection_blocks_stolen_session(): void
    {
        $originalAgent = 'LegitimateBrowser/1.0 (Windows NT 10.0)';
        $attackerAgent = 'AttackerScript/2.0 (Linux x86_64)';

        // 1. Legitimate user logs in
        $loginRes = $this->withServerVariables(['HTTP_USER_AGENT' => $originalAgent])
            ->postJson('/api/login', [
                'email' => 'enduserA@example.test',
                'password' => 'password123',
            ]);

        $loginRes->assertStatus(200);
        $loginRes->assertJsonStructure(['csrf_token', 'user']);

        // 2. Legitimate user can query /api/me with same User-Agent
        $meRes = $this->withServerVariables(['HTTP_USER_AGENT' => $originalAgent])
            ->getJson('/api/me');
        $meRes->assertStatus(200);

        // 3. Attacker using stolen session cookie but different User-Agent
        $hijackRes = $this->withServerVariables(['HTTP_USER_AGENT' => $attackerAgent])
            ->getJson('/api/me');

        // Session must be invalidated and 401 returned
        $hijackRes->assertStatus(401);
        $hijackRes->assertJson(['code' => 'SESSION_HIJACK_PREVENTED']);

        // Audit log must have logged the incident
        $this->assertTrue(
            AuditLog::where('action', 'SESSION_HIJACK_DETECTED')
                ->where('user_id', $this->endUserA->id)
                ->exists()
        );
    }

    /**
     * Test IDOR protection: End User from Office B cannot view Office A's draft PPMP.
     */
    public function test_idor_protection_blocks_unauthorized_ppmp_show(): void
    {
        $endUserB = User::create([
            'name' => 'End User B',
            'email' => 'enduserB@example.test',
            'password' => Hash::make('password123'),
            'role' => 'end_user',
            'office_id' => $this->officeB->id,
            'is_active' => true,
        ]);

        $ppmpA = Ppmp::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'ppmp_number' => 'PPMP-2026-000001',
            'tracking_number' => 'PPMP-2026-000001',
            'title' => 'Confidential Office A Draft PPMP',
            'fiscal_year' => '2026',
            'plan_type' => 'FINAL',
            'status' => 'DRAFT',
            'office_id' => $this->officeA->id,
            'created_by' => $this->endUserA->id,
            'total_budget' => 50000.00,
        ]);

        // End user from Office B attempts to view Office A's draft PPMP
        $res = $this->actingAs($endUserB)->getJson("/api/ppmps/{$ppmpA->uuid}");
        $res->assertStatus(403);
    }

    /**
     * Test cross-office Head authorization: Head B cannot approve Office A's PPMP.
     */
    public function test_cross_office_head_cannot_approve_other_office_ppmp(): void
    {
        $ppmpA = Ppmp::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'ppmp_number' => 'PPMP-2026-000002',
            'tracking_number' => 'PPMP-2026-000002',
            'title' => 'Office A PPMP Awaiting Head',
            'fiscal_year' => '2026',
            'plan_type' => 'FINAL',
            'status' => 'HEAD_PENDING',
            'office_id' => $this->officeA->id,
            'created_by' => $this->endUserA->id,
            'total_budget' => 75000.00,
        ]);

        // Head B attempts to approve Office A's PPMP
        $res = $this->actingAs($this->headUserB)->postJson("/api/ppmps/{$ppmpA->uuid}/head/approve");
        $res->assertStatus(403);

        // Head A (correct office) approves successfully
        $headARes = $this->actingAs($this->headUserA)->postJson("/api/ppmps/{$ppmpA->uuid}/head/approve");
        $headARes->assertStatus(200);
        $this->assertEquals('HEAD_APPROVED', $headARes->json('ppmp.status'));
    }

    /**
     * Test public offices endpoint does not leak office head PII.
     */
    public function test_public_offices_does_not_leak_head_pii(): void
    {
        $this->headUserA->update([
            'phone_number' => '+639123456789',
            'address' => 'Private Residence Block 1 Lot 2',
        ]);

        $res = $this->getJson('/api/public-offices');
        $res->assertStatus(200);

        $firstOffice = collect($res->json())->firstWhere('code', 'OFFICE-A');
        $this->assertNotNull($firstOffice);
        $this->assertArrayHasKey('head', $firstOffice);

        // Sensitive PII must NOT be present
        $this->assertArrayNotHasKey('phone_number', $firstOffice['head'] ?? []);
        $this->assertArrayNotHasKey('address', $firstOffice['head'] ?? []);
        $this->assertArrayNotHasKey('signature_path', $firstOffice['head'] ?? []);
    }

    /**
     * Test regular user cannot scrape an unrelated end user's signature.
     */
    public function test_unauthorized_user_cannot_scrape_unrelated_signature(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $sigPath = 'signatures/unrelated.png';
        \Illuminate\Support\Facades\Storage::disk('local')->put($sigPath, 'fake-image-bytes');

        $unrelatedUser = User::create([
            'name' => 'Unrelated User',
            'email' => 'unrelated@example.test',
            'password' => Hash::make('password123'),
            'role' => 'end_user',
            'office_id' => $this->officeB->id,
            'signature_path' => $sigPath,
            'is_active' => true,
        ]);

        // End user A attempts to download unrelated user's signature
        $res = $this->actingAs($this->endUserA)->get("/api/users/{$unrelatedUser->id}/signature");
        $res->assertStatus(403);

        // Target user can download their own signature
        $selfRes = $this->actingAs($unrelatedUser)->get("/api/users/{$unrelatedUser->id}/signature");
        $selfRes->assertStatus(200);

        // Admin can download it
        $adminRes = $this->actingAs($this->adminUser)->get("/api/users/{$unrelatedUser->id}/signature");
        $adminRes->assertStatus(200);
    }

    /**
     * Test registration rejects privilege escalation to admin role.
     */
    public function test_registration_rejects_admin_role_escalation(): void
    {
        $res = $this->postJson('/api/register', [
            'name' => 'Hacker Admin Attempt',
            'email' => 'hacker@example.test',
            'role' => 'admin',
            'phone_number' => '+639000000000',
            'address' => 'Unknown',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'office_id' => $this->officeA->id,
            'designation' => 'Staff',
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['role']);
    }
}
