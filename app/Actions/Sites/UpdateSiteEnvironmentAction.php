<?php

namespace App\Actions\Sites;

use App\Enums\ActivityEvent;
use App\Jobs\SyncEnvironmentJob;
use App\Models\ActivityLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a site's .env content: the stored file, the per-key variables and the
 * activity log entry are written together in one transaction, so a failure
 * part way never leaves them disagreeing. The file is then pushed to the
 * server by a queued job.
 */
class UpdateSiteEnvironmentAction
{
    /**
     * @throws ValidationException when a line isn't a valid KEY=value pair.
     */
    public function execute(Site $site, string $envContent, ?User $user, bool $clearConfigCache = false, bool $restartQueue = false): void
    {
        $variables = $this->parseEnvContent($envContent);

        DB::transaction(function () use ($site, $envContent, $variables, $user): void {
            $this->recordActivity($site, $envContent, $variables, $user);

            $site->update(['env_content' => $envContent]);

            $site->environmentVariables()->whereNotIn('key', array_keys($variables))->delete();

            foreach ($variables as $key => $value) {
                $site->environmentVariables()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        SyncEnvironmentJob::dispatch($site, $clearConfigCache, $restartQueue);
    }

    /**
     * Record in the team activity log who saved the environment and which
     * keys the save added, changed or removed (names only, never values).
     * Saving the same content again records nothing. When FlitOps has never
     * stored this site's environment (it was only read from the server), the
     * earlier file is unknown, so the entry is marked as a first save with no
     * key breakdown.
     *
     * @param  array<string, string>  $newVariables
     */
    private function recordActivity(Site $site, string $newContent, array $newVariables, ?User $user): void
    {
        $oldContent = $site->env_content;

        if ($oldContent === null) {
            $storedVariables = $site->environmentVariables()->get();

            if ($storedVariables->isNotEmpty()) {
                $oldContent = $storedVariables->map(fn ($variable) => "{$variable->key}={$variable->value}")->implode("\n");
            }
        }

        if ($oldContent === $newContent) {
            return;
        }

        $properties = ['first_save' => true, 'added_keys' => [], 'changed_keys' => [], 'removed_keys' => []];

        if ($oldContent !== null) {
            try {
                $oldVariables = $this->parseEnvContent($oldContent);
            } catch (ValidationException) {
                $oldVariables = [];
            }

            $properties = [
                'first_save' => false,
                'added_keys' => array_keys(array_diff_key($newVariables, $oldVariables)),
                'changed_keys' => array_keys(array_filter(
                    array_intersect_key($newVariables, $oldVariables),
                    fn (string $value, string $key): bool => $oldVariables[$key] !== $value,
                    ARRAY_FILTER_USE_BOTH,
                )),
                'removed_keys' => array_keys(array_diff_key($oldVariables, $newVariables)),
            ];
        }

        ActivityLog::record($site->server->team, ActivityEvent::SiteEnvironmentUpdated, $site, $properties, $user);
    }

    /**
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    private function parseEnvContent(string $envContent): array
    {
        $variables = [];
        $lines = preg_split('/\r\n|\r|\n/', $envContent) ?: [];

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
                continue;
            }

            $delimiterIndex = strpos($line, '=');
            if ($delimiterIndex === false) {
                throw ValidationException::withMessages([
                    'env_content' => 'Line '.($index + 1).' is invalid. Use KEY=value format.',
                ]);
            }

            $key = trim(substr($line, 0, $delimiterIndex));
            $value = ltrim(substr($line, $delimiterIndex + 1));

            if ($key === '' || ! preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
                throw ValidationException::withMessages([
                    'env_content' => 'Line '.($index + 1).' has an invalid key. Keys must start with a letter or underscore and contain only letters, numbers, and underscores.',
                ]);
            }

            if (strlen($value) > 65535) {
                throw ValidationException::withMessages([
                    'env_content' => 'Line '.($index + 1).' value is too long.',
                ]);
            }

            $variables[$key] = $value;
        }

        return $variables;
    }
}
