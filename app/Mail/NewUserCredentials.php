<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewUserCredentials extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $rawPassword;
    public $verificationUrl; // <--- Bagong Property

    // Update Constructor
    public function __construct($user, $rawPassword, $verificationUrl)
    {
        $this->user = $user;
        $this->rawPassword = $rawPassword;
        $this->verificationUrl = $verificationUrl; // <--- Assign value
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Action Required: Verify Your Account & Login Details');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.new_user_credentials');
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}