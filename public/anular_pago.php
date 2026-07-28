<?php
// public/anular_pago.php
session_start();

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config/db.php';
require __DIR__ . '/config/mailer.php';

$datos = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada. Recarga la página.']);
    exit();
}

if (!isset($datos['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID de pago no proporcionado']);
    exit();
}

try {
    $id = new MongoDB\BSON\ObjectId($datos['id']);
    $userId = (string)$_SESSION['user_id'];

    // 1. Buscar el pago en la base de datos
    $pago = $db->pagos->findOne(['_id' => $id]);

    if (!$pago) {
        throw new Exception("El comprobante no existe en el sistema.");
    }

    // 2. Seguridad: Verificar que el reporte sea del vecino logueado
    if (isset($pago['user_id']) && (string)$pago['user_id'] !== $userId) {
        throw new Exception("No tienes permisos para anular este reporte.");
    }

    // 3. Regla de Negocio: Solo se pueden anular pagos que NO hayan sido aprobados
    $estadoActual = strtolower(trim($pago['estado'] ?? $pago['estatus'] ?? ''));
    if (in_array($estadoActual, ['validado', 'aprobado', 'solvente', 'completado'])) {
        throw new Exception("¡No puedes anular un voucher que ya fue auditado y APROBADO por la Tesorería!");
    }
    if ($estadoActual === 'anulado') {
        throw new Exception("Este voucher ya fue anulado previamente.");
    }

    // 4. Actualizar estado en MongoDB a 'Anulado'
    $datosActualizar = [
        '$set' => [
            'estado'     => 'Anulado',
            'estatus'    => 'anulado',
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ]
    ];

    $db->pagos->updateOne(['_id' => $id], $datosActualizar);
    $db->vouchers->updateOne(['_id' => $id], $datosActualizar);

    // ===============================================================
    // 5. ENVIAR CORREO DE CONFIRMACIÓN DE ANULACIÓN AL USUARIO
    // ===============================================================
    $usuario = $db->usuarios->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
    $correoUsuario = $usuario['email'] ?? $usuario['correo'] ?? null;
    $nombreVecino  = $pago['nombre'] ?? $usuario['nombre'] ?? 'Residente';
    $referencia    = $pago['referencia_bancaria'] ?? $pago['referencia'] ?? 'S/R';
    $montoFormat   = number_format(floatval((string)$pago['monto']), 2, ',', '.');

    if ($correoUsuario) {
        $asunto = "🚫 Trámite Anulado: Comprobante N° {$referencia} - Alianza Victoriosa";
        $html = "
        <div style='font-family: Arial, sans-serif; max-w: 600px; margin: auto; border: 1px solid #64748b; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);'>
            <div style='background-color: #475569; padding: 20px; text-align: center; color: white;'>
                <h2 style='margin: 0; font-size: 18px; text-transform: uppercase; letter-spacing: 1px;'>Reporte de Pago Anulado</h2>
                <p style='margin: 5px 0 0; font-size: 12px; opacity: 0.9;'>Comunidad Alianza Victoriosa</p>
            </div>
            <div style='padding: 24px; background-color: #f8fafc; color: #334155;'>
                <p style='font-size: 15px; margin-top: 0;'>Hola <strong>{$nombreVecino}</strong>,</p>
                <p style='font-size: 14px; line-height: 1.5; color: #475569;'>Te confirmamos que has <strong>anulado exitosamente</strong> tu declaración de pago en el portal comunal. Este voucher ha sido retirado de la cola de auditoría de la Tesorería.</p>
                
                <div style='background: white; border: 1px solid #e2e8f0; border-left: 4px solid #64748b; padding: 16px; border-radius: 6px; margin: 20px 0;'>
                    <table style='width: 100%; font-size: 13px; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 6px 0; color: #64748b;'><strong>N° Referencia:</strong></td>
                            <td style='padding: 6px 0; text-align: right; font-family: monospace; font-size: 14px; color: #0f172a; text-decoration: line-through;'><strong>{$referencia}</strong></td>
                        </tr>
                        <tr>
                            <td style='padding: 6px 0; color: #64748b;'><strong>Monto Declarado:</strong></td>
                            <td style='padding: 6px 0; text-align: right; font-size: 14px; color: #64748b;'>Bs. {$montoFormat}</td>
                        </tr>
                        <tr>
                            <td style='padding: 6px 0; color: #64748b; border-top: 1px dashed #cbd5e1; padding-top: 10px;'><strong>Estatus Actual:</strong></td>
                            <td style='padding: 6px 0; text-align: right; border-top: 1px dashed #cbd5e1; padding-top: 10px;'><span style='background-color: #e2e8f0; color: #475569; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 11px;'>ANULADO POR USUARIO</span></td>
                        </tr>
                    </table>
                </div>
                
                <p style='font-size: 12px; color: #64748b;'>Si cometiste un error en el monto o en el archivo adjunto, puedes ingresar al sistema y generar un nuevo reporte en cualquier momento.</p>
            </div>
        </div>";

        enviarCorreoComunal($correoUsuario, $nombreVecino, $asunto, $html);
    }

    echo json_encode([
        'status'  => 'success',
        'message' => 'El reporte fue anulado y se envió la confirmación a tu correo.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
?>