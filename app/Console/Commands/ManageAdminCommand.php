<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ManageAdminCommand extends Command
{
    protected $signature = 'flitops:admin
        {email : The email address of the staff member\'s Flitops account}
        {--revoke : Take admin access away instead of giving it}';

    protected $description = 'Give a Flitops staff member access to the admin panel, or take it away. They also need two-factor sign-in turned on.';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->components->error("No user with the email [{$this->argument('email')}].");

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();

        if ($this->option('revoke')) {
            $this->components->info("{$user->email} can no longer open the admin panel.");

            return self::SUCCESS;
        }

        $this->components->info("{$user->email} can now open the admin panel at /admin.");

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            $this->components->warn('They need to turn on two-factor sign-in in their settings before the panel will open.');
        }

        return self::SUCCESS;
    }
}
