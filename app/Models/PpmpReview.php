<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PpmpReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'ppmp_id',
        'reviewer_id',
        'reviewer_role',
        'action',
        'remarks',
        'status_before',
        'status_after',
        'submitted_at',
        'received_at',
        'acted_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'received_at' => 'datetime',
        'acted_at' => 'datetime',
    ];

    public function ppmp()
    {
        return $this->belongsTo(Ppmp::class, 'ppmp_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
