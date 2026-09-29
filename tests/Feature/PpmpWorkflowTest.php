<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PpmpWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $endUser;
    protected User $headUser;
    protected User $budgetUser;
    protected User $oppmoUser;
    protected User $twgUser;
    protected Office $office;

    protected function setUp(): void
    {
        parent::setUp();

        // Prepare test environment
        $this->office = Office::firstOrCreate(
            ['code' => 'TEST-OFFICE'],
            ['name' => 'Testing Office', 'head_user_id' => null]
        );

        $password = Hash::make('password123');

        $this->endUser = User::firstOrCreate(
            ['email' => 'test_enduser@example.test'],
            ['name' => 'Test End User', 'password' => $password, 'role' => 'end_user', 'office_id' => $this->office->id, 'is_active' => true]
        );

        $this->headUser = User::firstOrCreate(
            ['email' => 'test_head@example.test'],
            ['name' => 'Test Head', 'password' => $password, 'role' => 'head', 'office_id' => $this->office->id, 'is_active' => true]
        );

        $this->office->update(['head_user_id' => $this->headUser->id]);

        $this->budgetUser = User::firstOrCreate(
            ['email' => 'test_budget@example.test'],
            ['name' => 'Test Budget Officer', 'password' => $password, 'role' => 'budget_officer', 'office_id' => $this->office->id, 'is_active' => true]
        );

        $this->oppmoUser = User::firstOrCreate(
            ['email' => 'test_oppmo@example.test'],
            ['name' => 'Test OPPMO User', 'password' => $password, 'role' => 'oppmo', 'office_id' => $this->office->id, 'is_active' => true]
        );

        $this->twgUser = User::firstOrCreate(
            ['email' => 'test_twg@example.test'],
            ['name' => 'Test TWG User', 'password' => $password, 'role' => 'twg', 'office_id' => $this->office->id, 'is_active' => true]
        );
    }

    /**
     * Test full normal happy path workflow
     * DRAFT -> HEAD -> REVIEW -> BUDGET -> OPPMO -> TWG -> READY_TO_PRINT
     */
    public function test_complete_ppmp_workflow_and_signatures(): void
    {
        // 1. End User creates PPMP Draft
        $createRes = $this->actingAs($this->endUser)->postJson('/api/ppmps', [
            'title' => 'Automated Workflow Testing PPMP',
            'fiscal_year' => '2026',
            'plan_type' => 'FINAL',
            'items' => [
                [
                    'description' => 'Workflow Verification Workstation',
                    'project_type' => 'Goods',
                    'quantity_size' => '2 units',
                    'procurement_mode' => 'Public Bidding',
                    'estimated_budget' => 250000.00,
                    'source_of_fund' => 'General Fund',
                ]
            ]
        ]);

        $createRes->assertStatus(201);
        $ppmpUuid = $createRes->json('ppmp.uuid');
        $this->assertEquals('DRAFT', $createRes->json('ppmp.status'));
        $this->assertEquals('TEST-OFFICEGOODS-2026-000001', $createRes->json('ppmp.tracking_number'));

        // Verify End user e-signature exists
        $ppmp = Ppmp::where('uuid', $ppmpUuid)->first();
        $this->assertTrue($ppmp->signatures()->where('role', 'end_user')->exists());

        // 2. End User uploads a private PDF attachment
        Storage::fake('local');
        $pdfContent = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\nxref\n0 2\ntrailer<</Size 2/Root 1 0 R>>\nstartxref\n50\n%%EOF";
        $fakePdf = UploadedFile::fake()->createWithContent('tech_specs.pdf', $pdfContent);

        $uploadRes = $this->actingAs($this->endUser)->postJson("/api/ppmps/{$ppmpUuid}/attachments", [
            'file' => $fakePdf,
        ]);
        $uploadRes->assertStatus(201);
        $attachmentUuid = $uploadRes->json('attachment.uuid');

        // Verify attachment is not public and can be downloaded securely
        $downloadRes = $this->actingAs($this->endUser)->get("/api/ppmps/{$ppmpUuid}/attachments/{$attachmentUuid}/download");
        $downloadRes->assertStatus(200);

        // 3. End user submits to Head
        $submitHeadRes = $this->actingAs($this->endUser)->postJson("/api/ppmps/{$ppmpUuid}/submit-to-head");
        $submitHeadRes->assertStatus(200);
        $this->assertEquals('HEAD_PENDING', $submitHeadRes->json('ppmp.status'));

        // Head must receive before approving
        $this->actingAs($this->headUser)->postJson("/api/ppmps/{$ppmpUuid}/receive")->assertStatus(200);

        // 4. Head Approves PPMP
        $headApproveRes = $this->actingAs($this->headUser)->postJson("/api/ppmps/{$ppmpUuid}/head/approve");
        $headApproveRes->assertStatus(200);
        $this->assertEquals('HEAD_APPROVED', $headApproveRes->json('ppmp.status'));
        $this->assertTrue($ppmp->fresh()->signatures()->where('role', 'head')->exists());

        // 5. End user submits for formal review
        $submitReviewRes = $this->actingAs($this->endUser)->postJson("/api/ppmps/{$ppmpUuid}/submit-for-review");
        $submitReviewRes->assertStatus(200);
        $this->assertEquals('BUDGET_OFFICER_REVIEW', $submitReviewRes->json('ppmp.status'));

        // Budget Officer receives before approving
        $this->actingAs($this->budgetUser)->postJson("/api/ppmps/{$ppmpUuid}/receive")->assertStatus(200);

        // 6. Budget Officer Approves (Initial indicator beside name)
        $budgetApproveRes = $this->actingAs($this->budgetUser)->postJson("/api/ppmps/{$ppmpUuid}/budget/approve");
        $budgetApproveRes->assertStatus(200);
        $this->assertEquals('OPPMO_REVIEW', $budgetApproveRes->json('ppmp.status'));
        $this->assertTrue($ppmp->fresh()->signatures()->where('role', 'budget_officer')->exists());

        // OPPMO receives before approving
        $this->actingAs($this->oppmoUser)->postJson("/api/ppmps/{$ppmpUuid}/receive")->assertStatus(200);

        // 7. OPPMO Approves (Initial indicator beside name)
        $oppmoApproveRes = $this->actingAs($this->oppmoUser)->postJson("/api/ppmps/{$ppmpUuid}/oppmo/approve");
        $oppmoApproveRes->assertStatus(200);
        $this->assertEquals('TWG_REVIEW', $oppmoApproveRes->json('ppmp.status'));
        $this->assertTrue($ppmp->fresh()->signatures()->where('role', 'oppmo')->exists());

        // TWG receives before approving
        $this->actingAs($this->twgUser)->postJson("/api/ppmps/{$ppmpUuid}/receive")->assertStatus(200);

        // 8. TWG Approves (TWG E-Signature indicator)
        $twgApproveRes = $this->actingAs($this->twgUser)->postJson("/api/ppmps/{$ppmpUuid}/twg/approve");
        $twgApproveRes->assertStatus(200);
        $this->assertEquals('READY_TO_PRINT', $twgApproveRes->json('ppmp.status'));
        $this->assertTrue($ppmp->fresh()->signatures()->where('role', 'twg')->exists());

        // Verify all 4 required approval signatures exist
        $freshPpmp = $ppmp->fresh(['signatures', 'routes']);
        $this->assertEquals(5, $freshPpmp->signatures()->count()); // end_user + head + budget + oppmo + twg
        $this->assertTrue($freshPpmp->routes()->where('status', 'READY_TO_PRINT')->exists());
    }

    /**
     * Test Reviewer UPDATE / REMARKS return loop with mandatory remarks
     */
    public function test_reviewer_return_requires_remarks_and_allows_correction(): void
    {
        $ppmp = Ppmp::create([
            'uuid' => (string) Str::uuid(),
            'ppmp_number' => 'PPMP-2026-TEST02',
            'office_id' => $this->office->id,
            'created_by' => $this->endUser->id,
            'title' => 'Correction Loop Test',
            'fiscal_year' => '2026',
            'status' => 'BUDGET_OFFICER_REVIEW',
            'total_budget' => 100000.00,
        ]);

        $item = PpmpItem::create([
            'ppmp_id' => $ppmp->id,
            'item_no' => 1,
            'description' => 'Test Item',
            'estimated_budget' => 100000.00,
        ]);

        // Receive document first
        $this->actingAs($this->budgetUser)->postJson("/api/ppmps/{$ppmp->uuid}/receive")->assertStatus(200);

        // Attempt return without remarks (Must fail validation)
        $failRes = $this->actingAs($this->budgetUser)->postJson("/api/ppmps/{$ppmp->uuid}/budget/return", [
            'remarks' => '',
        ]);
        $failRes->assertStatus(422);

        // Submit valid return with remarks and field correction
        $returnRes = $this->actingAs($this->budgetUser)->postJson("/api/ppmps/{$ppmp->uuid}/budget/return", [
            'remarks' => 'Please lower the ABC in Item 1 to 90,000.',
            'field_changes' => [
                [
                    'item_id' => $item->id,
                    'field_name' => 'estimated_budget',
                    'old_value' => '100000.00',
                    'new_value' => '90000.00',
                ]
            ]
        ]);

        $returnRes->assertStatus(200);
        $this->assertEquals('BUDGET_OFFICER_RETURNED', $returnRes->json('ppmp.status'));

        // Verify change log and updated total
        $this->assertTrue($ppmp->changeLogs()->where('field_name', 'estimated_budget')->exists());
        $this->assertEquals(90000.00, (float)$ppmp->fresh()->total_budget);

        // End user corrects and resubmits for review
        $resubmitRes = $this->actingAs($this->endUser)->postJson("/api/ppmps/{$ppmp->uuid}/submit-for-review");
        $resubmitRes->assertStatus(200);
        $this->assertEquals('BUDGET_OFFICER_REVIEW', $resubmitRes->json('ppmp.status'));
    }

    /**
     * Test unauthorized role access is forbidden
     */
    public function test_unauthorized_role_access_is_forbidden(): void
    {
        $ppmp = Ppmp::create([
            'uuid' => (string) Str::uuid(),
            'ppmp_number' => 'PPMP-2026-TEST03',
            'office_id' => $this->office->id,
            'created_by' => $this->endUser->id,
            'title' => 'Security Forbidden Check',
            'fiscal_year' => '2026',
            'status' => 'HEAD_PENDING',
            'total_budget' => 50000.00,
        ]);

        // End user tries to approve own PPMP as head -> HTTP 403
        $unauthRes = $this->actingAs($this->endUser)->postJson("/api/ppmps/{$ppmp->uuid}/head/approve");
        $unauthRes->assertStatus(403);

        // Budget officer tries to approve TWG endpoint -> HTTP 403
        $unauthRes2 = $this->actingAs($this->budgetUser)->postJson("/api/ppmps/{$ppmp->uuid}/twg/approve");
        $unauthRes2->assertStatus(403);
    }

    /**
     * Test explicit receive endpoint for Head, Reviewers, and End User
     */
    public function test_explicit_receive_document_endpoint(): void
    {
        $ppmp = Ppmp::create([
            'uuid' => (string) Str::uuid(),
            'ppmp_number' => 'PPMP-2026-RECEIVE-01',
            'office_id' => $this->office->id,
            'created_by' => $this->endUser->id,
            'title' => 'Receive Tracking Test',
            'fiscal_year' => '2026',
            'status' => 'HEAD_PENDING',
            'head_submitted_at' => now(),
            'total_budget' => 60000.00,
        ]);

        // 1. Head receives document
        $this->assertNull($ppmp->fresh()->head_received_at);
        $headReceiveRes = $this->actingAs($this->headUser)->postJson("/api/ppmps/{$ppmp->uuid}/receive");
        $headReceiveRes->assertStatus(200);
        $this->assertNotNull($ppmp->fresh()->head_received_at);

        // Head approves
        $this->actingAs($this->headUser)->postJson("/api/ppmps/{$ppmp->uuid}/head/approve")->assertStatus(200);

        // End user submits for review
        $this->actingAs($this->endUser)->postJson("/api/ppmps/{$ppmp->uuid}/submit-for-review")->assertStatus(200);
        $this->assertEquals('BUDGET_OFFICER_REVIEW', $ppmp->fresh()->status);
        $this->assertNull($ppmp->fresh()->budget_received_at);

        // 2. Budget Officer receives document
        $budgetReceiveRes = $this->actingAs($this->budgetUser)->postJson("/api/ppmps/{$ppmp->uuid}/receive");
        $budgetReceiveRes->assertStatus(200);
        $this->assertNotNull($ppmp->fresh()->budget_received_at);

        // Budget Officer returns document with remarks
        $this->actingAs($this->budgetUser)->postJson("/api/ppmps/{$ppmp->uuid}/budget/return", [
            'remarks' => 'Please revise item quantity and fund source.',
        ])->assertStatus(200);

        $this->assertEquals('BUDGET_OFFICER_RETURNED', $ppmp->fresh()->status);
        $this->assertNull($ppmp->fresh()->enduser_received_at);

        // 3. End user receives returned document
        $endUserReceiveRes = $this->actingAs($this->endUser)->postJson("/api/ppmps/{$ppmp->uuid}/receive");
        $endUserReceiveRes->assertStatus(200);
        $this->assertNotNull($ppmp->fresh()->enduser_received_at);
    }
}
