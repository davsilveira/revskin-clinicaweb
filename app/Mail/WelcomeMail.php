<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $token;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $token)
    {
        $this->user = $user;
        $this->token = $token;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $resetUrl = url('/reset-password/' . $this->token . '?email=' . urlencode($this->user->email));

        return $this->subject('Bem-vindo ao ' . config('app.name') . ' - Defina sua senha')
            ->view('emails.welcome', [
                'user' => $this->user,
                'resetUrl' => $resetUrl,
            ]);
    }
}
