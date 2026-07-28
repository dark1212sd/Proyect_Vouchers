<?php
// public/config/mailer.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// CARGA INTELIGENTE: Si db.php ya abrió Composer y PHPMailer existe, nos saltamos este paso
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    $rutaVendor = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($rutaVendor)) {
        require_once $rutaVendor;
    }
}

function enviarCorreoComunal($destinatario, $nombreDestinatario, $asunto, $cuerpoHTML) {
    // Si por alguna razón la librería no está disponible, aborta en silencio sin romper el JSON
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("Error: La clase PHPMailer no está disponible en el servidor.");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug  = 0; // Silencioso para no interferir con la respuesta JSON
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        // TUS CREDENCIALES OFICIALES
        $mail->Username   = 'leonardo21xyz@gmail.com';
        $mail->Password   = 'sesifnwxeqrtmavw';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($mail->Username, 'Tesorería - Alianza Victoriosa');
        $mail->addAddress($destinatario, $nombreDestinatario);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpoHTML;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error enviando correo a $destinatario: {$mail->ErrorInfo}");
        return false;
    }
}
?>