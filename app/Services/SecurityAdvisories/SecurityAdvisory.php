<?php

namespace App\Services\SecurityAdvisories;

use App\Models\Server;
use App\Services\Ssh\SshConnection;

/**
 * A security fix Flitops publishes for customers' servers. Nothing runs on a
 * server until the customer asks: first a check, which only reads, then
 * either the automatic fix or the manual guide, whichever they choose.
 *
 * Each advisory lives in code, so the commands it runs on customers' servers
 * are reviewed and tested like the rest of the app. Add new ones to
 * {@see SecurityAdvisoryCatalogue}.
 */
interface SecurityAdvisory
{
    /**
     * Stable identifier stored against each server, e.g. "2026-11-nginx2root".
     */
    public function key(): string;

    public function title(): string;

    /**
     * "critical" or "high".
     */
    public function severity(): string;

    public function publishedOn(): string;

    /**
     * What the problem is and why it matters, in plain words.
     */
    public function summary(): string;

    /**
     * What happens on the server when Flitops installs the fix, including any
     * moment the sites are unavailable.
     */
    public function fixDescription(): string;

    public function referenceUrl(): ?string;

    /**
     * Whether the server could be affected at all, judged from what Flitops
     * knows without connecting.
     */
    public function appliesTo(Server $server): bool;

    /**
     * Read the server's state without changing anything.
     */
    public function check(SshConnection $connection, Server $server): SecurityCheckResult;

    /**
     * Install the fix. Each step's output is passed to $log as it finishes.
     * Throws when a step fails.
     *
     * @param  callable(string): void  $log
     */
    public function fix(SshConnection $connection, Server $server, callable $log): void;

    /**
     * The steps for customers who would rather fix it themselves.
     *
     * @return list<array{title: string, explanation: string, command: ?string}>
     */
    public function manualSteps(Server $server): array;
}
