<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'head_user_id',
        'head_name',
        'designation',
        'responsibility_number',
    ];

    public function head()
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'office_id');
    }

    public function ppmps()
    {
        return $this->hasMany(Ppmp::class, 'office_id');
    }
}
