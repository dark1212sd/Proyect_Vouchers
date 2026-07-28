<?php

// public/actualizar_perfil.php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = new MongoDB\BSON\ObjectId($_SESSION['user_id']);

    // Recibimos los datos del edificio y contacto
    $telefono = trim($_POST['telefono'] ?? '');
    $torre = trim($_POST['torre'] ?? 'Torre Única');
    $piso = trim($_POST['piso'] ?? '');
    $apartamento = trim($_POST['apartamento'] ?? '');

    $datosActualizar = [
        'telefono' => $telefono,
        'torre' => $torre,
        'piso' => $piso,
        'apartamento' => $apartamento,
        'updated_at' => new MongoDB\BSON\UTCDateTime()
    ];

    // Procesamiento de la foto de perfil (Avatar)
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['avatar']['tmp_name'];
        $fileName = $_FILES['avatar']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($fileExtension, $extensionesPermitidas)) {
            // Creamos la carpeta de avatares si no existe
            $uploadFileDir = __DIR__ . '/uploads/avatars/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            // Nombre único para evitar sobreescribir fotos de otros vecinos
            $newFileName = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $datosActualizar['avatar'] = '/uploads/avatars/' . $newFileName;
                $_SESSION['avatar'] = $datosActualizar['avatar'];
            }
        }
    }

    try {
        $db->usuarios->updateOne(
            ['_id' => $userId],
            ['$set' => $datosActualizar]
        );
        header("Location: /user_panel.php?perfil_actualizado=1");
        exit();
    } catch (Exception $e) {
        die("Error al actualizar perfil en MongoDB: " . $e->getMessage());
    }
} else {
    header("Location: /user_panel.php");
    exit();
}
