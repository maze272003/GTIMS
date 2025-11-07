<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewLoginNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $ipAddress;

    public function __construct($ipAddress)
    {
        $this->ipAddress = $ipAddress;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Security Alert] New Login to Your Account',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-login', // Gagawa tayo ng view na ito
        );
    }
}