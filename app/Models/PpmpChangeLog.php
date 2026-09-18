<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PpmpChangeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ppmp_id',
        'user_id',
        'role',
        'field_name',
        'old_value',
        'new_value',
        'action',
        'review_id',
    ];

    public function ppmp()
    {
        return $this->belongsTo(Ppmp::class, 'ppmp_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
