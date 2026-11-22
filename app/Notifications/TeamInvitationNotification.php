<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /** Drop the email quietly if its record was deleted before it was sent. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public TeamInvitation $invitation) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = route('team-invitations.accept', $this->invitation->token);

        return (new MailMessage)
            ->subject("You've been invited to join {$this->invitation->team->name}")
            ->line("You have been invited to join the **{$this->invitation->team->name}** team.")
            ->action('Accept Invitation', $acceptUrl)
            ->line('If you did not expect this invitation, you can ignore this email.');
    }
}
