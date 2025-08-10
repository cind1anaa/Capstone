<?php
// Test email functionality
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Configure PHPMailer
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'groundzer0.use@gmail.com';  
    $mail->Password   = 'yxsh tpqt havu frle';        
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Debug mode
    $mail->SMTPDebug  = SMTP::DEBUG_SERVER;

    // Recipients
    $mail->setFrom('groundzer0.use@gmail.com', 'Ground Zero Admin');
    $mail->addAddress('test@example.com', 'Test User');
    $mail->addReplyTo('groundzer0.use@gmail.com', 'Ground Zero Admin');

    // Content
    $mail->isHTML(true);
    $mail->Subject = "Test Email - Ground Zero";
    $mail->Body = "
        <p>This is a test email from Ground Zero system.</p>
        <p>If you receive this, the email functionality is working.</p>
        <p>Best regards,<br>Ground Zero Admin Team</p>
    ";

    $mail->send();
    echo "Test email sent successfully!";
} catch (Exception $e) {
    echo "Failed to send test email. Mailer Error: {$mail->ErrorInfo}";
}
?> 