<?php

// auth/procesar_login.php
session_start();

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../config/db.php';

$datos = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit();
}

try {
    // 1. VALIDACIÓN DEL CAPTCHA (Primer escudo contra bots)
    $captchaUsuario = intval(trim($datos['captcha'] ?? 0));
    $captchaCorrecto = intval($_SESSION['captcha_res'] ?? -1);

    if ($captchaUsuario !== $captchaCorrecto || $captchaCorrecto === -1) {
        throw new Exception("El resultado del CAPTCHA de seguridad es incorrecto. Intenta de nuevo.");
    }

    $usuarioInput = trim($datos['username'] ?? $datos['email'] ?? '');
    $passwordInput = $datos['password'] ?? '';

    if (empty($usuarioInput) || empty($passwordInput)) {
        throw new Exception("Por favor ingresa tu usuario/correo y contraseña.");
    }

    // 2. BUSCAR USUARIO EN MONGODB (Por username, correo o cédula)
    $usuario = $db->usuarios->findOne([
        '$or' => [
            ['username' => $usuarioInput],
            ['email' => $usuarioInput],
            ['correo' => $usuarioInput],
            ['cedula' => $usuarioInput]
        ]
    ]);

    if (!$usuario) {
        // Por seguridad, damos un mensaje genérico para no revelar qué usuarios existen
        throw new Exception("Credenciales incorrectas o cuenta no registrada.");
    }

    // 3. VERIFICAR BLOQUEO TEMPORAL (Segundo escudo contra Fuerza Bruta)
    $ahora = new MongoDB\BSON\UTCDateTime();
    if (isset($usuario['bloqueado_hasta']) && $usuario['bloqueado_hasta'] > $ahora) {
        // Calcular minutos restantes de bloqueo
        $tiempoRestanteMs = $usuario['bloqueado_hasta']->toDateTime()->getTimestamp() - time();
        $minutosRestantes = ceil($tiempoRestanteMs / 60);
        throw new Exception("🔒 Cuenta bloqueada por seguridad tras varios intentos fallidos. Podrás volver a intentar en {$minutosRestantes} minuto(s).");
    }

    // 4. VERIFICAR CONTRASEÑA
    // Nota: Soporta contraseñas encriptadas con password_hash o texto plano (para tus usuarios de prueba antiguas)
    $passwordCorrecta = false;
    if (password_verify($passwordInput, $usuario['password'])) {
        $passwordCorrecta = true;
    } elseif ($passwordInput === $usuario['password']) {
        $passwordCorrecta = true;
    }

    // A. SI LA CLAVE ES INCORRECTA: Aumentar intentos fallidos
    if (!$passwordCorrecta) {
        $intentosActuales = intval($usuario['intentos_fallidos'] ?? 0) + 1;
        $maxIntentos = 3; // Límite de fallos permitidos

        $datosActualizar = ['$set' => ['intentos_fallidos' => $intentosActuales]];

        $mensajeError = "Contraseña incorrecta. Te quedan " . ($maxIntentos - $intentosActuales) . " intento(s).";

        // Si llegó al límite (3 fallos), bloqueamos por 5 minutos (300 segundos)
        if ($intentosActuales >= $maxIntentos) {
            $tiempoBloqueo = new MongoDB\BSON\UTCDateTime((time() + 300) * 1000);
            $datosActualizar['$set']['bloqueado_hasta'] = $tiempoBloqueo;
            $mensajeError = "🚨 Has superado el límite de 3 intentos fallidos. Tu cuenta ha sido bloqueada temporalmente por 5 minutos.";
        }

        $db->usuarios->updateOne(['_id' => $usuario['_id']], $datosActualizar);
        throw new Exception($mensajeError);
    }

    // B. SI LA CLAVE ES CORRECTA: Limpiar bloqueos y fallos en MongoDB
    $db->usuarios->updateOne(
        ['_id' => $usuario['_id']],
        ['$unset' => ['intentos_fallidos' => '', 'bloqueado_hasta' => '']]
    );

    // Iniciar Sesión exitosamente
    $_SESSION['user_id'] = (string)$usuario['_id'];
    $_SESSION['username'] = $usuario['username'] ?? $usuario['nombre'] ?? 'Residente';
    $_SESSION['role'] = $usuario['role'] ?? 'user';

    // Regenerar el captcha para la próxima vez
    unset($_SESSION['captcha_res']);

    echo json_encode([
        'status' => 'success',
        'message' => '¡Autenticación exitosa! Redirigiéndo...',
        'redirect' => ($usuario['role'] === 'admin' || $usuario['role'] === 'superuser') ? '../public/admin_panel.php' : '../public/user_panel.php'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
