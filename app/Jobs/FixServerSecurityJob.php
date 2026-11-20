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
 * Install a security advisory's fix on a server the customer asked us to
 * fix, then check again so it only counts as protected once the server
 * says so. Never retried on its own: a half-finished package upgrade is
 * for a person to look at.
 */
class FixServerSecurityJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1200;

    /** @var list<string> */
    private array $log = [];

    public function __construct(
        public ServerSecurityCheck $check,
    ) {
        $this->onQueue('provisioning');
    }

    public function handle(SshService $sshService, RecordSecurityCheckAction $record): void
    {
        $advisory = $this->check->securityAdvisory();
        $server = $this->check->server;

        try {
            $connection = $sshService->connect($server);

            try {
                $advisory->fix($connection, $server, fn (string $output) => $this->appendToLog($output, $record));
                $result = $advisory->check($connection, $server);
            } finally {
                $connection->disconnect();
            }
        } catch (Throwable $e) {
            Log::warning('Security fix failed', ['server_security_check_id' => $this->check->id, 'message' => $e->getMessage()]);

            $record->execute($this->check, SecurityCheckStatus::FixFailed, [
                'detail' => $e->getMessage(),
                'output' => $this->logText(),
            ]);

            return;
        }

        $fixWorked = $result->status === SecurityCheckStatus::Covered;

        $record->execute($this->check, $fixWorked ? SecurityCheckStatus::Covered : SecurityCheckStatus::FixFailed, [
            'detail' => $fixWorked ? $result->detail : "The update ran, but the server still isn't protected: {$result->detail}",
            'checked_at' => now(),
            'fixed_at' => $fixWorked ? now() : null,
        ]);
    }

    private function appendToLog(string $output, RecordSecurityCheckAction $record): void
    {
        $this->log[] = $output;

        $record->execute($this->check, SecurityCheckStatus::Fixing, ['output' => $this->logText()]);
    }

    private function logText(): string
    {
        return implode("\n\n", $this->log);
    }
}
