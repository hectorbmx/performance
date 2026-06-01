<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyCoachEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Activa tu cuenta de Training Flow')
            ->view('emails.verify-coach-email', [
                'coachName' => $notifiable->name,
                'verificationUrl' => $verificationUrl,
                'expirationMinutes' => config('auth.verification.expire', 60),
            ]);
    }
}
