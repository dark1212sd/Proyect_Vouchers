<?php
require __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $cedula = $_POST['cedula'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Verificar si el usuario O la cédula ya existen para evitar duplicados
    $existe_usuario = $db->usuarios->findOne(['username' => $username]);
    $existe_cedula = $db->usuarios->findOne(['cedula' => $cedula]);

    if ($existe_usuario) {
        echo "<script>alert('El nombre de usuario ya está en uso. Elige otro.'); window.history.back();</script>";
        exit();
    }

    if ($existe_cedula) {
        echo "<script>alert('Esta cédula ya se encuentra registrada en el sistema.'); window.history.back();</script>";
        exit();
    }

    // Encriptar la contraseña por seguridad
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Insertar el nuevo vecino en MongoDB con la CÉDULA INCLUIDA
        $db->usuarios->insertOne([
            'nombre' => $nombre,
            'cedula' => $cedula,        // <--- LA SOLUCIÓN AL ERROR E11000
            'username' => $username,
            'password' => $passwordHash,
            'role' => 'vecino',
            'fecha_registro' => new MongoDB\BSON\UTCDateTime()
        ]);

        echo "<script>alert('Registro exitoso. Ahora puedes iniciar sesión.'); window.location.href='login.html';</script>";
    } catch (Exception $e) {
        // Si ocurre cualquier otro error de base de datos, lo mostramos
        echo "<script>alert('Error al registrar: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} else {
    header("Location: registro.html");
}
?>