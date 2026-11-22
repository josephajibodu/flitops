<?php

namespace App\Models;

use App\Enums\FirewallRuleStatus;
use Database\Factories\FirewallRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FirewallRule extends Model
{
    /** @use HasFactory<FirewallRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'server_id',
        'type',
        'protocol',
        'port',
        'source',
        'mask',
        'note',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FirewallRuleStatus::class,
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
