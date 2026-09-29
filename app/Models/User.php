<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'address',
        'password',
        'role',
        'office_id',
        'designation',
        'signature_path',
        'authorization_letter_path',
        'is_active',
        'approval_status',
        'rejection_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function ppmps()
    {
        return $this->hasMany(Ppmp::class, 'created_by');
    }

    public function notifications()
    {
        return $this->hasMany(SystemNotification::class, 'user_id');
    }

    public function isEndUser(): bool
    {
        return $this->role === 'end_user';
    }

    public function isHead(): bool
    {
        return in_array($this->role, ['head', 'authorized_staff'], true);
    }

    public function isAuthorizedStaff(): bool
    {
        return $this->role === 'authorized_staff';
    }

    public function isBudgetOfficer(): bool
    {
        return $this->role === 'budget_officer';
    }

    public function isOppmo(): bool
    {
        return $this->role === 'oppmo';
    }

    public function isTwg(): bool
    {
        return $this->role === 'twg';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
