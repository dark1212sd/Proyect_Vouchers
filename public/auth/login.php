<?php
// public/auth/login.php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: ../public/user_panel.php');
    exit();
}

// Activamos reporte de errores para desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. GUARDAMOS EL CAPTCHA ANTERIOR ANTES DE GENERAR EL NUEVO
// (Vital para que el POST compare con el número que el usuario vio en pantalla)
$captcha_esperado = $_SESSION['captcha_res'] ?? -1;

$num1 = rand(2, 9);
$num2 = rand(1, 8);
$_SESSION['captcha_res'] = $num1 + $num2;

// Si ya tiene sesión activa, redirigir a su panel correspondiente
if (isset($_SESSION['role']) || isset($_SESSION['rol']) || isset($_SESSION['user_id'])) {
    $rol = $_SESSION['role'] ?? $_SESSION['rol'] ?? 'user';
    $destino = ($rol === 'superuser') ? '/super_dashboard.php' : (($rol === 'admin') ? '/dashboard.php' : '/user_panel.php');
    header("Location: " . $destino);
    exit();
}

$error = "";

// Procesamiento del formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/db.php';

    $username       = trim($_POST['username'] ?? '');
    $password       = $_POST['password'] ?? '';
    $captchaUsuario = intval(trim($_POST['captcha'] ?? 0));

    if (!empty($username) && !empty($password) && !empty($captchaUsuario)) {
        try {
            // A. VALIDAR CAPTCHA PRIMERO (Ahorra recursos si es un bot)
            if ($captchaUsuario !== intval($captcha_esperado) || $captcha_esperado === -1) {
                $error = "Desafío antibots incorrecto. La suma matemática no coincide.";
            } else {
                // B. CONSULTA NOSQL A MONGODB
                $usuario = $db->usuarios->findOne(['username' => $username]);

                if (!$usuario) {
                    $error = "Credenciales incorrectas. Verifica tu usuario y contraseña.";
                } else {
                    // C. VERIFICAR BLOQUEO TEMPORAL (Fuerza Bruta)
                    $ahora = new MongoDB\BSON\UTCDateTime();
                    if (isset($usuario['bloqueado_hasta']) && $usuario['bloqueado_hasta'] > $ahora) {
                        $tiempoRestanteMs = $usuario['bloqueado_hasta']->toDateTime()->getTimestamp() - time();
                        $minutos = ceil($tiempoRestanteMs / 60);
                        $error = "🔒 Cuenta bloqueada temporalmente por seguridad tras 3 intentos fallidos. Podrás reintentar en {$minutos} minuto(s).";
                    } else {
                        // D. VERIFICAR CONTRASEÑA
                        if (password_verify($password, $usuario['password'])) {

                            // LIMPIAR INTENTOS Y BLOQUEOS EN MONGODB AL TENER ÉXITO
                            $db->usuarios->updateOne(
                                    ['_id' => $usuario['_id']],
                                    ['$unset' => ['intentos_fallidos' => '', 'bloqueado_hasta' => '']]
                            );

                            $_SESSION['user_id']  = (string)$usuario['_id'];
                            $_SESSION['username'] = $usuario['username'];
                            $_SESSION['role']     = $usuario['role'] ?? 'user';
                            $_SESSION['nombre']   = $usuario['nombre'] ?? $username;

                            $rol = $_SESSION['role'];
                            if ($rol === 'superuser') {
                                header("Location: /super_dashboard.php");
                            } elseif ($rol === 'admin') {
                                header("Location: /dashboard.php");
                            } else {
                                header("Location: /user_panel.php");
                            }
                            exit();

                        } else {
                            // SI LA CLAVE ES INCORRECTA: Aumentar contador de fallos
                            $intentos = intval($usuario['intentos_fallidos'] ?? 0) + 1;
                            $maxIntentos = 3;

                            $datosActualizar = ['$set' => ['intentos_fallidos' => $intentos]];
                            $error = "Contraseña incorrecta. Te quedan " . ($maxIntentos - $intentos) . " intento(s) antes del bloqueo.";

                            // Si llegó a 3 fallos, bloqueamos por 5 minutos (300 segundos)
                            if ($intentos >= $maxIntentos) {
                                $tiempoBloqueo = new MongoDB\BSON\UTCDateTime((time() + 300) * 1000);
                                $datosActualizar['$set']['bloqueado_hasta'] = $tiempoBloqueo;
                                $error = "🚨 Has superado el límite de 3 intentos fallidos. Tu cuenta ha sido bloqueada por 5 minutos.";
                            }

                            $db->usuarios->updateOne(['_id' => $usuario['_id']], $datosActualizar);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $error = "Error de conexión con MongoDB: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, completa todos los campos para continuar.";
    }
}
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema - VoucherCheck</title>

    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        neon: {
                            cyan: '#00f2fe',
                            emerald: '#10b981',
                            blue: '#4facfe'
                        }
                    }
                }
            }
        }
    </script>

    <style>
        @keyframes slowZoom {
            0% { transform: scale(1); }
            100% { transform: scale(1.05); }
        }
        .animate-bg-zoom {
            animation: slowZoom 15s infinite alternate ease-in-out;
        }
        .glow-cyan {
            box-shadow: 0 0 25px -5px rgba(0, 242, 254, 0.3);
        }
        .glow-input:focus {
            box-shadow: 0 0 15px -3px rgba(0, 242, 254, 0.25);
        }
        @keyframes fadeInRight {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .animate-fade-in-right {
            animation: fadeInRight 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex selection:bg-cyan-500 selection:text-slate-950 overflow-x-hidden">

<!-- CONTENEDOR PRINCIPAL DIVIDIDO (SPLIT-SCREEN) -->
<div class="flex w-full min-h-screen">

    <!-- ==========================================
         COLUMNA IZQUIERDA: BRANDING FINTECH & ANIME (PC)
         ========================================== -->
    <div class="hidden lg:flex lg:w-1/2 bg-slate-900 relative flex-col justify-between p-12 overflow-hidden border-r border-slate-800/80">
        <!-- Imagen de fondo -->
        <div class="absolute inset-0 z-0 opacity-20">
            <img
                    src="https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=1500&q=80"
                    alt="FinTech Background"
                    class="w-full h-full object-cover animate-bg-zoom"
            >
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/80 to-slate-900/40"></div>
        </div>

        <!-- Resplandor Neón -->
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-gradient-to-tr from-cyan-500/20 to-emerald-500/10 rounded-full blur-[100px] pointer-events-none"></div>

        <!-- Header Izquierdo con Icono Anime -->
        <div class="relative z-10 flex items-center space-x-3">
            <img src="/assets/img/logo_alianza_victoriosa_anime.svg" alt="Alianza Victoriosa" class="w-14 h-14 glow-cyan rounded-2xl">
            <div>
                <span class="text-xl font-black tracking-tight text-white block leading-none">VOUCHER<span class="text-cyan-400">CHECK</span></span>
                <span class="text-[10px] font-bold text-emerald-400 tracking-widest uppercase block mt-0.5">Comunidad Alianza Victoriosa</span>
            </div>
        </div>

        <!-- Contenido Central Izquierdo -->
        <div class="relative z-10 max-w-lg my-auto py-12">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 text-xs font-bold uppercase tracking-wider mb-6">
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                Trabajo de Grado - Arquitectura NoSQL
            </div>
            <h2 class="text-4xl font-black text-white tracking-tight leading-tight mb-6">
                Auditoría financiera comunal en <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-emerald-400">milisegundos</span>.
            </h2>
            <p class="text-slate-400 text-base leading-relaxed mb-8">
                Plataforma diseñada para garantizar la integridad, trazabilidad y prevención de fraude en el registro de comprobantes de pago vecinales.
            </p>

            <div class="grid grid-cols-2 gap-4 pt-6 border-t border-slate-800">
                <div class="bg-slate-950/60 p-4 rounded-2xl border border-slate-800/80">
                    <i data-lucide="lock" class="w-5 h-5 text-emerald-400 mb-2"></i>
                    <span class="text-sm font-bold text-white block">Encriptación</span>
                    <span class="text-xs text-slate-500">Bcrypt Password Hash</span>
                </div>
                <div class="bg-slate-950/60 p-4 rounded-2xl border border-slate-800/80">
                    <i data-lucide="database" class="w-5 h-5 text-cyan-400 mb-2"></i>
                    <span class="text-sm font-bold text-white block">Alta Velocidad</span>
                    <span class="text-xs text-slate-500">Motor MongoDB 2.x</span>
                </div>
            </div>
        </div>

        <!-- Footer Izquierdo -->
        <div class="relative z-10 text-xs text-slate-500 flex justify-between items-center">
            <span>&copy; <?php echo date('Y'); ?> Sistema de Gestión Comunal.</span>
            <span class="font-mono text-[11px] bg-slate-950 px-2.5 py-1 rounded-md border border-slate-800 text-slate-400">PHP 8.5 // ONLINE</span>
        </div>
    </div>

    <!-- ==========================================
         COLUMNA DERECHA: FORMULARIO DE ACCESO
         ========================================== -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 md:p-16 bg-slate-950 relative">

        <a href="/index.php" class="absolute top-6 right-6 inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-slate-900 border border-slate-800 text-xs font-semibold text-slate-400 hover:text-cyan-400 hover:border-cyan-500/30 transition-all">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
            <span>Volver al inicio</span>
        </a>

        <!-- Contenedor del Formulario -->
        <div class="w-full max-w-md animate-fade-in-right my-auto">

            <!-- Logo para móviles con el icono Anime -->
            <div class="flex items-center space-x-3 mb-8 lg:hidden">
                <img src="/assets/img/logo_alianza_victoriosa_anime.svg" alt="Alianza Victoriosa" class="w-11 h-11 glow-cyan rounded-xl">
                <div>
                    <span class="text-lg font-black tracking-tight text-white block leading-none">VOUCHER<span class="text-cyan-400">CHECK</span></span>
                    <span class="text-[9px] font-bold text-emerald-400 tracking-widest uppercase block">Alianza Victoriosa</span>
                </div>
            </div>

            <div class="mb-8">
                <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight mb-2">Bienvenido de nuevo</h1>
                <p class="text-sm text-slate-400">Ingresa tus credenciales comunales para acceder al panel de control.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="mb-6 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-medium flex items-start gap-3 animate-bounce">
                    <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0 mt-0.5"></i>
                    <div>
                        <span class="font-bold block mb-0.5">Fallo de autenticación</span>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form id="loginForm" action="/auth/login.php" method="POST" class="space-y-5">

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2" for="username">
                        Usuario o Cédula de Identidad
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500">
                            <i data-lucide="user" class="w-5 h-5"></i>
                        </div>
                        <input
                                type="text"
                                id="username"
                                name="username"
                                required
                                autocomplete="username"
                                placeholder="Ej: Leo_su o V-12345678"
                                class="w-full pl-11 pr-4 py-3.5 bg-slate-900 border border-slate-800 rounded-xl text-white text-sm focus:outline-none focus:border-cyan-400 glow-input transition-all placeholder-slate-600"
                        >
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider" for="password">
                            Contraseña
                        </label>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500">
                            <i data-lucide="lock" class="w-5 h-5"></i>
                        </div>
                        <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••••••"
                                class="w-full pl-11 pr-11 py-3.5 bg-slate-900 border border-slate-800 rounded-xl text-white text-sm focus:outline-none focus:border-cyan-400 glow-input transition-all placeholder-slate-600"
                        >
                        <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-500 hover:text-cyan-400 transition-colors">
                            <i data-lucide="eye" id="eyeIcon" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>

                <!-- CAMPO DE CAPTCHA MATEMÁTICO ADAPTADO A TU ESTÉTICA -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2" for="captcha">
                        Desafío Antibots (CAPTCHA)
                    </label>
                    <div class="flex items-center gap-3">
                        <div class="flex-1 py-3.5 px-4 bg-slate-900 border border-slate-800 rounded-xl font-mono font-black text-sm text-center text-cyan-400 select-none shadow-inner tracking-wider">
                            ¿Cuánto es <?php echo $num1; ?> + <?php echo $num2; ?>?
                        </div>
                        <div class="relative w-32">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <i data-lucide="shield-check" class="w-4 h-4"></i>
                            </div>
                            <input
                                    type="number"
                                    id="captcha"
                                    name="captcha"
                                    required
                                    placeholder="?"
                                    class="w-full pl-10 pr-3 py-3.5 bg-slate-900 border border-slate-800 rounded-xl text-white text-sm font-black text-center focus:outline-none focus:border-cyan-400 glow-input transition-all placeholder-slate-600"
                            >
                        </div>
                    </div>
                </div>

                <button
                        type="submit"
                        id="submitBtn"
                        class="w-full py-4 rounded-xl bg-gradient-to-r from-cyan-500 via-teal-400 to-emerald-400 text-slate-950 font-extrabold text-sm glow-cyan hover:opacity-95 hover:scale-[1.01] active:scale-[0.99] transition-all flex items-center justify-center gap-2.5 mt-4 relative overflow-hidden group"
                >
                    <svg id="btnSpinner" class="animate-spin -ml-1 mr-2 h-5 w-5 text-slate-950 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>

                    <span id="btnText">Iniciar Sesión en el Portal</span>
                    <i data-lucide="arrow-right" id="btnIcon" class="w-4 h-4 stroke-[2.5] group-hover:translate-x-1 transition-transform"></i>
                </button>
            </form>

            <!-- DIVISOR SEPARADOR -->
            <div class="relative my-6 flex items-center justify-center border-t border-slate-800">
                <span class="absolute bg-slate-950 px-3 text-xs text-slate-500 font-medium uppercase tracking-wider">o si eres nuevo en la comunidad</span>
            </div>

            <!-- BOTÓN PARA CREAR NUEVO USUARIO -->
            <a
                    href="/auth/registro.php"
                    class="w-full py-3.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-cyan-400 font-bold text-sm border border-cyan-500/30 hover:border-cyan-400/60 transition-all flex items-center justify-center gap-2 shadow-lg group"
            >
                <i data-lucide="user-plus" class="w-4 h-4 text-cyan-400 group-hover:scale-110 transition-transform"></i>
                <span>Crear Cuenta Comunal</span>
            </a>

            <div class="mt-8 pt-6 border-t border-slate-900 text-center">
                <p class="text-xs text-slate-500">
                    ¿Tienes problemas para acceder? <br>
                    <span class="text-slate-400 font-medium">Comunícate con la mesa técnica de Alianza Victoriosa.</span>
                </p>
            </div>

        </div>
    </div>

</div>

<!-- SCRIPTS DE ICONOS Y ANIMACIÓN DE LOGIN -->
<script>
    lucide.createIcons();

    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    togglePassword.addEventListener('click', () => {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        if (type === 'text') {
            togglePassword.innerHTML = '<i data-lucide="eye-off" class="w-5 h-5 text-cyan-400"></i>';
        } else {
            togglePassword.innerHTML = '<i data-lucide="eye" class="w-5 h-5"></i>';
        }
        lucide.createIcons();
    });

    const loginForm = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnIcon = document.getElementById('btnIcon');
    const btnSpinner = document.getElementById('btnSpinner');

    loginForm.addEventListener('submit', function() {
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-80', 'cursor-wait');
        submitBtn.classList.remove('hover:scale-[1.01]', 'hover:opacity-95');

        btnSpinner.classList.remove('hidden');
        if (btnIcon) btnIcon.classList.add('hidden');
        btnText.textContent = 'Autenticando en MongoDB...';
    });
</script>
</body>
</html>