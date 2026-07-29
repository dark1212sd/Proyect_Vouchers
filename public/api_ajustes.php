<?php
// public/api_ajustes.php
session_start();

// Desactivar errores en pantalla para no romper la respuesta JSON
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

// Configurar encabezado para devolver siempre JSON
header('Content-Type: application/json; charset=utf-8');

// 1. Verificación de Seguridad: Solo Administradores
$rol = $_SESSION['role'] ?? $_SESSION['rol'] ?? 'user';
if (!isset($_SESSION['user_id']) || !in_array($rol, ['admin', 'superuser'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Acceso denegado. Permisos insuficientes.'
    ]);
    exit();
}

require_once __DIR__ . '/config/db.php';

// Validar que la petición sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Método HTTP no permitido.'
    ]);
    exit();
}

try {
    // ========================================================
    // 2. RECOPILACIÓN DE DATOS DEL FORMULARIO
    // ========================================================

    // Parámetros Financieros y Globales
    $cuota_base = floatval($_POST['cuota_base'] ?? 35.00);
    $dia_corte  = trim($_POST['dia_corte'] ?? '5');
    $auditoria_estricta = (isset($_POST['auditoria_estricta']) && $_POST['auditoria_estricta'] === '1');
    $modo_mantenimiento = (isset($_POST['modo_mantenimiento']) && $_POST['modo_mantenimiento'] === '1');
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_user = trim($_POST['smtp_user'] ?? '');
    $smtp_pass = trim($_POST['smtp_pass'] ?? '');

    // Cuentas Recaudadoras
    $zelle_email     = trim($_POST['zelle_email'] ?? '');
    $paypal_email    = trim($_POST['paypal_email'] ?? '');
    $pm_banco        = trim($_POST['pm_banco'] ?? '');
    $pm_telefono     = trim($_POST['pm_telefono'] ?? '');
    $pm_cedula       = trim($_POST['pm_cedula'] ?? '');
    $transfer_banco  = trim($_POST['transfer_banco'] ?? '');
    $transfer_cuenta = trim($_POST['transfer_cuenta'] ?? '');
    $transfer_nombre = trim($_POST['transfer_nombre'] ?? '');
    $transfer_rif    = trim($_POST['transfer_rif'] ?? '');

    // ========================================================
    // 3. PROCESAMIENTO DEL CÓDIGO QR (SUBIDA DE IMAGEN)
    // ========================================================
    $qr_url = null;

    if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['qr_image'];
        $extPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        // Validación de formato y peso (Máximo 2MB)
        if (in_array($extension, $extPermitidas)) {
            if ($archivo['size'] <= (2 * 1024 * 1024)) {
                $directorioDestino = __DIR__ . '/uploads/config/';

                // Crear carpeta si no existe
                if (!is_dir($directorioDestino)) {
                    mkdir($directorioDestino, 0755, true);
                }

                // Generar nombre único para evitar caché
                $nombreArchivo = "qr_pagos_" . time() . "." . $extension;
                $rutaFisica = $directorioDestino . $nombreArchivo;

                if (move_uploaded_file($archivo['tmp_name'], $rutaFisica)) {
                    $qr_url = "uploads/config/" . $nombreArchivo;
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'No se pudo guardar la imagen del QR en el servidor.'
                    ]);
                    exit();
                }
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'La imagen del QR supera el límite de 2MB.'
                ]);
                exit();
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'El QR debe ser formato JPG, PNG o WEBP.'
            ]);
            exit();
        }
    }

    // ========================================================
    // 4. ACTUALIZAR MONGODB (UPSERT: Crea o Actualiza)
    // ========================================================
    $fechaActual = new MongoDB\BSON\UTCDateTime();

    // Actualizar Documento 1: 'global'
    $db->configuracion->updateOne(
        ['tipo' => 'global'],
        ['$set' => [
            'cuota_base'         => $cuota_base,
            'dia_corte'          => $dia_corte,
            'auditoria_estricta' => $auditoria_estricta,
            'modo_mantenimiento' => $modo_mantenimiento,
            'updated_at'         => $fechaActual
        ]],
        ['upsert' => true]
    );

    // Preparar Documento 2: 'cuentas_recaudacion'
    $datosCuentas = [
        'zelle_email'     => $zelle_email,
        'paypal_email'    => $paypal_email,
        'pm_banco'        => $pm_banco,
        'pm_telefono'     => $pm_telefono,
        'pm_cedula'       => $pm_cedula,
        'transfer_banco'  => $transfer_banco,
        'transfer_cuenta' => $transfer_cuenta,
        'transfer_nombre' => $transfer_nombre,
        'transfer_rif'    => $transfer_rif,
        'updated_at'      => $fechaActual
    ];

    // Solo actualizamos la URL del QR si el admin subió uno nuevo
    if ($qr_url !== null) {
        $datosCuentas['qr_url'] = $qr_url;
    }

    $db->configuracion->updateOne(
        ['tipo' => 'cuentas_recaudacion'],
        ['$set' => $datosCuentas],
        ['upsert' => true]
    );

    // Respuesta de éxito para que el AJAX lo lea
    echo json_encode([
        'status' => 'success',
        'message' => '¡Los ajustes del sistema han sido guardados correctamente!'
    ]);

} catch (Exception $e) {
    // Capturar errores críticos de BD o servidor
    echo json_encode([
        'status' => 'error',
        'message' => 'Error de Base de Datos: ' . $e->getMessage()
    ]);
}
?>