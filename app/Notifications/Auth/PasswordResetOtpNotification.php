<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresInMinutes,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your Adam Travel verification code')
            ->greeting('Reset your password')
            ->line('Use the verification code below to continue resetting your Adam Travel account password.')
            ->line('Verification code: '.$this->code)
            ->line('This code expires in '.$this->expiresInMinutes.' minutes.')
            ->line('If you did not request this code, you can ignore this email.');
    }
}
