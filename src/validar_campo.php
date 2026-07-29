<?php
// validar_campo.php
header('Content-Type: application/json');
require_once 'conexion.php'; // Tu archivo de conexión a la BD (PDO)

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['campo']) && isset($data['valor'])) {
    $campo = $data['campo'];
    $valor = trim($data['valor']);

    // Lista blanca de campos permitidos por seguridad
    $campos_permitidos = ['cedula', 'email', 'usuario'];

    if (!in_array($campo, $campos_permitidos)) {
        echo json_encode(['error' => 'Campo no válido']);
        exit;
    }

    // Consulta preparada
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE $campo = :valor");
    $stmt->bindParam(':valor', $valor);
    $stmt->execute();

    $existe = $stmt->fetchColumn() > 0;

    echo json_encode(['existe' => $existe]);
} else {
    echo json_encode(['error' => 'Datos incompletos']);
}