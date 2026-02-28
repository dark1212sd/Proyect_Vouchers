<?php
require __DIR__ . '/../public/config/db.php'; // Ajusta la ruta según tu estructura

// Definir los datos del admin
$adminData = [
    'username' => 'admin_principal',
    'password' => password_hash('TuPasswordSeguro123', PASSWORD_DEFAULT),
    'role' => 'admin',
    'created_at' => new MongoDB\BSON\UTCDateTime()
];

try {
    // Insertar en la colección 'usuarios' definida en db.php
    $resultado = $coleccion->insertOne($adminData);
    echo "Administrador creado con ID: " . $resultado->getInsertedId();
} catch (Exception $e) {
    echo "Error al crear admin: " . $e->getMessage();
}
?>