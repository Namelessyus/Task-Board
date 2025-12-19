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
    $mail->Password   = 'bivbpdzbiulhzvyz';       // App Password (16 chars)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // //Recipients
    $mail->setFrom('schoolusecfa@gmail.com', 'TaskBoard');
} catch (Exception $e) {
    echo "Error: {$mail->ErrorInfo}";
}
