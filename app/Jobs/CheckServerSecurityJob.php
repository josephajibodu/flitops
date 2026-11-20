<?php

namespace App\Jobs;

use App\Actions\SecurityAdvisories\RecordSecurityCheckAction;
use App\Enums\SecurityCheckStatus;
use App\Models\ServerSecurityCheck;
use App\Services\Ssh\SshService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Check, without changing anything, whether a server is protected against a
 * security advisory.
 */
class CheckServerSecurityJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public ServerSecurityCheck $check,
    ) {
        $this->onQueue('ssh');
    }

    public function handle(SshService $sshService, RecordSecurityCheckAction $record): void
    {
        try {
            $connection = $sshService->connect($this->check->server);

            try {
                $result = $this->check->securityAdvisory()->check($connection, $this->check->server);
            } finally {
                $connection->disconnect();
            }

            $record->execute($this->check, $result->status, [
                'detail' => $result->detail,
                'checked_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Security check failed', ['server_security_check_id' => $this->check->id, 'message' => $e->getMessage()]);

            $record->execute($this->check, SecurityCheckStatus::CheckFailed, [
                'detail' => "We couldn't check this server: {$e->getMessage()}",
                'checked_at' => now(),
            ]);
        }
    }
}
