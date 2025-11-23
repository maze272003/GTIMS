<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp; // Ginawang public para ma-access sa view

    /**
     * Create a new message instance.
     */
    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your One-Time Password (OTP) for Login',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Gagawa tayo ng view para dito
        return new Content(
            view: 'emails.send-otp', 
        );
    }
}