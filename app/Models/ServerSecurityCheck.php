<?php

namespace App\Models;

use App\Enums\SecurityCheckStatus;
use App\Services\SecurityAdvisories\SecurityAdvisory;
use App\Services\SecurityAdvisories\SecurityAdvisoryCatalogue;
use Database\Factories\ServerSecurityCheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where one server stands on one published security advisory: whether it has
 * been checked, whether it is protected, and how the last fix went.
 */
class ServerSecurityCheck extends Model
{
    /** @use HasFactory<ServerSecurityCheckFactory> */
    use HasFactory;

    protected $fillable = [
        'server_id',
        'advisory',
        'status',
        'detail',
        'output',
        'checked_at',
        'fixed_by',
        'fixed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SecurityCheckStatus::class,
            'checked_at' => 'datetime',
            'fixed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** @return BelongsTo<User, $this> */
    public function fixedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fixed_by');
    }

    public function securityAdvisory(): SecurityAdvisory
    {
        return app(SecurityAdvisoryCatalogue::class)->find($this->advisory);
    }
}
