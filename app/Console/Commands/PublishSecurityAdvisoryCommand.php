<?php

namespace App\Console\Commands;

use App\Actions\SecurityAdvisories\PublishSecurityAdvisoryAction;
use App\Services\SecurityAdvisories\SecurityAdvisoryCatalogue;
use Illuminate\Console\Command;

class PublishSecurityAdvisoryCommand extends Command
{
    protected $signature = 'flitops:security:publish
        {advisory : The advisory key, e.g. 2026-11-nginx2root}';

    protected $description = 'Show a security advisory to every team with a server it may affect, by email and a dashboard banner. Nothing runs on any server until the customer asks.';

    public function handle(SecurityAdvisoryCatalogue $catalogue, PublishSecurityAdvisoryAction $publish): int
    {
        $key = $this->argument('advisory');

        if (! $catalogue->has($key)) {
            $this->components->error("No security advisory [{$key}].");

            return self::FAILURE;
        }

        $result = $publish->execute($catalogue->find($key));

        $this->components->info("Told {$result['teams']} team(s) about {$result['servers']} newly affected server(s).");

        return self::SUCCESS;
    }
}
