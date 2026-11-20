<?php

namespace App\Models;

use App\Enums\AdminAction;
use Database\Factories\AdminAuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A record of what a Flitops staff member did in the admin panel. Kept apart
 * from the team activity log, which customers see. Never put secret values in
 * `properties`.
 */
class AdminAuditLog extends Model
{
    /** @use HasFactory<AdminAuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AdminAction::class,
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(User $admin, AdminAction $action, array $properties = [], ?Model $subject = null): self
    {
        return self::create([
            'user_id' => $admin->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
            'ip_address' => request()->ip(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
