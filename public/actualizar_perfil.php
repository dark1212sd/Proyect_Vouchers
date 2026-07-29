<?php
// public/actualizar_perfil.php
session_start();

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit();
}

require_once __DIR__ . '/config/db.php';

$userId = (string)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $cedula = trim($_POST['cedula'] ?? '');
    $apto   = trim($_POST['apto'] ?? '');
    $email  = trim($_POST['email'] ?? '');

    // 1. Preparar los datos básicos a actualizar
    $datosActualizar = [
        'nombre' => $nombre,
        'cedula' => $cedula,
        'apto'   => $apto,
        'email'  => $email,
        'updated_at' => new MongoDB\BSON\UTCDateTime()
    ];

    // 2. Procesar la subida de la foto de perfil (si se seleccionó una)
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['foto_perfil'];
        $extPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        // Validar que sea imagen y no supere los 3MB
        if (in_array($extension, $extPermitidas) && $archivo['size'] <= (3 * 1024 * 1024)) {
            $directorioDestino = __DIR__ . '/uploads/perfiles/';

            // Crear la carpeta si no existe
            if (!is_dir($directorioDestino)) {
                mkdir($directorioDestino, 0755, true);
            }

            // Generar un nombre único basado en el ID y el timestamp
            $nombreArchivo = "avatar_{$userId}_" . time() . ".{$extension}";
            $rutaFisica = $directorioDestino . $nombreArchivo;
            $rutaRelativa = "uploads/perfiles/" . $nombreArchivo;

            if (move_uploaded_file($archivo['tmp_name'], $rutaFisica)) {
                // Si se subió bien, agregamos la URL al arreglo de actualización
                $datosActualizar['foto_url'] = $rutaRelativa;
            }
        }
    }

    // 3. Actualizar el documento en MongoDB
    try {
        // Intentar usar ObjectId (por si la base de datos lo tiene así)
        try {
            $objId = new MongoDB\BSON\ObjectId($userId);
        } catch (Exception $e) {
            $objId = $userId;
        }

        $db->usuarios->updateOne(
            [
                '$or' => [
                    ['_id' => $objId],
                    ['_id' => $userId]
                ]
            ],
            ['$set' => $datosActualizar]
        );

        // Actualizar la variable de sesión para que la barra de navegación cambie inmediatamente
        $_SESSION['nombre'] = $nombre;

        // Redirigir de vuelta al panel
        header('Location: user_panel.php?status=perfil_actualizado');
        exit();

    } catch (Exception $e) {
        header('Location: user_panel.php?status=error');
        exit();
    }
} else {
    // Redirigir si alguien entra al archivo directamente desde la URL
    header('Location: user_panel.php');
    exit();
}
?>