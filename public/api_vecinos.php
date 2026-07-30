<?php
// public/api_vecinos.php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

// Verificar seguridad: Solo Admins
$rol = $_SESSION['role'] ?? $_SESSION['rol'] ?? 'user';
if (!isset($_SESSION['user_id']) || !in_array($rol, ['admin', 'superuser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado.']);
    exit();
}

// Cargar Base de Datos
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        case 'create':
            $email = strtolower(trim($_POST['email'] ?? ''));
            $cedula = trim($_POST['cedula'] ?? '');

            // Verificar si el correo o cédula ya existen
            $existe = $db->usuarios->findOne(['$or' => [['email' => $email], ['cedula' => $cedula]]]);
            if ($existe) {
                echo json_encode(['status' => 'error', 'message' => 'El correo o la cédula ya están registrados.']);
                exit();
            }

            $password = trim($_POST['password'] ?? '123456');

            $nuevoUsuario = [
                'nombre' => trim($_POST['nombre'] ?? ''),
                'apto' => strtoupper(trim($_POST['apto'] ?? '')),
                'cedula' => $cedula,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'user',
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ];

            $db->usuarios->insertOne($nuevoUsuario);
            echo json_encode(['status' => 'success', 'message' => 'Vecino registrado con éxito.']);
            break;

        case 'update':
            $userId = $_POST['user_id'] ?? '';
            if (!$userId) throw new Exception("ID de usuario requerido.");

            $objId = new MongoDB\BSON\ObjectId($userId);
            $email = strtolower(trim($_POST['email'] ?? ''));
            $cedula = trim($_POST['cedula'] ?? '');

            // Validar duplicidad excluyendo al propio usuario que estamos editando
            $existe = $db->usuarios->findOne([
                '_id' => ['$ne' => $objId],
                '$or' => [['email' => $email], ['cedula' => $cedula]]
            ]);

            if ($existe) {
                echo json_encode(['status' => 'error', 'message' => 'El correo o cédula ya pertenece a otro residente.']);
                exit();
            }

            $db->usuarios->updateOne(
                ['_id' => $objId],
                ['$set' => [
                    'nombre' => trim($_POST['nombre'] ?? ''),
                    'apto' => strtoupper(trim($_POST['apto'] ?? '')),
                    'cedula' => $cedula,
                    'email' => $email,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );

            echo json_encode(['status' => 'success', 'message' => 'Datos actualizados.']);
            break;

        case 'reset_password':
            $userId = $_POST['user_id'] ?? '';
            if (!$userId) throw new Exception("ID de usuario requerido.");

            $objId = new MongoDB\BSON\ObjectId($userId);
            $nuevaClave = password_hash('123456', PASSWORD_DEFAULT);

            $db->usuarios->updateOne(
                ['_id' => $objId],
                ['$set' => ['password' => $nuevaClave]]
            );

            echo json_encode(['status' => 'success', 'message' => 'Contraseña reiniciada a 123456.']);
            break;

        case 'delete':
            $userId = $_POST['user_id'] ?? '';
            if (!$userId) throw new Exception("ID de usuario requerido.");

            $objId = new MongoDB\BSON\ObjectId($userId);

            // Evitar que el admin se borre a sí mismo por error
            if ($userId === (string)$_SESSION['user_id']) {
                echo json_encode(['status' => 'error', 'message' => 'No puedes eliminar tu propia cuenta.']);
                exit();
            }

            $db->usuarios->deleteOne(['_id' => $objId]);
            echo json_encode(['status' => 'success', 'message' => 'Usuario eliminado.']);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida.']);
            break;
    }

} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de BD: ' . $e->getMessage()]);
}
?>