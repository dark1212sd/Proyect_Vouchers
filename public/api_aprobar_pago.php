<?php
// public/api_aprobar_pago.php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

// Verificar permisos de admin
$rol = $_SESSION['role'] ?? $_SESSION['rol'] ?? 'user';
if (!isset($_SESSION['user_id']) || !in_array($rol, ['admin', 'superuser'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido']);
    exit();
}

try {
    $pago_id = $_POST['pago_id'] ?? null;

    if (!$pago_id) {
        echo json_encode(['status' => 'error', 'message' => 'ID de pago no proporcionado']);
        exit();
    }

    $objId = new MongoDB\BSON\ObjectId($pago_id);

    // Actualizar MongoDB a Validado/Aprobado
    $db->pagos->updateOne(
        ['_id' => $objId],
        ['$set' => [
            'estado' => 'aprobado',
            'estatus' => 'aprobado',
            'fecha_auditoria' => new MongoDB\BSON\UTCDateTime()
        ]]
    );

    echo json_encode(['status' => 'success', 'message' => 'Pago validado correctamente.']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()]);
}
?>