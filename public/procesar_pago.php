<?php
/**
 * API de Procesamiento de Pagos - Sistema Lanceros de la Victoria
 * Backend: PHP + MongoDB
 */

// Seteamos el encabezado para que el navegador entienda que respondemos JSON
header('Content-Type: application/json; charset=utf-8');

// Requerimos la conexión a MongoDB (Asegúrate que la ruta sea correcta)
require __DIR__ . '/config/db.php';

// Inicializamos la respuesta por defecto
$response = [
    'status' => 'error',
    'message' => 'Método no permitido'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Validar campos básicos
        if (empty($_POST['cedula']) || empty($_POST['referencia']) || empty($_POST['monto'])) {
            throw new Exception("Todos los campos son obligatorios.");
        }

        $cedula = htmlspecialchars($_POST['cedula']);
        $referencia = htmlspecialchars($_POST['referencia']);
        $monto = floatval($_POST['monto']);

        // 2. Validar y Procesar el Archivo (Soporte)
        if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al subir el archivo de soporte.");
        }

        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'pdf'];
        $info_archivo = pathinfo($_FILES['comprobante']['name']);
        $extension = strtolower($info_archivo['extension']);

        if (!in_array($extension, $extensiones_permitidas)) {
            throw new Exception("Formato de archivo no permitido (Solo JPG, PNG o PDF).");
        }

        // Generar nombre único para evitar sobreescritura en Fedora
        $nombre_archivo = time() . "_" . bin2hex(random_bytes(4)) . "." . $extension;
        $carpeta_destino = "../uploads/vouchers/";
        $ruta_final = $carpeta_destino . $nombre_archivo;

        // Crear carpeta si no existe (Seguridad en el filesystem)
        if (!is_dir($carpeta_destino)) {
            mkdir($carpeta_destino, 0755, true);
        }

        if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $ruta_final)) {

            // 3. Persistencia en MongoDB
            $resultado = $db->vouchers->insertOne([
                'referencia_bancaria' => $referencia,
                'cedula_vecino' => $cedula,
                'monto' => new MongoDB\BSON\Decimal128($monto),
                'estatus' => 'pendiente',
                'soporte_url' => $nombre_archivo, // Guardamos solo el nombre para mayor portabilidad
                'fecha_declaracion' => new MongoDB\BSON\UTCDateTime(),
                'metadatos' => [
                    'extension' => $extension,
                    'peso_kb' => round($_FILES['comprobante']['size'] / 1024, 2)
                ]
            ]);

            // 4. Respuesta Exitosa
            $response = [
                'status' => 'success',
                'message' => '¡Declaración recibida con éxito!',
                'id' => (string)$resultado->getInsertedId()
            ];

        } else {
            throw new Exception("No se pudo mover el archivo al directorio de destino.");
        }

    } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
        // Manejo específico para el índice único de la referencia bancaria
        $response = [
            'status' => 'error',
            'message' => 'El número de referencia bancaria ya existe en nuestro sistema.'
        ];
    } catch (Exception $e) {
        // Manejo de errores generales
        $response = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

// Retornamos la respuesta JSON final
echo json_encode($response);