<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    //Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'schoolusecfa@gmail.com';     // Gmail
    $mail->Password   = '';       // App Password (16 chars)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // //Recipients
    $mail->setFrom('schoolusecfa@gmail.com', 'TaskBoard');
    $mail->addAddress('rabinamaharjan392@gmail.com'); // reciever email

    // //Content
    $mail->isHTML(true);
    $mail->Subject = 'Hello from XAMPP + Gmail';
    $mail->Body    = 'This is a test email sent using <b>PHPMailer</b> from XAMPP.';

    $mail->send();
    echo 'Email Sent Successfully!';
} catch (Exception $e) {
    echo "Error: {$mail->ErrorInfo}";
}
