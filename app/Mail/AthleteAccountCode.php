<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;

class AthleteAccountCode extends Mailable
{
    public function __construct(public string $code, public string $purpose) {}

    public function build(): self
    {
        return $this->subject($this->purpose === 'verify' ? 'Confirma tu cuenta de atleta' : 'Recupera tu cuenta de atleta')
            ->view('emails.athlete-account-code');
    }
}
