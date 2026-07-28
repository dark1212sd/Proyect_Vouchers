<?php
// public/auth/procesar_registro.php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/db.php';

    $nombre   = trim($_POST['nombre'] ?? '');
    $cedula   = trim($_POST['cedula'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? ''); // CAPTURAMOS EL CORREO
    $password = $_POST['password'] ?? '';

    if (!empty($nombre) && !empty($cedula) && !empty($username) && !empty($email) && !empty($password)) {
        try {
            // Verificamos si el usuario, correo o la cédula ya existen para evitar duplicados
            $existe = $db->usuarios->findOne([
                    '$or' => [
                            ['username' => $username],
                            ['cedula'   => $cedula],
                            ['email'    => $email]
                    ]
            ]);

            if (!$existe) {
                // Encriptamos la contraseña con Bcrypt
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // Insertamos el nuevo vecino en MongoDB, INCLUYENDO SU EMAIL
                $resultado = $db->usuarios->insertOne([
                        'nombre'     => $nombre,
                        'cedula'     => $cedula,
                        'username'   => $username,
                        'email'      => $email,
                        'password'   => $passwordHash,
                        'role'       => 'user',
                        'created_at' => new MongoDB\BSON\UTCDateTime()
                ]);

                if ($resultado->getInsertedCount() > 0) {
                    header("Location: /auth/login.php?exito=1");
                    exit();
                } else {
                    $error = "No se pudo registrar la cuenta en la base de datos.";
                }
            } else {
                $error = "La cédula, correo electrónico o usuario ya están registrados en el edificio.";
            }
        } catch (Exception $e) {
            $error = "Error de conexión con MongoDB: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, completa todos los campos del formulario, incluyendo tu correo.";
    }
} else {
    header("Location: /auth/registro.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error de Registro - VoucherCheck</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex items-center justify-center p-4">
<div class="max-w-md w-full bg-slate-900 border border-rose-500/30 p-8 rounded-3xl text-center shadow-2xl relative overflow-hidden">
    <div class="absolute top-0 left-0 right-0 h-1 bg-rose-500"></div>
    <div class="w-16 h-16 bg-rose-500/10 text-rose-400 rounded-2xl flex items-center justify-center mx-auto mb-6 border border-rose-500/20">
        <i data-lucide="alert-triangle" class="w-8 h-8"></i>
    </div>
    <h2 class="text-xl font-bold text-white mb-2">No se pudo crear la cuenta</h2>
    <p class="text-sm text-slate-400 mb-6"><?php echo htmlspecialchars($error ?? 'Ocurrió un error inesperado.'); ?></p>
    <a href="/auth/registro.php" class="inline-flex items-center justify-center w-full py-3.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-sm transition-all">
        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Volver a intentarlo
    </a>
</div>
<script>lucide.createIcons();</script>
</body>
</html>