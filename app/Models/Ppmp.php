<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ppmp extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'parent_id',
        'tracking_number',
        'ppmp_number',
        'office_id',
        'implementing_unit',
        'created_by',
        'title',
        'account_code',
        'fiscal_year',
        'plan_type',
        'is_annual',
        'delivery_period',
        'place_of_delivery',
        'payment_method',
        'warranty_and_other_terms',
        'attachment_list_data',
        'app_data',
        'total_budget',
        'status',
        'amendment_status',
        'amendment_type',
        'amendment_scope',
        'amendment_reason',
        'requested_amendment_type',
        'requested_amendment_scope',
        'requested_amendment_reason',
        'amendment_requested_at',
        'amendment_approved_at',
        'prepared_at',
        'head_submitted_at',
        'head_approved_at',
        'review_submitted_at',
        'budget_received_at',
        'budget_approved_at',
        'oppmo_received_at',
        'oppmo_approved_at',
        'twg_received_at',
        'twg_approved_at',
        'ready_to_print_at',
        'head_received_at',
        'enduser_received_at',
        'admin_received_at',
    ];

    protected $casts = [
        'is_annual' => 'boolean',
        'attachment_list_data' => 'array',
        'app_data' => 'array',
        'total_budget' => 'decimal:2',
        'amendment_requested_at' => 'datetime',
        'amendment_approved_at' => 'datetime',
        'prepared_at' => 'datetime',
        'head_submitted_at' => 'datetime',
        'head_received_at' => 'datetime',
        'head_approved_at' => 'datetime',
        'review_submitted_at' => 'datetime',
        'budget_received_at' => 'datetime',
        'budget_approved_at' => 'datetime',
        'oppmo_received_at' => 'datetime',
        'oppmo_approved_at' => 'datetime',
        'twg_received_at' => 'datetime',
        'twg_approved_at' => 'datetime',
        'ready_to_print_at' => 'datetime',
        'enduser_received_at' => 'datetime',
        'admin_received_at' => 'datetime',
    ];

    protected $appends = ['default_signatories'];

    public function getDefaultSignatoriesAttribute(): array
    {
        static $cachedSignatories = null;
        if ($cachedSignatories === null) {
            $signatories = \App\Models\PpmpSignatory::where('is_active', true)->get();
            $budget = $signatories->firstWhere('signatory_type', 'budget_requirement');
            $bac = $signatories->firstWhere('signatory_type', 'bac_secretariat');
            $gov = $signatories->firstWhere('signatory_type', 'approved_by');

            $cachedSignatories = [
                'budget_requirement' => [
                    'name' => $budget ? $budget->name : 'DESSAMIE BUAT-SANCHEZ, CPA, JD',
                    'position' => $budget ? $budget->position : 'PGDH - PBO / BAC - Chairman',
                ],
                'bac_secretariat' => [
                    'name' => $bac ? $bac->name : 'NORJANNA M. CAMAGUIN, MPA',
                    'position' => $bac ? $bac->position : 'PGDH - OPPMO',
                ],
                'approved_by' => [
                    'name' => $gov ? $gov->name : 'HON. YVONNE R. CAGAS',
                    'position' => $gov ? $gov->position : 'Provincial Governor',
                ],
            ];
        }
        return $cachedSignatories;
    }

    public function parent()
    {
        return $this->belongsTo(Ppmp::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Ppmp::class, 'parent_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(PpmpItem::class, 'ppmp_id');
    }

    public function attachments()
    {
        return $this->hasMany(PpmpAttachment::class, 'ppmp_id');
    }

    public function reviews()
    {
        return $this->hasMany(PpmpReview::class, 'ppmp_id');
    }

    public function signatures()
    {
        return $this->hasMany(PpmpSignature::class, 'ppmp_id');
    }

    public function routes()
    {
        return $this->hasMany(PpmpRoute::class, 'ppmp_id');
    }

    public function changeLogs()
    {
        return $this->hasMany(PpmpChangeLog::class, 'ppmp_id');
    }

    public function notifications()
    {
        return $this->hasMany(SystemNotification::class, 'ppmp_id');
    }
}
