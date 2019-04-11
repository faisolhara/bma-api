<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class Gmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    protected $newPassword;
    

    public function __construct($newPassword)
    {
        //
        $this->newPassword = $newPassword;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('dck.sce@gmail.com')
                    ->subject(trans('common.bma-reset-password'))
                    ->view('mail')
                    ->with([
                        'newPassword' => $this->newPassword,
                    ]);
    }
}
