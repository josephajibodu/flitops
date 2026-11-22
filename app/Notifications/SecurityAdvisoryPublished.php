<?php

namespace App\Notifications;

use App\Models\Team;
use App\Services\SecurityAdvisories\SecurityAdvisory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityAdvisoryPublished extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /** Drop the email quietly if its record was deleted before it was sent. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Team $team,
        public SecurityAdvisory $advisory,
        public int $serverCount,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject("Security update for your servers: {$this->advisory->title()}")
            ->line("{$this->serversPhrase()} in **{$this->team->name}** may be affected by a security issue.")
            ->line($this->advisory->summary())
            ->line('Nothing has been changed on your servers. Open Flitops to check each server: we\'ll tell you whether it\'s already protected, and if not, you can let Flitops install the fix or follow our step-by-step guide.')
            ->action('Check my servers', $this->url());
    }

    /**
     * Stored for the alert banner, which shows it on every page until it is
     * dismissed or every affected server in the team is protected.
     *
     * @return array{title: string, message: string, url: string, target: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Security update: {$this->advisory->title()}",
            'message' => "{$this->serversPhrase()} may be affected. Check them, then fix in one click or follow our guide.",
            'url' => $this->url(),
            'target' => self::targetKey($this->team, $this->advisory->key()),
        ];
    }

    /**
     * Identifies the team and advisory, so the alert clears once every
     * affected server is protected.
     */
    public static function targetKey(Team $team, string $advisoryKey): string
    {
        return "security_advisory:{$advisoryKey}:team:{$team->id}";
    }

    private function url(): string
    {
        return route('security-updates.index', $this->team);
    }

    private function serversPhrase(): string
    {
        return $this->serverCount === 1 ? '1 of your servers' : "{$this->serverCount} of your servers";
    }
}
