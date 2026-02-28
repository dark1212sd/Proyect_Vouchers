<?php
session_start();
// Si no hay sesión o no es admin, denegar acceso
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

header('Content-Type: application/json');
require __DIR__ . '/config/db.php';

try {
    // Buscamos todos los vouchers, ordenados por fecha (más nuevos primero)
    $cursor = $db->vouchers->find([], ['sort' => ['fecha_declaracion' => -1]]);

    $pagos = [];
    foreach ($cursor as $documento) {
        $pagos[] = [
            'id' => (string)$documento->_id,
            'cedula' => $documento->cedula_vecino,
            'referencia' => $documento->referencia_bancaria,
            'monto' => (string)$documento->monto,
            'estatus' => $documento->estatus,
            'soporte' => $documento->soporte_url
        ];
    }

    echo json_encode($pagos);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

