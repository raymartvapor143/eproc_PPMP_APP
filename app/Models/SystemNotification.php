<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ppmp_id',
        'type',
        'title',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ppmp()
    {
        return $this->belongsTo(Ppmp::class, 'ppmp_id');
    }

    public static function notify(int $userId, string $title, string $message, ?int $ppmpId = null, string $type = 'info'): self
    {
        return self::create([
            'user_id' => $userId,
            'ppmp_id' => $ppmpId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);
    }
}
