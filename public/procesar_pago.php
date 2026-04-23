<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/config/db.php';

$response = ['status' => 'error', 'message' => 'Método no permitido'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Validar campos básicos
        if (empty($_POST['cedula']) || empty($_POST['monto']) || empty($_POST['metodo_pago'])) {
            throw new Exception("Datos incompletos.");
        }

        $cedula = htmlspecialchars($_POST['cedula']);
        $monto = floatval($_POST['monto']);
        $metodo_pago = htmlspecialchars($_POST['metodo_pago']);

        // Campos condicionales según el método
        $referencia = (!empty($_POST['referencia'])) ? htmlspecialchars($_POST['referencia']) : 'N/A';
        $divisa = $_POST['divisa'] ?? 'bs';
        $plataforma = $_POST['plataforma'] ?? null;

        $nombre_archivo = null;
        $extension = null;

        // 2. Procesar el Archivo (Soporte) - AHORA ES CONDICIONAL
        if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'pdf'];
            $info_archivo = pathinfo($_FILES['comprobante']['name']);
            $extension = strtolower($info_archivo['extension']);

            if (!in_array($extension, $extensiones_permitidas)) {
                throw new Exception("Formato de archivo no permitido.");
            }

            $nombre_archivo = time() . "_" . bin2hex(random_bytes(4)) . "." . $extension;

            // LA RUTA ESTÁ CORREGIDA AQUÍ CON SU PUNTO Y COMA
            $carpeta_destino = __DIR__ . "/uploads/vouchers/";

            if (!is_dir($carpeta_destino)) {
                mkdir($carpeta_destino, 0755, true);
            }

            if (!move_uploaded_file($_FILES['comprobante']['tmp_name'], $carpeta_destino . $nombre_archivo)) {
                throw new Exception("No se pudo guardar la imagen.");
            }
        } else {
            // Si no subió archivo y NO es efectivo, dar error.
            if ($metodo_pago !== 'efectivo') {
                throw new Exception("El comprobante de pago es obligatorio para este método.");
            }
        }

        // 3. Construir el documento a guardar en MongoDB
        $documento = [
            'cedula_vecino' => $cedula,
            'monto' => new MongoDB\BSON\Decimal128($monto),
            'metodo_pago' => $metodo_pago,
            'referencia_bancaria' => $referencia,
            'estatus' => 'pendiente',
            'fecha_declaracion' => new MongoDB\BSON\UTCDateTime()
        ];

        // Añadir campos dinámicos
        if ($metodo_pago === 'efectivo') {
            $documento['divisa'] = $divisa;
        } elseif ($metodo_pago === 'electronico') {
            $documento['plataforma'] = $plataforma;
        }

        // Guardar soporte si existe
        if ($nombre_archivo) {
            $documento['soporte_url'] = $nombre_archivo;
            $documento['metadatos'] = [
                'extension' => $extension,
                'peso_kb' => round($_FILES['comprobante']['size'] / 1024, 2)
            ];
        }

        // 4. Inserción en la BD
        $resultado = $db->vouchers->insertOne($documento);

        $response = [
            'status' => 'success',
            'message' => '¡Declaración recibida con éxito!',
            'id' => (string)$resultado->getInsertedId()
        ];

    } catch (Exception $e) {
        $response = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

echo json_encode($response);
?>