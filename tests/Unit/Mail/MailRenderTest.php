<?php

namespace Tests\Unit\Mail;

use Tests\TestCase;
use App\Mail\SendOtpMail;
use App\Mail\NewLoginNotification;

class MailRenderTest extends TestCase
{
    public function test_send_otp_mail_renders_with_otp_code()
    {
        $otp = '123456';
        $mailable = new SendOtpMail($otp);

        $mailable->assertSeeInHtml($otp);
        $mailable->assertSeeInHtml('Your One-Time Password');
        $mailable->assertSeeInHtml('5 minutes');
        $mailable->assertDontSeeInHtml('rawPassword');
        $mailable->assertDontSeeInHtml('verificationUrl');
    }

    public function test_send_otp_mail_has_correct_subject()
    {
        $mailable = new SendOtpMail('123456');

        $mailable->assertHasSubject('Your One-Time Password (OTP) for Login');
    }

    public function test_new_login_notification_renders()
    {
        $mailable = new NewLoginNotification('192.168.1.1');

        $mailable->assertSeeInHtml('192.168.1.1');
        $mailable->assertSeeInHtml('New Login Detected');
    }
}
