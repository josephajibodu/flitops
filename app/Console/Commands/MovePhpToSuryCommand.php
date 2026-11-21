<?php

namespace App\Console\Commands;

use App\Actions\ServerPhp\MovePhpToSuryRepository;
use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Console\Command;

class MovePhpToSuryCommand extends Command
{
    protected $signature = 'flitops:php:move-to-sury
        {--server=* : Specific server ID(s) to target}
        {--all : Apply to every active server with PHP}';

    protected $description = 'Move already-provisioned servers from the frozen ondrej/php PPA to packages.sury.org.';

    public function handle(MovePhpToSuryRepository $action): int
    {
        $serverIds = $this->option('server');

        if (empty($serverIds) && ! $this->option('all')) {
            $this->components->error('Pass --server=ID (repeatable) or --all.');

            return self::FAILURE;
        }

        $servers = $this->option('all')
            ? Server::query()->where('status', ServerStatus::Active)->whereNotNull('php_version')->get()
            : Server::query()->whereIn('id', $serverIds)->whereNotNull('php_version')->get();

        if ($servers->isEmpty()) {
            $this->components->warn('No matching servers found.');

            return self::SUCCESS;
        }

        $this->components->warn('PHP-FPM restarts for a few seconds on each server that still has PHP from the old PPA.');

        $failures = 0;

        foreach ($servers as $server) {
            try {
                $this->components->task("Server {$server->id} ({$server->name})", function () use ($server, $action) {
                    $action->execute($server);

                    return true;
                });
            } catch (\Throwable $e) {
                $failures++;
                $this->components->error("Server {$server->id}: {$e->getMessage()}");
            }
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
