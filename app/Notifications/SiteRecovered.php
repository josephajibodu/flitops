<?php

namespace App\Notifications;

use App\Models\SiteIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteRecovered extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /** Drop the email quietly if its record was deleted before it was sent. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public SiteIncident $incident,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = $this->incident->site;
        $server = $site->server;
        $downtime = $this->incident->started_at->diffForHumans($this->incident->resolved_at, true);

        return (new MailMessage)
            ->success()
            ->subject("Site recovered: {$site->domain}")
            ->line("**{$site->domain}** on **{$server->name}** is responding again.")
            ->line("It was down for {$downtime}.")
            ->action('View Site', route('servers.sites.show', [$server->team, $server, $site]));
    }
}
