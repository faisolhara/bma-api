<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class MailSendToken extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    protected $sendTo;
    protected $resetToken;
    protected $expireResetToken;
    

    public function __construct($sendTo, $resetToken, $expireResetToken)
    {
        //
        $this->sendTo           = $sendTo;
        $this->resetToken       = $resetToken;
        $this->expireResetToken = $expireResetToken;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('dck.sce@gmail.com')
                    ->subject('Request to reset password')
                    ->view('send-token')
                    ->with([
                        'reset_token'        => $this->resetToken,
                        'expire_reset_token' => $this->expireResetToken,
                        'to'                 => $this->sendTo,
                    ]);
    }
}
