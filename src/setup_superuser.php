<?php
// Usamos la configuración de base de datos existente
require __DIR__ . '/../public/config/db.php';

$superData = [
    'username' => 'Leo_su',
    'password' => password_hash('RootSecure2026!', PASSWORD_DEFAULT), // Siempre encriptada
    'role' => 'superuser',
    'nombre' => 'Director de Sistemas',
    'cedula' => 'V-30235528',
    'created_at' => new MongoDB\BSON\UTCDateTime()
];

try {
    // Verificamos si ya existe para no duplicar
    $existe = $db->usuarios->findOne(['username' => $superData['username']]);

    if (!$existe) {
        $resultado = $db->usuarios->insertOne($superData);
        echo "✅ Superusuario creado con éxito. ID: " . $resultado->getInsertedId();
    } else {
        echo "⚠️ El Superusuario ya existe en la base de datos.";
    }
} catch (Exception $e) {
    echo "❌ Error al crear superusuario: " . $e->getMessage();
}
?>