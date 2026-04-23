<?php
session_start();
require __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['username'])) {
        echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
        exit();
    }

    $voucher_id = $_POST['voucher_id'];
    $mensaje_texto = trim($_POST['mensaje']);

    if (empty($mensaje_texto)) {
        echo json_encode(['status' => 'error', 'message' => 'El mensaje no puede estar vacío']);
        exit();
    }

    try {
        $id_obj = new MongoDB\BSON\ObjectId($voucher_id);
        $nuevo_mensaje = [
            'remitente' => $_SESSION['username'],
            'rol' => $_SESSION['role'],
            'texto' => htmlspecialchars($mensaje_texto),
            'fecha' => new MongoDB\BSON\UTCDateTime()
        ];

        // Guardamos el mensaje dentro del váucher
        $db->vouchers->updateOne(
            ['_id' => $id_obj],
            ['$push' => ['mensajes' => $nuevo_mensaje]]
        );

        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error al enviar mensaje.']);
    }
}
?>