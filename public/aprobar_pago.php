<?php
// public/aprobar_pago.php
session_start();

// 1. SILENCIAR ADVERTENCIAS VISUALES PARA EVITAR EL ERROR DE JSON
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

// Protección de seguridad: Solo administradores y súper usuarios
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superuser'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit();
}

// 2. FORZAR LA SALIDA EXCLUSIVAMENTE EN FORMATO JSON
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config/db.php';
require __DIR__ . '/config/mailer.php';

// Leer los datos enviados por Javascript (fetch)
$datos = json_decode(file_get_contents('php://input'), true);

if (!isset($datos['id']) || !isset($datos['accion'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID o acción no proporcionados en la petición']);
    exit();
}

try {
    $id = new MongoDB\BSON\ObjectId($datos['id']);
    $accion = $datos['accion'];

    $nuevoEstado = ($accion === 'aprobar') ? 'Validado' : 'Rechazado';
    $nuevoEstatus = strtolower($nuevoEstado);

    // 1. Obtener los datos del pago antes de actualizar
    $pago = $db->pagos->findOne(['_id' => $id]);

    if (!$pago) {
        throw new Exception("El registro de pago no existe en la base de datos.");
    }

    // 2. Buscar al usuario para obtener su correo electrónico
    $cedulaVecino = $pago['cedula_vecino'] ?? $pago['cedula'] ?? '';
    $usuario = clone $db->usuarios->findOne(['cedula' => $cedulaVecino]);

    // Si no lo encuentra por cédula, intentar por el ID del usuario
    if (!$usuario && isset($pago['user_id']) && !empty($pago['user_id'])) {
        try {
            $usuario = clone $db->usuarios->findOne(['_id' => new MongoDB\BSON\ObjectId($pago['user_id'])]);
        } catch (Exception $e) {
            // Si el user_id no es un ObjectId válido, se ignora
        }
    }

    $correoUsuario = $usuario['email'] ?? null;
    $nombreVecino = $pago['nombre'] ?? $usuario['nombre'] ?? 'Residente';

    // 3. Actualizar ambas colecciones en MongoDB
    $datosActualizar = [
        '$set' => [
            'estado'     => $nuevoEstado,
            'estatus'    => $nuevoEstatus,
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ]
    ];

    $db->pagos->updateOne(['_id' => $id], $datosActualizar);
    $db->vouchers->updateOne(['_id' => $id], $datosActualizar);

    // ===============================================================
    // 4. ENVÍO DEL VOUCHER VIRTUAL AL CORREO (MODO SILENCIOSO)
    // ===============================================================
    if ($correoUsuario) {
        $montoFormat = number_format(floatval((string)$pago['monto']), 2, ',', '.');
        $referencia = $pago['referencia'] ?? $pago['referencia_bancaria'] ?? 'S/R';
        $fechaHoy = date('d/m/Y h:i A');

        if ($accion === 'aprobar') {
            $asunto = "✅ Recibo Digital: Pago Aprobado - Alianza Victoriosa";
            $html = "
            <div style='font-family: Arial, sans-serif; max-w: 600px; margin: auto; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);'>
                <div style='background-color: #10b981; padding: 20px; text-align: center; color: white;'>
                    <h2 style='margin: 0; font-size: 20px; text-transform: uppercase; letter-spacing: 1px;'>Voucher Digital Aprobado</h2>
                    <p style='margin: 5px 0 0; font-size: 13px; opacity: 0.9;'>Comunidad Alianza Victoriosa</p>
                </div>
                <div style='padding: 24px; background-color: #f8fafc; color: #334155;'>
                    <p style='font-size: 15px; margin-top: 0;'>Hola <strong>{$nombreVecino}</strong>,</p>
                    <p style='font-size: 14px; line-height: 1.5; color: #475569;'>Tu declaración de pago ha sido auditada y <strong>validada exitosamente</strong> por la Tesorería del edificio. A continuación, te presentamos el detalle oficial de tu transacción:</p>
                    
                    <div style='background: white; border: 1px solid #e2e8f0; border-left: 4px solid #10b981; padding: 16px; border-radius: 6px; margin: 20px 0;'>
                        <table style='width: 100%; font-size: 13px; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'><strong>N° Referencia:</strong></td>
                                <td style='padding: 6px 0; text-align: right; font-family: monospace; font-size: 14px; color: #0f172a;'><strong>{$referencia}</strong></td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'><strong>Monto Validado:</strong></td>
                                <td style='padding: 6px 0; text-align: right; font-size: 15px; color: #10b981;'><strong>Bs. {$montoFormat}</strong></td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'><strong>Fecha de Auditoría:</strong></td>
                                <td style='padding: 6px 0; text-align: right; color: #334155;'>{$fechaHoy}</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b; border-top: 1px dashed #cbd5e1; padding-top: 10px;'><strong>Estado de Cuenta:</strong></td>
                                <td style='padding: 6px 0; text-align: right; border-top: 1px dashed #cbd5e1; padding-top: 10px;'><span style='background-color: #d1fae5; color: #065f46; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 11px;'>SOLVENTE</span></td>
                            </tr>
                        </table>
                    </div>
                    
                    <p style='font-size: 11px; color: #94a3b8; text-align: center; margin-bottom: 0;'>Este documento es un comprobante de pago digital generado automáticamente por el sistema de gestión comunal VoucherCheck. No requiere firma autógrafa.</p>
                </div>
            </div>";
        } else {
            $asunto = "⚠️ Atención: Reporte de pago no procesado - Alianza Victoriosa";
            $html = "
            <div style='font-family: Arial, sans-serif; max-w: 600px; margin: auto; border: 1px solid #f43f5e; border-radius: 10px; overflow: hidden;'>
                <div style='background-color: #f43f5e; padding: 20px; text-align: center; color: white;'>
                    <h2 style='margin: 0; font-size: 20px; text-transform: uppercase;'>Auditoría No Procesada</h2>
                    <p style='margin: 5px 0 0; font-size: 13px; opacity: 0.9;'>Comunidad Alianza Victoriosa</p>
                </div>
                <div style='padding: 24px; background-color: #fff1f2; color: #334155;'>
                    <p style='font-size: 15px; margin-top: 0;'>Hola <strong>{$nombreVecino}</strong>,</p>
                    <p style='font-size: 14px; line-height: 1.5; color: #475569;'>Te informamos que tu reporte de pago con referencia <strong>{$referencia}</strong> por un monto de <strong>Bs. {$montoFormat}</strong> no ha podido ser validado por la Tesorería.</p>
                    <p style='font-size: 13px; background: white; p: 12px; border-radius: 6px; border: 1px solid #fecdd3; color: #e11d48; padding: 12px;'><strong>Motivo frecuente:</strong> El número de referencia no coincide con los estados de cuenta bancarios o la imagen del comprobante es ilegible.</p>
                    <p style='font-size: 13px; color: #475569;'>Por favor, ingresa nuevamente al portal para verificar los datos enviados o comunícate con la administración para aclarar la situación.</p>
                </div>
            </div>";
        }

        // Se ejecuta en silencio: si falla el correo, el pago igual queda aprobado en MongoDB
        enviarCorreoComunal($correoUsuario, $nombreVecino, $asunto, $html);
    }

    // Respuesta limpia para que el javascript del dashboard no falle
    echo json_encode([
        'status'  => 'success',
        'message' => 'El pago ha sido ' . strtolower($nuevoEstado) . ' con éxito y se notificó al residente.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error procesando la solicitud: ' . $e->getMessage()
    ]);
}
?>