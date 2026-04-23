<?php
session_start();
require __DIR__ . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['username']) || !isset($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit();
}

try {
    $voucher_id = new MongoDB\BSON\ObjectId($_GET['id']);
    $pago = $db->vouchers->findOne(['_id' => $voucher_id]);

    if (!$pago) {
        echo json_encode(['status' => 'error', 'message' => 'Pago no encontrado']);
        exit();
    }

    $mensajes = $pago['mensajes'] ?? [];
    $formateados = [];

    foreach ($mensajes as $msg) {
        $formateados[] = [
            'remitente' => $msg['remitente'],
            'rol' => $msg['rol'],
            'texto' => $msg['texto'],
            'fecha' => $msg['fecha']->toDateTime()->format('d/m Y, h:i A')
        ];
    }

    // Devolvemos los mensajes y quién es el usuario actual (para saber qué lado pintar las burbujas)
    echo json_encode([
        'status' => 'success',
        'usuario_actual' => $_SESSION['username'],
        'mensajes' => $formateados
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
}
?>