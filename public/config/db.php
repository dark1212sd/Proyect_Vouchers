<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$client = new MongoDB\Client("mongodb://localhost:27017");
$db = $client->junta_comunal_db;

try {
    // Conexión a la base de datos de la Junta Comunal
    $cliente = new MongoDB\Client("mongodb://localhost:27017");
    $db = $cliente->junta_comunal_db;

    // Test de conexión
    $coleccion = $db->usuarios;
    // echo "Conexión exitosa a la base de datos";

} catch (Exception $e) {
    die("Error al conectar con MongoDB: " . $e->getMessage());
}
?>