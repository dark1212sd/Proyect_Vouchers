<?php
// public/api_rastreo.php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/db.php';

$datos = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$referencia = trim(htmlspecialchars($datos['referencia'] ?? ''));

if (empty($referencia)) {
    echo json_encode(['status' => 'error', 'message' => 'Por favor ingresa un número de referencia.']);
    exit();
}

try {
    // Consulta optimizada en la colección 'pagos' por referencia bancaria o ID
    $pago = $db->pagos->findOne([
        '$or' => [
            ['referencia' => $referencia],
            ['referencia_bancaria' => $referencia]
        ]
    ]);

    if (!$pago) {
        echo json_encode([
            'status'  => 'not_found',
            'message' => "No se encontró ningún reporte con la referencia N° {$referencia}."
        ]);
        exit();
    }

    // Formatear datos públicos no sensibles
    $estadoActual = strtolower(trim($pago['estado'] ?? $pago['estatus'] ?? 'en revisión'));
    $montoFormat  = number_format(floatval((string)($pago['monto'] ?? 0)), 2, ',', '.');
    $metodo       = strtoupper(str_replace('_', ' ', $pago['metodo_pago'] ?? 'Digital'));

    $fecha = "N/A";
    if (!empty($pago['fecha_pago'])) {
        $fecha = $pago['fecha_pago'];
    } elseif (!empty($pago['fecha_declaracion']) && $pago['fecha_declaracion'] instanceof MongoDB\BSON\UTCDateTime) {
        $fecha = $pago['fecha_declaracion']->toDateTime()->format('d/m/Y');
    }

    // Enmascarar la cédula por privacidad pública (Ej: V-123***78)
    $cedulaOriginal = $pago['cedula_vecino'] ?? $pago['cedula'] ?? 'V-00000000';
    $cedulaMask     = preg_replace('/(\d{3})\d{3}(\d{2})/', '$1***$2', $cedulaOriginal);

    echo json_encode([
        'status'     => 'success',
        'referencia' => $pago['referencia'] ?? $referencia,
        'monto'      => $montoFormat,
        'metodo'     => $metodo,
        'fecha'      => $fecha,
        'cedula'     => $cedulaMask,
        'estado'     => $pago['estado'] ?? 'En Revisión',
        'raw_status' => $estadoActual
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión con la base de datos NoSQL.']);
}
?>