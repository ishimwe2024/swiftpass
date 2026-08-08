<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

function sendOtpEmail($toEmail, $toName, $otp)
{
    $mail = new PHPMailer(true);

    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ruzigamanzi2003@gmail.com';
        $mail->Password   = 'vpvh ecye gnyo taer';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('no-reply@urugendo.com', 'Urugendo Support');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Your password reset OTP';
        $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $mail->Body    = "<p>Hello <strong>{$safeName}</strong>,</p>\n<p>Your password reset OTP is <strong>{$otp}</strong>.</p>\n<p>This code expires in 10 minutes.</p>\n<p>If you did not request a password reset, please ignore this email.</p>";
        $mail->AltBody = "Hello {$toName},\n\nYour password reset OTP is {$otp}.\nThis code expires in 10 minutes.\n\nIf you did not request a password reset, please ignore this email.";

        return $mail->send();
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}
