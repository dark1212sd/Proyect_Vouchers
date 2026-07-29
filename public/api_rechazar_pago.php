<?php

// public/api_rechazar_pago.php
session_start();

// Bloquear cualquier salida de HTML o errores para que no rompa el JSON
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

// Verificar permisos de admin
$rol = $_SESSION['role'] ?? $_SESSION['rol'] ?? 'user';
if (!isset($_SESSION['user_id']) || !in_array($rol, ['admin', 'superuser'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido']);
    exit();
}

// Usamos Throwable para atrapar absolutamente cualquier error crítico de PHP 7/8
try {
    $pago_id = $_POST['pago_id'] ?? null;
    $motivo = trim($_POST['motivo_rechazo'] ?? 'El comprobante no pudo ser procesado.');

    if (!$pago_id) {
        echo json_encode(['status' => 'error', 'message' => 'ID de pago no proporcionado']);
        exit();
    }

    // 1. Parseo Seguro del ID del PAGO
    try {
        $objId = new MongoDB\BSON\ObjectId($pago_id);
    } catch (Exception $e) {
        $objId = $pago_id;
    }

    $pago = $db->pagos->findOne(['_id' => $objId]);
    if (!$pago) {
        echo json_encode(['status' => 'error', 'message' => 'Pago no encontrado en la base de datos']);
        exit();
    }

    $userId = (string)($pago['user_id'] ?? '');

    // 2. Parseo Seguro del ID del USUARIO (Aquí estaba el error)
    try {
        $userObjId = new MongoDB\BSON\ObjectId($userId);
    } catch (Exception $e) {
        $userObjId = $userId;
    }

    // Buscar al usuario por ObjectId o por String
    $usuario = $db->usuarios->findOne(['_id' => $userObjId]);
    if (!$usuario) {
        $usuario = $db->usuarios->findOne(['_id' => $userId]);
    }

    $correo_residente = $usuario['email'] ?? $usuario['correo'] ?? null;
    $nombre_residente = $usuario['nombre'] ?? 'Residente';

    // 3. Procesar Imagen de evidencia (Opcional)
    $url_evidencia = null;
    if (isset($_FILES['imagen_evidencia']) && $_FILES['imagen_evidencia']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['imagen_evidencia'];
        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $dir = __DIR__ . '/uploads/rechazos/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $nombreArchivo = "evidencia_{$pago_id}_" . time() . ".$ext";
            if (move_uploaded_file($archivo['tmp_name'], $dir . $nombreArchivo)) {
                $url_evidencia = "uploads/rechazos/" . $nombreArchivo;
            }
        }
    }

    // 4. Actualizar MongoDB
    $datosActualizar = [
        'estado' => 'rechazado',
        'estatus' => 'rechazado',
        'motivo_rechazo' => $motivo,
        'fecha_auditoria' => new MongoDB\BSON\UTCDateTime()
    ];

    if ($url_evidencia) {
        $datosActualizar['imagen_rechazo'] = $url_evidencia;
    }

    $db->pagos->updateOne(['_id' => $objId], ['$set' => $datosActualizar]);

    // ===============================================
    // 5. ENVÍO DE CORREO AL RESIDENTE
    // ===============================================
    if ($correo_residente) {
        $rastreo = $pago['numero_rastreo'] ?? 'Desconocido';
        $asunto = "Tu pago ($rastreo) ha sido rechazado - Condominio";
        $html = "
        <html>
        <body style='font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;'>
            <div style='max-w: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 10px; border-top: 5px solid #ef4444;'>
                <h2 style='color: #111827;'>Hola, $nombre_residente</h2>
                <p style='color: #4b5563; font-size: 16px;'>Te informamos que tu declaración de pago con número de rastreo <strong>$rastreo</strong> ha sido auditada y su estatus cambió a <strong style='color:#ef4444;'>RECHAZADO</strong>.</p>
                
                <div style='background: #fef2f2; border: 1px solid #f87171; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='margin-top: 0; color: #b91c1c;'>Mensaje de Administración:</h4>
                    <p style='color: #991b1b; margin-bottom: 0;'>$motivo</p>
                </div>
                
                <p style='color: #4b5563; font-size: 14px;'>Por favor, ingresa a tu panel de residente para revisar los detalles (y la evidencia adjunta si aplica) y vuelve a generar el reporte con los datos corregidos.</p>
            </div>
        </body>
        </html>
        ";

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Administracion <no-reply@condominio.com>\r\n";

        // Usamos @ para evitar que un fallo en el servidor local de correos rompa el script
        @mail($correo_residente, $asunto, $html, $headers);
    }

    echo json_encode(['status' => 'success', 'message' => 'Pago rechazado y residente notificado correctamente.']);

} catch (Throwable $e) {
    // Throwable captura Error y Exception (Evitando la pantalla blanca de muerte en PHP)
    echo json_encode(['status' => 'error', 'message' => 'Error Interno: ' . $e->getMessage()]);
}
