<?php
// public/api_reportes.php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

// Verificar seguridad
$rol = $_SESSION['role'] ?? $_SESSION['rol'] ?? 'user';
if (!isset($_SESSION['user_id']) || !in_array($rol, ['admin', 'superuser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado.']);
    exit();
}

require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit();
}

try {
    // 1. CONSTRUIR LOS FILTROS DINÁMICOS
    $filtros = [];

    // Filtro por Fechas
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';

    if (!empty($fecha_inicio) && !empty($fecha_fin)) {
        $inicioMs = strtotime($fecha_inicio . " 00:00:00") * 1000;
        $finMs = strtotime($fecha_fin . " 23:59:59") * 1000;

        $filtros['created_at'] = [
            '$gte' => new MongoDB\BSON\UTCDateTime($inicioMs),
            '$lte' => new MongoDB\BSON\UTCDateTime($finMs)
        ];
    }

    // Filtro por Estado
    if (!empty($_POST['estado'])) {
        $filtros['estado'] = strtolower($_POST['estado']);
    }

    // Filtro por Método de Pago
    if (!empty($_POST['metodo_pago'])) {
        $filtros['metodo_pago'] = $_POST['metodo_pago'];
    }

    // Filtro por Banco de Origen
    if (!empty($_POST['banco'])) {
        $filtros['banco_origen'] = $_POST['banco'];
    }

    // 2. EJECUTAR LA CONSULTA
    $cursor = $db->pagos->find($filtros, ['sort' => ['created_at' => -1]]);
    $pagos = iterator_to_array($cursor);

    // Obtener todos los usuarios de una vez para optimizar
    $usuariosCursor = $db->usuarios->find();
    $diccionarioUsuarios = [];
    foreach ($usuariosCursor as $u) {
        $diccionarioUsuarios[(string)$u['_id']] = $u;
    }

    // 3. PROCESAR DATA Y CALCULAR ESTADÍSTICAS
    $data_formateada = [];
    $stats = [
        'total_monto_bs' => 0,
        'total_monto_usd' => 0,
        'cantidad_transacciones' => count($pagos),
        'aprobados' => 0,
        'rechazados' => 0,
        'pendientes' => 0
    ];

    foreach ($pagos as $p) {
        $estado = strtolower($p['estado'] ?? $p['estatus'] ?? 'en revisión');
        $monto = floatval((string)($p['monto'] ?? 0));
        $metodo = $p['metodo_pago'] ?? 'Desconocido';
        $es_usd = in_array(strtolower($metodo), ['zelle', 'paypal']);

        if ($estado === 'aprobado' || $estado === 'validado' || $estado === 'completado') {
            $stats['aprobados']++;
            if ($es_usd) { $stats['total_monto_usd'] += $monto; } else { $stats['total_monto_bs'] += $monto; }
        } elseif ($estado === 'rechazado') {
            $stats['rechazados']++;
        } else {
            $stats['pendientes']++;
        }

        $userId = (string)($p['user_id'] ?? '');
        $residenteInfo = $diccionarioUsuarios[$userId] ?? null;

        $nombreResidente = $residenteInfo['nombre'] ?? $p['nombre_residente'] ?? 'Desconocido';
        $aptoResidente = $residenteInfo['apto'] ?? $p['apto'] ?? 'S/A';

        $fechaStr = "No Especificada";
        if (!empty($p['created_at']) && $p['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
            $fechaStr = $p['created_at']->toDateTime()->format('d/m/Y h:i A');
        } elseif (!empty($p['fecha_pago'])) {
            $fechaObj = date_create($p['fecha_pago']);
            $fechaStr = $fechaObj ? date_format($fechaObj, 'd/m/Y h:i A') : $p['fecha_pago'];
        }

        // Manejo elegante del banco para registros viejos
        $bancoOrigen = !empty($p['banco_origen']) ? $p['banco_origen'] : 'No Especificado';

        $data_formateada[] = [
            'rastreo' => $p['numero_rastreo'] ?? 'S/N',
            'fecha' => $fechaStr,
            'residente' => $nombreResidente,
            'apto' => $aptoResidente,
            'metodo' => strtoupper(str_replace('_', ' ', $metodo)),
            'banco' => $bancoOrigen,
            'referencia' => $p['referencia_bancaria'] ?? $p['referencia'] ?? 'S/R',
            'moneda' => $es_usd ? 'USD' : 'BS',
            'monto' => $monto,
            'estado' => strtoupper($estado)
        ];
    }

    echo json_encode([
        'status' => 'success',
        'stats' => $stats,
        'data' => $data_formateada
    ]);

} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error Interno: ' . $e->getMessage()]);
}
?>