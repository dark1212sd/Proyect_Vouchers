<?php
// public/procesar_pago.php
session_start();

// 1. VERIFICACIÓN DE SESIÓN (Seguridad de Acceso)
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada. Por favor inicia sesión nuevamente.']);
    exit();
}

// Configuración de reporte de errores para devolver siempre JSON limpio
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método de petición no permitido.']);
    exit();
}

try {
    $userId = $_SESSION['user_id'];

    // 2. RECUPERAR Y LIMPIAR DATOS DEL FORMULARIO
    $monto           = floatval($_POST['monto'] ?? 0);
    $metodo          = trim(htmlspecialchars($_POST['metodo_pago'] ?? ''));
    $referencia      = trim(htmlspecialchars($_POST['referencia'] ?? ''));
    $correoRemitente = trim(htmlspecialchars($_POST['correo_remitente'] ?? ''));

    // Validaciones básicas de negocio
    if ($monto <= 0) {
        throw new Exception("El monto declarado debe ser mayor a 0.");
    }
    if (empty($metodo) || empty($referencia)) {
        throw new Exception("El método de pago y el número de referencia son obligatorios.");
    }
    if (in_array(strtolower($metodo), ['zelle', 'paypal']) && empty($correoRemitente)) {
        throw new Exception("Para pagos con Zelle o PayPal debes indicar el correo electrónico del remitente.");
    }

    // 3. VERIFICACIÓN ANTIFRAUDE: EVITAR REFERENCIAS BANCARIAS DUPLICADAS
    $refExistente = $db->pagos->findOne([
        '$or' => [
            ['referencia'          => $referencia],
            ['referencia_bancaria' => $referencia]
        ]
    ]);

    if ($refExistente) {
        throw new Exception("La referencia bancaria N° {$referencia} ya fue registrada previamente en el sistema.");
    }

    // 4. PROCESAMIENTO Y CARGA ARCHIVO DE SOPORTE (Voucher Digital)
    if (!isset($_FILES['soporte']) || $_FILES['soporte']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Debes adjuntar la captura o foto del comprobante de pago.");
    }

    $archivo = $_FILES['soporte'];
    $extPermitidas = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $extPermitidas)) {
        throw new Exception("Formato no válido. Solo se permiten imágenes (JPG, PNG, WEBP) o documentos PDF.");
    }

    // Límite de tamaño: 5 MB
    if ($archivo['size'] > (5 * 1024 * 1024)) {
        throw new Exception("El archivo supera el tamaño máximo permitido de 5 MB.");
    }

    // Carpeta de almacenamiento físico
    $directorioDestino = __DIR__ . '/uploads/vouchers/';
    if (!is_dir($directorioDestino)) {
        mkdir($directorioDestino, 0755, true);
    }

    // Nombre único del archivo físico para evitar sobreescritura
    $nombreArchivo = "voucher_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $extension;
    $rutaFisica    = $directorioDestino . $nombreArchivo;
    $rutaRelativa  = "uploads/vouchers/" . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaFisica)) {
        throw new Exception("Error al almacenar el comprobante en el servidor. Intenta nuevamente.");
    }

    // 5. GENERAR NÚMERO DE RASTREO ÚNICO (Ej: AV-2026-748291)
    $anioActual = date('Y');
    $hashAleatorio = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $numeroRastreo = "AV-{$anioActual}-{$hashAleatorio}";

    // Verificar en MongoDB que no exista por casualidad (colisión cero)
    while ($db->pagos->findOne(['numero_rastreo' => $numeroRastreo])) {
        $hashAleatorio = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $numeroRastreo = "AV-{$anioActual}-{$hashAleatorio}";
    }

    // 6. BUSCAR DATOS DEL USUARIO EN MONGODB PARA EL REGISTRO
    $usuario = $db->usuarios->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
    $nombreVecino = $usuario['nombre'] ?? $_SESSION['nombre'] ?? 'Residente';
    $cedulaVecino = $usuario['cedula'] ?? 'S/CI';
    $emailVecino  = $usuario['email'] ?? '';

    // 7. CONSTRUIR DOCUMENTO NOSQL E INSERTAR EN LA COLECCIÓN 'pagos'
    $nuevoPago = [
        'numero_rastreo'      => $numeroRastreo, // <-- NUEVO CAMPO OFICIAL DE RASTREO
        'user_id'             => new MongoDB\BSON\ObjectId($userId),
        'cedula_vecino'       => $cedulaVecino,
        'nombre'              => $nombreVecino,
        'monto'               => $monto,
        'metodo_pago'         => $metodo,
        'referencia'          => $referencia,
        'referencia_bancaria' => $referencia,
        'correo_remitente'    => $correoRemitente,
        'soporte_url'         => $rutaRelativa,
        'estado'              => 'En Revisión',
        'fecha_pago'          => date('Y-m-d'),
        'fecha_declaracion'   => new MongoDB\BSON\UTCDateTime(),
        'trazabilidad'        => [
            [
                'evento'    => 'Declarado por Residente',
                'estado'    => 'En Revisión',
                'timestamp' => date('d/m/Y H:i:s')
            ]
        ]
    ];

    $resultado = $db->pagos->insertOne($nuevoPago);

    if (!$resultado->getInsertedCount()) {
        throw new Exception("No se pudo registrar el pago en la base de datos NoSQL.");
    }

    // 8. NOTIFICACIÓN POR CORREO ELECTRÓNICO CON PHPMAILER
    $envioSmtp = false;
    if (!empty($emailVecino)) {
        $asunto = "Comprobante Recibido: {$numeroRastreo} - Alianza Victoriosa";
        $montoFormat = number_format($monto, 2, ',', '.') . " Bs/USD";
        $metodoLabel = strtoupper(str_replace('_', ' ', $metodo));

        $html = "
        <div style='font-family: Arial, sans-serif; max-w: 550px; margin: auto; border: 1px solid #0ea5e9; border-radius: 12px; overflow: hidden; background-color: #0f172a; color: #f8fafc;'>
            <div style='background: linear-gradient(90deg, #00f2fe, #4facfe); padding: 20px; text-align: center; color: #0f172a;'>
                <h2 style='margin: 0; font-weight: 900; letter-spacing: 1px;'>ALIANZA VICTORIOSA</h2>
                <p style='margin: 4px 0 0; font-size: 12px; font-weight: bold;'>Recibo Provisional de Pago</p>
            </div>
            <div style='padding: 30px;'>
                <p style='font-size: 16px; margin-top: 0;'>Hola <strong>{$nombreVecino}</strong>,</p>
                <p style='font-size: 14px; color: #94a3b8;'>Hemos recibido tu declaración de pago en nuestra plataforma NoSQL. Tu comprobante se encuentra actualmente <strong>EN REVISIÓN</strong> por la Tesorería del condominio.</p>
                
                <div style='margin: 25px 0; padding: 20px; background-color: #1e293b; border-left: 4px solid #00f2fe; border-radius: 8px;'>
                    <p style='margin: 0 0 8px; font-size: 11px; color: #64748b; text-transform: uppercase;'>Número de Rastreo Oficial</p>
                    <p style='margin: 0 0 16px; font-family: monospace; font-size: 22px; font-weight: bold; color: #00f2fe;'>{$numeroRastreo}</p>
                    
                    <p style='margin: 0; font-size: 13px; color: #e2e8f0;'><strong>Monto Declarado:</strong> {$montoFormat}</p>
                    <p style='margin: 4px 0 0; font-size: 13px; color: #e2e8f0;'><strong>Método de Pago:</strong> {$metodoLabel}</p>
                    <p style='margin: 4px 0 0; font-size: 13px; color: #e2e8f0;'><strong>Referencia Bancaria:</strong> #{$referencia}</p>
                </div>

                <p style='font-size: 12px; color: #94a3b8;'>Puedes rastrear el estatus de auditoría en cualquier momento desde la portada de nuestro portal público utilizando tu número de rastreo.</p>
            </div>
            <div style='background-color: #020617; padding: 15px; text-align: center; font-size: 11px; color: #64748b; border-top: 1px solid #1e293b;'>
                VoucherCheck NoSQL &copy; " . date('Y') . " - Mesa Técnica de Alianza Victoriosa
            </div>
        </div>";

        $envioSmtp = enviarCorreoComunal($emailVecino, $nombreVecino, $asunto, $html);
    }

    // 9. RETORNAR RESPUESTA EXITOSA AL FRONTEND
    echo json_encode([
        'status'         => 'success',
        'message'        => '¡Comprobante reportado con éxito!',
        'numero_rastreo' => $numeroRastreo,
        'referencia'     => $referencia,
        'smtp'           => $envioSmtp ? 'notificado' : 'silencioso'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
?>