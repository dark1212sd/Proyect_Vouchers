<?php
// public/auth/registro.php
session_start();

// Si el usuario ya está autenticado, redirigir a su panel
if (isset($_SESSION['user_id'])) {
    header('Location: ../user_panel.php');
    exit();
}

// Configuración de errores y cabeceras para API AJAX
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// ============================================================================
// ENRUTADOR API BACKEND (PROCESA AJAX SIN RECARGAR LA PÁGINA)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/mailer.php';

    $datos = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $datos['action'] ?? '';

    try {
        // --------------------------------------------------------------------
        // ACCIÓN 1: VALIDACIÓN EN TIEMPO REAL (CEDULA, EMAIL, USUARIO)
        // --------------------------------------------------------------------
        if ($action === 'validar_campo') {
            $campo = trim($datos['campo'] ?? '');
            $valor = trim($datos['valor'] ?? '');

            if (!in_array($campo, ['cedula', 'email', 'username'])) {
                throw new Exception("Campo no permitido para validación.");
            }

            // Si es cédula, verificamos formato V-XXXXXXXX o E-XXXXXXXX
            if ($campo === 'cedula' && !preg_match('/^[VE]-[0-9]{6,8}$/', $valor)) {
                echo json_encode(['status' => 'success', 'existe' => false, 'invalido' => true]);
                exit();
            }

            $existe = $db->usuarios->findOne([$campo => $valor]);
            echo json_encode(['status' => 'success', 'existe' => !empty($existe)]);
            exit();
        }

        // --------------------------------------------------------------------
        // ACCIÓN 2: PROCESAR REGISTRO Y ENVIAR CÓDIGO OTP (2FA)
        // --------------------------------------------------------------------
        if ($action === 'registrar') {
            $nombre       = htmlspecialchars(trim($datos['nombre'] ?? ''));
            $nacionalidad = strtoupper(trim($datos['nacionalidad'] ?? 'V'));
            $cedula_num   = preg_replace('/[^0-9]/', '', trim($datos['cedula_num'] ?? ''));
            $apto         = htmlspecialchars(trim($datos['apto'] ?? ''));
            $email        = htmlspecialchars(trim($datos['email'] ?? ''));
            $username     = strtolower(htmlspecialchars(trim($datos['username'] ?? '')));
            $password     = $datos['password'] ?? '';

            // 1. Validaciones estrictas de longitud y formato en servidor
            if (strlen($nombre) < 3 || strlen($nombre) > 60) {
                throw new Exception("El nombre debe tener entre 3 y 60 caracteres.");
            }
            if (!in_array($nacionalidad, ['V', 'E']) || strlen($cedula_num) < 6 || strlen($cedula_num) > 8) {
                throw new Exception("La cédula debe ser numérica y tener entre 6 y 8 dígitos.");
            }
            if (strlen($apto) < 1 || strlen($apto) > 6) {
                throw new Exception("El número de apartamento no debe exceder los 6 caracteres.");
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 80) {
                throw new Exception("Por favor ingresa un correo electrónico válido (máx. 80 caracteres).");
            }
            if (!preg_match('/^[a-z0-9_]{4,20}$/', $username)) {
                throw new Exception("El usuario debe tener entre 4 y 20 caracteres (solo minúsculas, números o guion bajo).");
            }
            if (strlen($password) < 6 || strlen($password) > 64) {
                throw new Exception("La contraseña debe tener entre 6 y 64 caracteres.");
            }

            // Construir cédula unificada (Ej: V-12345678)
            $cedula = "{$nacionalidad}-{$cedula_num}";

            // 2. Doble verificación de seguridad contra duplicados en MongoDB
            $duplicado = $db->usuarios->findOne([
                    '$or' => [
                            ['cedula'   => $cedula],
                            ['email'    => $email],
                            ['username' => $username]
                    ]
            ]);

            if ($duplicado) {
                if ($duplicado['cedula'] === $cedula) throw new Exception("La cédula N° {$cedula} ya está registrada.");
                if ($duplicado['email'] === $email) throw new Exception("El correo {$email} ya tiene una cuenta asociada.");
                if ($duplicado['username'] === $username) throw new Exception("El usuario '@{$username}' no está disponible.");
            }

            // 3. Generar código numérico aleatorio de 6 dígitos (OTP)
            $codigo_otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            // Documento del residente en estado "Pendiente 2FA"
            $nuevoUsuario = [
                    'nombre'     => $nombre,
                    'cedula'     => $cedula,
                    'apto'       => $apto,
                    'email'      => $email,
                    'username'   => $username,
                    'password'   => $password_hash,
                    'role'       => 'user',
                    'estado'     => 'Pendiente 2FA',
                    'verificado' => false,
                    'codigo_otp' => $codigo_otp,
                    'created_at' => new MongoDB\BSON\UTCDateTime()
            ];

            $resultado = $db->usuarios->insertOne($nuevoUsuario);

            // 4. Enviar el correo electrónico con PHPMailer
            $asunto = "Código de Activación 2FA - Alianza Victoriosa";
            $html = "
            <div style='font-family: Arial, sans-serif; max-w: 550px; margin: auto; border: 1px solid #0ea5e9; border-radius: 12px; overflow: hidden; background-color: #0f172a; color: #f8fafc;'>
                <div style='background: linear-gradient(90deg, #00f2fe, #4facfe); padding: 20px; text-align: center; color: #0f172a;'>
                    <h2 style='margin: 0; font-weight: 900; letter-spacing: 1px;'>ALIANZA VICTORIOSA</h2>
                    <p style='margin: 4px 0 0; font-size: 12px; font-weight: bold;'>Activación de Cuenta NoSQL</p>
                </div>
                <div style='padding: 30px; text-align: center;'>
                    <p style='font-size: 16px; margin-top: 0;'>Hola <strong>{$nombre}</strong>,</p>
                    <p style='font-size: 14px; color: #94a3b8;'>Has iniciado el proceso de registro para el Apto <strong>{$apto}</strong>. Para activar tu cuenta, ingresa el siguiente código de seguridad:</p>
                    
                    <div style='margin: 25px auto; padding: 15px; background-color: #1e293b; border: 2px dashed #00f2fe; border-radius: 10px; width: fit-content;'>
                        <span style='font-family: monospace; font-size: 32px; font-weight: 900; letter-spacing: 8px; color: #00f2fe;'>{$codigo_otp}</span>
                    </div>
                    
                    <p style='font-size: 12px; color: #64748b;'>Este código caducará al cerrar la ventana de registro.<br>Si no solicitaste esta cuenta, ignora este mensaje.</p>
                </div>
            </div>";

            $envioExitoso = enviarCorreoComunal($email, $nombre, $asunto, $html);

            $_SESSION['temp_email_verificacion'] = $email;

            echo json_encode([
                    'status'  => 'success',
                    'message' => 'Código de verificación enviado con éxito.',
                    'email'   => $email,
                    'smtp'    => $envioExitoso ? 'ok' : 'error_silencioso'
            ]);
            exit();
        }

        // --------------------------------------------------------------------
        // ACCIÓN 3: VERIFICAR CÓDIGO OTP Y ACTIVAR CUENTA
        // --------------------------------------------------------------------
        if ($action === 'verificar_otp') {
            $otp_usuario = trim($datos['otp'] ?? '');
            $email = $_SESSION['temp_email_verificacion'] ?? $datos['email'] ?? '';

            if (empty($otp_usuario) || empty($email)) {
                throw new Exception("Código o sesión expirada. Por favor recarga e intenta nuevamente.");
            }

            $usuario = $db->usuarios->findOne([
                    'email'      => $email,
                    'codigo_otp' => $otp_usuario,
                    'verificado' => false
            ]);

            if (!$usuario) {
                throw new Exception("El código de verificación es incorrecto.");
            }

            $db->usuarios->updateOne(
                    ['_id' => $usuario['_id']],
                    [
                            '$set'   => ['verificado' => true, 'estado' => 'Activo'],
                            '$unset' => ['codigo_otp' => '']
                    ]
            );

            $_SESSION['user_id']  = (string)$usuario['_id'];
            $_SESSION['username'] = $usuario['username'];
            $_SESSION['role']     = $usuario['role'] ?? 'user';
            $_SESSION['nombre']   = $usuario['nombre'];

            unset($_SESSION['temp_email_verificacion']);

            echo json_encode([
                    'status'   => 'success',
                    'message'  => '¡Cuenta verificada y solvente! Redirigiendo al portal...',
                    'redirect' => '../user_panel.php'
            ]);
            exit();
        }

        throw new Exception("Acción no reconocida por el servidor.");

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Residente - VoucherCheck</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        neon: { cyan: '#00f2fe', emerald: '#10b981', blue: '#4facfe' }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        @keyframes slowZoom { 0% { transform: scale(1); } 100% { transform: scale(1.05); } }
        .animate-bg-zoom { animation: slowZoom 15s infinite alternate ease-in-out; }
        .glow-cyan { box-shadow: 0 0 25px -5px rgba(0, 242, 254, 0.3); }
        .glow-input:focus { box-shadow: 0 0 15px -3px rgba(0, 242, 254, 0.25); }
        @keyframes fadeInRight { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
        .animate-fade-in-right { animation: fadeInRight 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex selection:bg-cyan-500 selection:text-slate-950 overflow-x-hidden">

<!-- CONTENEDOR PRINCIPAL DIVIDIDO (SPLIT-SCREEN) -->
<div class="flex w-full min-h-screen">

    <!-- COLUMNA IZQUIERDA: BRANDING FINTECH & ANIME (PC) -->
    <div class="hidden lg:flex lg:w-1/2 bg-slate-900 relative flex-col justify-between p-12 overflow-hidden border-r border-slate-800/80">
        <div class="absolute inset-0 z-0 opacity-20">
            <img src="https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=1500&q=80" alt="Background" class="w-full h-full object-cover animate-bg-zoom">
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/80 to-slate-900/40"></div>
        </div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-gradient-to-tr from-cyan-500/20 to-emerald-500/10 rounded-full blur-[100px] pointer-events-none"></div>

        <div class="relative z-10 flex items-center space-x-3">
            <img src="../assets/img/logo_alianza_victoriosa_anime.svg" alt="Logo" class="w-14 h-14 glow-cyan rounded-2xl bg-slate-950 p-1">
            <div>
                <span class="text-xl font-black tracking-tight text-white block leading-none">VOUCHER<span class="text-cyan-400">CHECK</span></span>
                <span class="text-[10px] font-bold text-emerald-400 tracking-widest uppercase block mt-0.5">Comunidad Alianza Victoriosa</span>
            </div>
        </div>

        <div class="relative z-10 max-w-lg my-auto py-8">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 text-xs font-bold uppercase tracking-wider mb-6">
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                Registro Blindado 2FA - PHPMailer
            </div>
            <h2 class="text-3xl sm:text-4xl font-black text-white tracking-tight leading-tight mb-6">
                Únete a la gestión financiera comunal sin <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-emerald-400">intermediarios</span>.
            </h2>
            <p class="text-slate-400 text-sm leading-relaxed mb-8">
                Al registrar tu apartamento en MongoDB, podrás declarar tus vouchers en línea, auditar pagos de condominio y recibir tus recibos digitales en milisegundos.
            </p>

            <div class="grid grid-cols-2 gap-4 pt-6 border-t border-slate-800">
                <div class="bg-slate-950/60 p-4 rounded-2xl border border-slate-800/80">
                    <i data-lucide="shield-check" class="w-5 h-5 text-emerald-400 mb-2"></i>
                    <span class="text-sm font-bold text-white block">Validación 2FA</span>
                    <span class="text-xs text-slate-500">Verificación por Correo</span>
                </div>
                <div class="bg-slate-950/60 p-4 rounded-2xl border border-slate-800/80">
                    <i data-lucide="zap" class="w-5 h-5 text-cyan-400 mb-2"></i>
                    <span class="text-sm font-bold text-white block">AJAX en Vivo</span>
                    <span class="text-xs text-slate-500">Cero Datos Duplicados</span>
                </div>
            </div>
        </div>

        <div class="relative z-10 text-xs text-slate-500 flex justify-between items-center">
            <span>&copy; <?php echo date('Y'); ?> Alianza Victoriosa NoSQL.</span>
            <span class="font-mono text-[11px] bg-slate-950 px-2.5 py-1 rounded-md border border-slate-800 text-slate-400">SECURE SSL // PDO FREE</span>
        </div>
    </div>

    <!-- COLUMNA DERECHA: FORMULARIO WIZARD DINÁMICO -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 bg-slate-950 relative">

        <a href="login.php" class="absolute top-6 right-6 inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-slate-900 border border-slate-800 text-xs font-semibold text-slate-400 hover:text-cyan-400 hover:border-cyan-500/30 transition-all">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
            <span>Ir al Login</span>
        </a>

        <div class="w-full max-w-md animate-fade-in-right my-auto py-8">

            <!-- Logo Móvil -->
            <div class="flex items-center space-x-3 mb-6 lg:hidden">
                <img src="../assets/img/logo_alianza_victoriosa_anime.svg" alt="Logo" class="w-10 h-10 glow-cyan rounded-xl bg-slate-900 p-1">
                <div>
                    <span class="text-base font-black tracking-tight text-white block leading-none">VOUCHER<span class="text-cyan-400">CHECK</span></span>
                    <span class="text-[9px] font-bold text-emerald-400 tracking-widest uppercase block">Alianza Victoriosa</span>
                </div>
            </div>

            <!-- PASO 1: FORMULARIO DE REGISTRO EN VIVO -->
            <div id="seccionRegistro">
                <div class="mb-6">
                    <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight mb-1">Crear Cuenta</h1>
                    <p class="text-xs sm:text-sm text-slate-400">Ingresa tus datos vecinales para verificar tu apartamento en la base de datos.</p>
                </div>

                <form id="formRegistro" class="space-y-4">
                    <!-- Campo Nombre -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Nombre Completo</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500"><i data-lucide="user" class="w-4 h-4"></i></span>
                            <input type="text" name="nombre" required minlength="3" maxlength="60" placeholder="Ej: Leonardo Tarazona" class="w-full pl-10 pr-4 py-3 bg-slate-900 border border-slate-800 rounded-xl text-white text-xs sm:text-sm focus:outline-none focus:border-cyan-400 glow-input transition-all font-semibold">
                        </div>
                    </div>

                    <!-- Campos Cédula con Select V/E y Apto -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Cédula (CI)</label>
                            <div class="flex">
                                <!-- Selector V- o E- -->
                                <select name="nacionalidad" id="nacionalidad" class="bg-slate-900 border border-r-0 border-slate-800 rounded-l-xl px-2.5 py-3 text-cyan-400 font-black text-xs sm:text-sm focus:outline-none focus:border-cyan-400 transition-all">
                                    <option value="V">V-</option>
                                    <option value="E">E-</option>
                                </select>
                                <!-- Input Cédula (Solo Números, 6 a 8 dígitos) -->
                                <input type="text" id="cedula_num" name="cedula_num" required minlength="6" maxlength="8" inputmode="numeric" placeholder="12345678" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="w-full px-3 py-3 bg-slate-900 border border-slate-800 rounded-r-xl text-white text-xs sm:text-sm focus:outline-none focus:border-cyan-400 glow-input transition-all font-mono font-bold">
                            </div>
                            <span id="err-cedula" class="hidden text-[11px] text-rose-400 font-bold mt-1 flex items-center gap-1"><i data-lucide="x-circle" class="w-3 h-3 inline"></i> Ya registrada</span>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Apartamento</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500"><i data-lucide="home" class="w-4 h-4"></i></span>
                                <input type="text" name="apto" required maxlength="6" placeholder="Ej: 4-B" class="w-full pl-10 pr-3 py-3 bg-slate-900 border border-slate-800 rounded-xl text-cyan-400 text-xs sm:text-sm focus:outline-none focus:border-cyan-400 glow-input transition-all font-black">
                            </div>
                        </div>
                    </div>

                    <!-- Campo Email -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Correo Electrónico (Para Código 2FA)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500"><i data-lucide="mail" class="w-4 h-4"></i></span>
                            <input type="email" id="email" name="email" required maxlength="80" placeholder="ejemplo@gmail.com" class="w-full pl-10 pr-4 py-3 bg-slate-900 border border-slate-800 rounded-xl text-white text-xs sm:text-sm focus:outline-none focus:border-cyan-400 glow-input transition-all font-semibold">
                        </div>
                        <span id="err-email" class="hidden text-[11px] text-rose-400 font-bold mt-1 flex items-center gap-1"><i data-lucide="x-circle" class="w-3 h-3 inline"></i> Correo ya registrado en el sistema</span>
                    </div>

                    <!-- Campos Usuario y Contraseña -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Usuario</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500"><i data-lucide="at-sign" class="w-4 h-4"></i></span>
                                <input type="text" id="username" name="username" required minlength="4" maxlength="20" placeholder="leonardo21" oninput="this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '').toLowerCase()" class="w-full pl-10 pr-3 py-3 bg-slate-900 border border-slate-800 rounded-xl text-white text-xs sm:text-sm focus:outline-none focus:border-cyan-400 glow-input transition-all font-semibold lowercase">
                            </div>
                            <span id="err-username" class="hidden text-[11px] text-rose-400 font-bold mt-1 flex items-center gap-1"><i data-lucide="x-circle" class="w-3 h-3 inline"></i> Usuario no disponible</span>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Contraseña</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500"><i data-lucide="lock" class="w-4 h-4"></i></span>
                                <input type="password" id="password" name="password" required minlength="6" maxlength="64" placeholder="••••••••" class="w-full pl-10 pr-9 py-3 bg-slate-900 border border-slate-800 rounded-xl text-white text-xs sm:text-sm focus:outline-none focus:border-cyan-400 glow-input transition-all font-semibold">
                                <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-cyan-400"><i data-lucide="eye" id="eyeIcon" class="w-4 h-4"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3">
                        <button type="submit" id="btnSubmitRegistro" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-cyan-500 via-teal-400 to-emerald-400 text-slate-950 font-extrabold text-xs sm:text-sm glow-cyan hover:opacity-95 transition-all flex items-center justify-center gap-2 group">
                            <span id="btnTextReg">Crear Cuenta y Enviar Código</span>
                            <i data-lucide="send" class="w-4 h-4 stroke-[2.5] group-hover:translate-x-1 transition-transform"></i>
                        </button>
                    </div>
                </form>

                <div class="mt-6 pt-6 border-t border-slate-900 text-center">
                    <p class="text-xs text-slate-500">¿Ya posees una cuenta verificada? <a href="login.php" class="text-cyan-400 font-bold hover:underline">Inicia Sesión aquí</a></p>
                </div>
            </div>

            <!-- PASO 2: VERIFICACIÓN POR CÓDIGO OTP (OCULTO INICIALMENTE) -->
            <div id="seccionVerificacion" class="hidden animate-fade-in-right text-center space-y-6">
                <div class="w-16 h-16 rounded-3xl bg-gradient-to-tr from-cyan-500/20 to-emerald-500/20 border border-cyan-500/40 flex items-center justify-center mx-auto shadow-xl shadow-cyan-500/10">
                    <i data-lucide="mail-check" class="w-8 h-8 text-cyan-400 animate-bounce"></i>
                </div>

                <div>
                    <h2 class="text-2xl font-black text-white">Verificación en 2 Pasos</h2>
                    <p class="text-xs sm:text-sm text-slate-400 mt-2 leading-relaxed">
                        Hemos enviado un código de seguridad de 6 dígitos a <br><strong id="lblEmailEnviado" class="text-cyan-400 font-mono">tu@correo.com</strong>
                    </p>
                </div>

                <form id="formOTP" class="space-y-6 max-w-xs mx-auto">
                    <div>
                        <label class="block text-xs font-extrabold text-slate-400 uppercase tracking-widest mb-3">Código de Activación</label>
                        <input type="text" name="otp" id="inputOTP" maxlength="6" required autocomplete="off" placeholder="000000" class="w-full py-4 bg-slate-900 border-2 border-cyan-500/50 rounded-2xl text-center font-mono text-2xl sm:text-3xl font-black tracking-[10px] text-white focus:outline-none focus:border-cyan-400 glow-cyan transition-all shadow-inner">
                    </div>

                    <button type="submit" id="btnSubmitOTP" class="w-full py-4 rounded-xl bg-gradient-to-r from-emerald-400 to-cyan-500 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-emerald-500/20 hover:opacity-95 transition-all flex items-center justify-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Activar Cuenta e Ingresar
                    </button>
                </form>

                <div class="pt-4 border-t border-slate-900">
                    <button type="button" onclick="window.location.reload()" class="text-xs text-slate-500 hover:text-slate-300 underline font-medium">
                        ¿Te equivocaste de correo o no llegó el código? Reintentar
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- SCRIPTS DE VALIDACIÓN EN VIVO Y CONTROL DE FLUJO -->
<script>
    lucide.createIcons();

    // 1. Mostrar / Ocultar Contraseña
    document.getElementById('togglePassword').addEventListener('click', () => {
        const pass = document.getElementById('password');
        const type = pass.getAttribute('type') === 'password' ? 'text' : 'password';
        pass.setAttribute('type', type);
        document.getElementById('togglePassword').innerHTML = type === 'text' ? '<i data-lucide="eye-off" class="w-4 h-4 text-cyan-400"></i>' : '<i data-lucide="eye" class="w-4 h-4"></i>';
        lucide.createIcons();
    });

    // 2. VALIDACIÓN ASÍNCRONA EN TIEMPO REAL AL ESCRIBIR / SOLTAR EL CAMPO
    let camposInvalidos = new Set();

    function verificarBoton() {
        const btn = document.getElementById('btnSubmitRegistro');
        if (camposInvalidos.size > 0) {
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    // Validación Cédula Combinada (V/E + Números)
    const verificarCedula = async () => {
        const nac = document.getElementById('nacionalidad').value;
        const num = document.getElementById('cedula_num').value.trim();
        const input = document.getElementById('cedula_num');
        const errorSpan = document.getElementById('err-cedula');

        if (num.length < 6) {
            errorSpan.classList.add('hidden');
            input.classList.remove('border-rose-500', 'border-emerald-500');
            camposInvalidos.delete('cedula');
            verificarBoton();
            return;
        }

        const cedulaCompleta = `${nac}-${num}`;

        try {
            const response = await fetch('registro.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'validar_campo', campo: 'cedula', valor: cedulaCompleta })
            });
            const data = await response.json();

            if (data.existe) {
                errorSpan.classList.remove('hidden');
                input.classList.add('border-rose-500');
                input.classList.remove('border-emerald-500', 'border-slate-800');
                camposInvalidos.add('cedula');
            } else {
                errorSpan.classList.add('hidden');
                input.classList.add('border-emerald-500');
                input.classList.remove('border-rose-500', 'border-slate-800');
                camposInvalidos.delete('cedula');
            }
            verificarBoton();
        } catch (error) { console.error('Error al validar CI:', error); }
    };

    document.getElementById('nacionalidad').addEventListener('change', verificarCedula);
    document.getElementById('cedula_num').addEventListener('blur', verificarCedula);
    document.getElementById('cedula_num').addEventListener('input', () => { if (camposInvalidos.has('cedula')) verificarCedula(); });

    // Validación Email y Usuario
    const camposValidar = [
        { id: 'email', err: 'err-email' },
        { id: 'username', err: 'err-username' }
    ];

    camposValidar.forEach(item => {
        const input = document.getElementById(item.id);
        const errorSpan = document.getElementById(item.err);

        const verificar = async () => {
            const valor = input.value.trim();
            if (!valor) {
                errorSpan.classList.add('hidden');
                input.classList.remove('border-rose-500', 'border-emerald-500');
                camposInvalidos.delete(item.id);
                verificarBoton();
                return;
            }

            try {
                const response = await fetch('registro.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'validar_campo', campo: item.id, valor: valor })
                });
                const data = await response.json();

                if (data.existe) {
                    errorSpan.classList.remove('hidden');
                    input.classList.add('border-rose-500');
                    input.classList.remove('border-emerald-500', 'border-slate-800');
                    camposInvalidos.add(item.id);
                } else {
                    errorSpan.classList.add('hidden');
                    input.classList.add('border-emerald-500');
                    input.classList.remove('border-rose-500', 'border-slate-800');
                    camposInvalidos.delete(item.id);
                }
                verificarBoton();
            } catch (error) { console.error('Error de red al validar:', error); }
        };

        input.addEventListener('blur', verificar);
        input.addEventListener('input', () => { if (camposInvalidos.has(item.id)) verificar(); });
    });

    // 3. PROCESAR ENVÍO DEL PASO 1 (REGISTRO Y DESPACHO DE CORREO)
    document.getElementById('formRegistro').addEventListener('submit', async function(e) {
        e.preventDefault();
        if (camposInvalidos.size > 0) return;

        const btn = document.getElementById('btnSubmitRegistro');
        const txt = document.getElementById('btnTextReg');
        const originalText = txt.innerText;

        btn.disabled = true;
        txt.innerText = 'Creando NoSQL & Enviando Correo...';

        const formData = new FormData(this);
        const datos = Object.fromEntries(formData.entries());
        datos.action = 'registrar';

        try {
            const response = await fetch('registro.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(datos)
            });
            const data = await response.json();

            if (data.status === 'success') {
                // Ocultar Formulario 1 y Mostrar Paso 2 (OTP)
                document.getElementById('seccionRegistro').classList.add('hidden');
                document.getElementById('seccionVerificacion').classList.remove('hidden');
                document.getElementById('lblEmailEnviado').innerText = data.email;
                document.getElementById('inputOTP').focus();
            } else {
                alert('❌ Error: ' + (data.message || 'No se pudo crear la cuenta.'));
                btn.disabled = false;
                txt.innerText = originalText;
            }
        } catch (error) {
            alert('❌ Error de conexión al servidor NoSQL.');
            btn.disabled = false;
            txt.innerText = originalText;
        }
    });

    // 4. PROCESAR PASO 2 (VERIFICACIÓN DEL CÓDIGO OTP)
    document.getElementById('formOTP').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitOTP');
        const originalHtml = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = `<svg class="animate-spin h-4 w-4 text-black inline mr-2" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Validando 2FA...`;

        try {
            const response = await fetch('registro.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'verificar_otp', otp: document.getElementById('inputOTP').value.trim() })
            });
            const data = await response.json();

            if (data.status === 'success') {
                btn.className = "w-full py-4 rounded-xl bg-emerald-400 text-slate-950 font-black text-xs sm:text-sm transition-all";
                btn.innerHTML = `✅ ¡Cuenta Solvente! Entrando...`;
                setTimeout(() => { window.location.href = data.redirect; }, 800);
            } else {
                alert('❌ ' + (data.message || 'Código incorrecto.'));
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                document.getElementById('inputOTP').value = '';
                document.getElementById('inputOTP').focus();
            }
        } catch (error) {
            alert('❌ Error de red al intentar verificar.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });
</script>
</body>
</html>