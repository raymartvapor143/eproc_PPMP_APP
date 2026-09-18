<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PpmpRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'ppmp_id',
        'from_user_id',
        'to_user_id',
        'from_role',
        'to_role',
        'action',
        'status',
        'remarks',
        'submitted_at',
        'received_at',
        'acted_at',
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

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
