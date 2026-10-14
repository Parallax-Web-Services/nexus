<?php
namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class LaunchTrialReady extends Notification
{
    public function __construct(public string $claimUrl, public int $days, public ?string $setupUrl = null, public bool $existingTrial = false) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        $nextStep = $this->existingTrial
            ? 'Your existing trial keeps its original end date. Sign in to open your saved answer.'
            : ($notifiable->hasVerifiedEmail() ? 'Sign in, then activate your ' : 'Verify this email, then activate your ').$this->days.'-day trial to continue in your workspace.';

        $message = (new MailMessage)->subject('Your Parallax trial and first answer')
            ->greeting('Your question is saved.')
            ->line('Your first answer is being prepared from the brief you submitted. '.$nextStep)
            ->action($this->setupUrl ? 'Set up my account password' : 'Sign in and open my answer', $this->setupUrl ?? $this->claimUrl)
            ->line('No payment card is required. There is no automatic charge when the trial ends.')
            ->line('If you already have a Parallax account, sign in with that account to continue.');
        if ($this->setupUrl) {
            $message->line('After setting your password, sign in and verify your email using the separate Nexus verification email.')
                ->line('[Open your saved answer and start the trial]('.$this->claimUrl.')');
        }
        return $message;
    }
}
