<?php

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit();
}

header('Content-Type: application/json');
require __DIR__ . '/config/db.php';

// Recibir datos JSON del fetch
$datos = json_decode(file_get_contents('php://input'), true);

if (!isset($datos['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID no proporcionado']);
    exit;
}

try {
    // Convertir el string ID a un ObjectId de MongoDB
    $id = new MongoDB\BSON\ObjectId($datos['id']);

    $resultado = $db->vouchers->updateOne(
        ['_id' => $id],
        ['$set' => ['estatus' => 'validado']]
    );

    if ($resultado->getModifiedCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Pago validado correctamente']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se realizaron cambios']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

header('Content-Type: application/json');
require __DIR__ . '/config/db.php';