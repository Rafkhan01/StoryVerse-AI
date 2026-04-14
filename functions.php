<?php
use PHPMailer\PHPMailer\PHPMailer;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function sendOtpEmail($toEmail, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'williambutcher616@gmail.com';
        $mail->Password = 'ifqbkhthdqeopoem'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('your-email@gmail.com', 'Story Prediction');
        $mail->addAddress($toEmail);
        $mail->Subject = "Your Verification Code";
        $mail->Body    = "Your OTP code is: <br>$otp<br>";

        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}
?>