<?php
// test_mail.php - Script de Diagnóstico de SMTP (Versión Unificada)

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);

// Apuntamos directamente al único vendor unificado
$rutaAutoload = __DIR__ . '/vendor/autoload.php';

if (!file_exists($rutaAutoload)) {
    die("<div style='background:#990000; color:white; padding:20px;'><b>Error Fatal:</b> No se encontró vendor/autoload.php en public/. Ejecuta 'composer require mongodb/mongodb phpmailer/phpmailer' dentro de la carpeta public.</div>");
}

require_once $rutaAutoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

echo "<div style='font-family: monospace; background: #0f172a; color: #38bdf8; padding: 20px; border-radius: 10px;'>";
echo "<h3>🔍 INICIANDO PRUEBA DE CONEXIÓN CON GMAIL...</h3><hr style='border-color: #334155;'>";

$mail = new PHPMailer(true);

try {
    // ACTIVAR EL MODO DEPURACIÓN
    $mail->SMTPDebug   = SMTP::DEBUG_SERVER;
    $mail->Debugoutput = function($str, $level) {
        echo "<div style='margin-bottom: 4px; color: #94a3b8;'>[DEBUG]: " . htmlspecialchars($str) . "</div>";
    };

    // Configuración del servidor
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;

    // TUS CREDENCIALES
    $mail->Username   = 'leonardo21xyz@gmail.com';
    $mail->Password   = 'sesifnwxeqrtmavw'; // Tu clave sin espacios

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Remitente y Destinatario
    $mail->setFrom($mail->Username, 'Test - Sistema Condominio');
    $mail->addAddress($mail->Username, 'Leonardo Tarazona');

    // Contenido
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = '⚡ ¡La conexión SMTP de tu Tesis funciona!';
    $mail->Body    = '<h2 style="color: #10b981;">¡Éxito Total!</h2><p>Si estás leyendo esto, significa que PHPMailer se comunicó perfectamente con los servidores de Google.</p>';

    echo "<p style='color: #f59e0b;'>Intentando conectar al servidor smtp.gmail.com:587...</p>";

    $mail->send();

    echo "<hr style='border-color: #334155;'>";
    echo "<h3 style='color: #10b981;'>✅ ¡EL CORREO SE ENVIÓ CON ÉXITO!</h3>";
    echo "<p style='color: #e2e8f0;'>Revisa tu bandeja de entrada o tu carpeta de SPAM.</p>";

} catch (Exception $e) {
    echo "<hr style='border-color: #334155;'>";
    echo "<h3 style='color: #f43f5e;'>❌ ERROR AL ENVIAR:</h3>";
    echo "<p style='color: #fca5a5;'>" . htmlspecialchars($mail->ErrorInfo) . "</p>";
}

echo "</div>";
?>