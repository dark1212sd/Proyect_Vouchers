<?php
// public/procesar_pago.php
session_start();

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Fallo técnico interno: ' . $error['message'] . ' (Línea ' . $error['line'] . ')'
        ]);
    }
});

require __DIR__ . '/config/db.php';
require __DIR__ . '/config/mailer.php';

$response = ['status' => 'error', 'message' => 'Método no permitido'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
            throw new Exception("La imagen es demasiado pesada para el servidor. Por favor sube una captura más liviana (máx. 2MB).");
        }

        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Sesión expirada. Por favor inicia sesión nuevamente.");
        }

        $userId = $_SESSION['user_id'];

        $usuario = $db->usuarios->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
        if (!$usuario && isset($_SESSION['username'])) {
            $usuario = $db->usuarios->findOne(['username' => $_SESSION['username']]);
        }

        $cedula  = !empty($_POST['cedula']) ? htmlspecialchars($_POST['cedula']) : ($usuario['cedula'] ?? 'S/C');
        $nombre  = $usuario['nombre'] ?? $_SESSION['username'];
        $correoUsuario = $usuario['email'] ?? $usuario['correo'] ?? null;

        if (empty($_POST['monto']) || empty($_POST['metodo_pago'])) {
            throw new Exception("El monto y el método de pago son obligatorios.");
        }

        $monto       = floatval($_POST['monto']);
        $metodo_pago = htmlspecialchars(trim($_POST['metodo_pago']));

        if ($monto <= 0) { throw new Exception("El monto debe ser mayor a cero."); }

        $referencia       = (!empty($_POST['referencia'])) ? htmlspecialchars(trim($_POST['referencia'])) : 'N/A';
        $correo_remitente = (!empty($_POST['correo_remitente'])) ? htmlspecialchars(trim($_POST['correo_remitente'])) : null;
        $divisa           = $_POST['divisa'] ?? 'bs';
        $fecha_pago       = !empty($_POST['fecha_pago']) ? htmlspecialchars(trim($_POST['fecha_pago'])) : date('Y-m-d');

        // ===============================================================
        // REGLA DE NEGOCIO: VALIDACIÓN DE CORREO Y REF PARA ZELLE / PAYPAL
        // ===============================================================
        if (in_array($metodo_pago, ['zelle', 'paypal'])) {
            if (empty($correo_remitente)) {
                throw new Exception("Para pagos con " . strtoupper($metodo_pago) . " es obligatorio indicar el Correo Remitente desde donde se envió el dinero.");
            }
            if ($referencia === 'N/A' || empty($referencia)) {
                throw new Exception("Debes colocar el ID o Número de Transacción de tu recibo de " . strtoupper($metodo_pago) . ".");
            }
        }

        $plataforma = !empty($_POST['plataforma']) ? htmlspecialchars(trim($_POST['plataforma'])) : strtoupper(str_replace('_', ' ', $metodo_pago));
        $nombre_archivo = null; $extension = null; $ruta_db = null;

        // Procesar Archivo
        if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            $info_archivo = pathinfo($_FILES['comprobante']['name']);
            $extension    = strtolower($info_archivo['extension']);

            if (!in_array($extension, $extensiones_permitidas)) {
                throw new Exception("Formato no permitido. Solo se aceptan imágenes (JPG, PNG, WEBP) o PDF.");
            }

            $nombre_archivo = "voucher_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $extension;
            $carpeta_destino = __DIR__ . "/uploads/vouchers/";
            if (!is_dir($carpeta_destino)) mkdir($carpeta_destino, 0755, true);

            if (!move_uploaded_file($_FILES['comprobante']['tmp_name'], $carpeta_destino . $nombre_archivo)) {
                throw new Exception("No se pudo guardar la imagen en el servidor.");
            }
            $ruta_db = "/uploads/vouchers/" . $nombre_archivo;
        } elseif ($metodo_pago !== 'efectivo') {
            throw new Exception("El soporte digital es obligatorio para validar tu pago NoSQL.");
        }

        // Evitar duplicados
        if ($referencia !== 'N/A' && !empty($referencia)) {
            $existePago = $db->pagos->findOne(['referencia' => $referencia]);
            if ($existePago) throw new Exception("La referencia o ID N° {$referencia} ya está registrada en el sistema.");
        }

        $documento = [
            'user_id'             => (string)$userId,
            'cedula_vecino'       => $cedula,
            'nombre'              => $nombre,
            'monto'               => new MongoDB\BSON\Decimal128((string)$monto),
            'metodo_pago'         => $metodo_pago,
            'referencia'          => $referencia,
            'referencia_bancaria' => $referencia,
            'fecha_pago'          => $fecha_pago,
            'estatus'             => 'pendiente',
            'estado'              => 'En Revisión',
            'fecha_declaracion'   => new MongoDB\BSON\UTCDateTime(),
            'created_at'          => new MongoDB\BSON\UTCDateTime()
        ];

        if ($correo_remitente) {
            $documento['correo_remitente'] = $correo_remitente;
        }

        if ($metodo_pago === 'efectivo') {
            $documento['divisa'] = strtoupper($divisa);
        } else {
            $documento['plataforma'] = $plataforma;
            $documento['banco']      = $plataforma;
        }

        if ($ruta_db) {
            $documento['soporte_url'] = $ruta_db;
            $documento['archivo']     = $ruta_db;
        }

        $db->vouchers->insertOne($documento);
        $resultado = $db->pagos->insertOne($documento);

        // Envío de Correo de Notificación al Reportar
        $notaCorreo = "";
        if (!empty($correoUsuario)) {
            $montoFormat = number_format($monto, 2, ',', '.');
            $asunto = "Comprobante Recibido - Alianza Victoriosa";
            $html = "
            <div style='font-family: Arial, sans-serif; max-w: 600px; margin: auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 10px;'>
                <h2 style='color: #0ea5e9;'>¡Hemos recibido tu reporte!</h2>
                <p>Hola <strong>{$nombre}</strong>,</p>
                <p>Tu reporte de <strong>" . strtoupper($metodo_pago) . "</strong> con referencia/ID <strong>{$referencia}</strong> por un monto de <strong>Bs. {$montoFormat}</strong> ha entrado en la cola de revisión.</p>
                <p>Te enviaremos tu recibo digital definitivo una vez que el pago sea validado.</p>
            </div>";

            if (enviarCorreoComunal($correoUsuario, $nombre, $asunto, $html)) {
                $notaCorreo = " 📧 Aviso enviado a: <b>" . htmlspecialchars($correoUsuario) . "</b>";
            }
        }

        $response = [
            'status'  => 'success',
            'message' => '¡Voucher enviado a Tesorería con éxito!' . $notaCorreo,
            'id'      => (string)$resultado->getInsertedId()
        ];

    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

echo json_encode($response);
?>