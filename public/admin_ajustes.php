<?php
// public/admin_ajustes.php
session_start();

// Protección de sesión: Solo administradores o superusuarios
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'superuser'])) {
    header("Location: /auth/login.php");
    exit();
}

// Aquí en el futuro se consultará a la colección 'configuraciones' en MongoDB
$cuota_actual = 35.00;
$dia_corte = "5";
$smtp_host = "smtp.gmail.com";
$smtp_user = "";
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustes de Portal - Admin</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        neon: { cyan: '#00f2fe', blue: '#4facfe', emerald: '#10b981' }
                    }
                }
            }
        }
    </script>
    <style>
        .glow-blue { box-shadow: 0 0 25px -5px rgba(79, 172, 254, 0.4); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex flex-col selection:bg-blue-500 selection:text-slate-950">

<!-- Formulario Principal que envuelve todos los ajustes -->
<form id="formAjustes" onsubmit="guardarAjustes(event)" class="flex flex-col min-h-screen">

    <!-- HEADER ADMIN -->
    <header class="bg-slate-900/90 border-b border-slate-800/80 sticky top-0 z-40 backdrop-blur-md">
        <div class="max-w-4xl mx-auto px-4 h-20 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="/dashboard.php" class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-blue-400 transition-colors" title="Volver al Dashboard">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <span class="text-lg font-black text-white tracking-tight">PORTAL<span class="text-blue-400">AJUSTES</span></span>
                    <span class="text-[10px] font-bold text-slate-500 tracking-widest uppercase block mt-0.5">Configuración Global</span>
                </div>
            </div>

            <button type="submit" id="btnGuardar" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-blue-500 to-cyan-400 hover:opacity-90 text-slate-950 font-black text-xs transition-all flex items-center gap-2 glow-blue">
                <i data-lucide="save" class="w-4 h-4 stroke-[2.5]"></i>
                <span id="txtGuardar">Guardar Cambios</span>
            </button>
        </div>
    </header>

    <!-- CONTENIDO DE AJUSTES -->
    <main class="max-w-4xl mx-auto px-4 py-8 w-full space-y-8 flex-grow">

        <!-- Notificación de Guardado (Oculta por defecto) -->
        <div id="alertSuccess" class="hidden p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold flex items-center gap-3 animate-pulse">
            <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0"></i>
            <span>¡Los ajustes del sistema han sido guardados correctamente en MongoDB!</span>
        </div>

        <!-- ==========================================
             1. CONFIGURACIÓN FINANCIERA
             ========================================== -->
        <section class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1 bg-blue-500"></div>
            <h2 class="text-sm font-black text-white flex items-center gap-2 mb-6 pb-3 border-b border-slate-800">
                <i data-lucide="calculator" class="w-5 h-5 text-blue-400"></i> Parámetros Financieros
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Cuota Base (USD)</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500 font-bold">$</div>
                        <input type="number" name="cuota_base" step="0.01" value="<?php echo $cuota_actual; ?>" required class="w-full pl-9 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono text-lg focus:border-blue-400 focus:outline-none transition-colors">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Día de Corte Mensual</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500"><i data-lucide="calendar" class="w-4 h-4"></i></div>
                        <select name="dia_corte" class="w-full pl-10 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white font-bold focus:border-blue-400 focus:outline-none transition-colors appearance-none cursor-pointer">
                            <option value="5" <?php echo ($dia_corte == '5') ? 'selected' : ''; ?>>Día 5 de cada mes</option>
                            <option value="10" <?php echo ($dia_corte == '10') ? 'selected' : ''; ?>>Día 10 de cada mes</option>
                            <option value="15" <?php echo ($dia_corte == '15') ? 'selected' : ''; ?>>Día 15 de cada mes</option>
                            <option value="30" <?php echo ($dia_corte == '30') ? 'selected' : ''; ?>>Fin de mes (Día 30)</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-500"><i data-lucide="chevron-down" class="w-4 h-4"></i></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==========================================
             2. CONFIGURACIÓN DE SEGURIDAD
             ========================================== -->
        <section class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
            <h2 class="text-sm font-black text-white flex items-center gap-2 mb-6 pb-3 border-b border-slate-800">
                <i data-lucide="shield-check" class="w-5 h-5 text-emerald-400"></i> Seguridad y Sistema
            </h2>

            <div class="space-y-4">
                <!-- Toggle Auditoría -->
                <div class="flex items-center justify-between p-4 sm:p-5 bg-slate-950 rounded-xl border border-slate-800 hover:border-slate-700 transition-colors">
                    <div>
                        <span class="block text-sm font-bold text-white mb-0.5">Auditoría Estricta de Soportes</span>
                        <span class="block text-[11px] text-slate-500">Exigir soporte digital (PDF o JPG) obligatoriamente en todos los métodos de pago.</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" name="auditoria_estricta" checked class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>

                <!-- Toggle Mantenimiento -->
                <div class="flex items-center justify-between p-4 sm:p-5 bg-slate-950 rounded-xl border border-slate-800 hover:border-slate-700 transition-colors">
                    <div>
                        <span class="block text-sm font-bold text-white mb-0.5">Modo Mantenimiento del Portal</span>
                        <span class="block text-[11px] text-slate-500">Bloquea el acceso a los residentes temporalmente (solo Administradores podrán entrar).</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" name="modo_mantenimiento" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-500 shadow-[0_0_10px_rgba(244,63,94,0)] peer-checked:shadow-[0_0_15px_rgba(244,63,94,0.4)]"></div>
                    </label>
                </div>
            </div>
        </section>

        <!-- ==========================================
             3. AUTOMATIZACIÓN DE RECIBOS (SMTP CORREOS)
             ========================================== -->
        <section class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1 bg-cyan-500"></div>
            <h2 class="text-sm font-black text-white flex items-center gap-2 mb-4 pb-3 border-b border-slate-800">
                <i data-lucide="mail" class="w-5 h-5 text-cyan-400"></i> Automatización de Recibos (SMTP)
            </h2>

            <p class="text-xs text-slate-400 mb-6 leading-relaxed bg-slate-950 p-4 rounded-xl border border-slate-800">
                <i data-lucide="info" class="w-4 h-4 inline mr-1 text-cyan-400"></i>
                Configura la cuenta oficial desde donde se enviarán automáticamente los comprobantes de pago (Vouchers Virtuales) y notificaciones a los correos de los vecinos.
            </p>

            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Servidor SMTP -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Servidor SMTP</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500"><i data-lucide="server" class="w-4 h-4"></i></div>
                            <select name="smtp_host" required class="w-full pl-10 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white font-bold focus:border-cyan-400 focus:outline-none transition-colors appearance-none cursor-pointer">
                                <option value="smtp.gmail.com" <?php echo ($smtp_host == 'smtp.gmail.com') ? 'selected' : ''; ?>>Google (Gmail / Workspace)</option>
                                <option value="smtp.office365.com" <?php echo ($smtp_host == 'smtp.office365.com') ? 'selected' : ''; ?>>Microsoft (Office 365 / Outlook)</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-500"><i data-lucide="chevron-down" class="w-4 h-4"></i></div>
                        </div>
                    </div>

                    <!-- Correo Tesorería -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Correo Emisor (Tesorería)</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500"><i data-lucide="at-sign" class="w-4 h-4"></i></div>
                            <input type="email" name="smtp_user" value="<?php echo htmlspecialchars($smtp_user); ?>" placeholder="tesoreria@alianzavictoriosa.com" required class="w-full pl-10 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono text-xs focus:border-cyan-400 focus:outline-none transition-colors">
                        </div>
                    </div>
                </div>

                <!-- Contraseña App -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Contraseña de Aplicación <span class="text-rose-400">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500"><i data-lucide="key" class="w-4 h-4"></i></div>
                        <input type="password" name="smtp_pass" id="smtp_pass" placeholder="Ingresa la clave de 16 dígitos generada por Google/Microsoft" required class="w-full pl-10 pr-12 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono focus:border-cyan-400 focus:outline-none transition-colors">
                        <button type="button" onclick="togglePassword('smtp_pass', 'eyeIconSMTP')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-500 hover:text-cyan-400 transition-colors">
                            <i data-lucide="eye" id="eyeIconSMTP" class="w-5 h-5"></i>
                        </button>
                    </div>
                    <span class="text-[10px] text-slate-500 mt-1.5 block">Por políticas de seguridad, no utilices tu contraseña principal. Genera una "App Password" desde los ajustes de seguridad de tu cuenta de correo.</span>
                </div>
            </div>
        </section>

    </main>
</form>

<!-- SCRIPTS -->
<script>
    lucide.createIcons();

    // Función para mostrar/ocultar contraseña SMTP
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.setAttribute('data-lucide', 'eye-off');
        } else {
            input.type = 'password';
            icon.setAttribute('data-lucide', 'eye');
        }
        lucide.createIcons();
    }

    // Simulación Asíncrona de Guardado de Ajustes
    function guardarAjustes(event) {
        event.preventDefault(); // Evita recargar la página

        const btnGuardar = document.getElementById('btnGuardar');
        const txtGuardar = document.getElementById('txtGuardar');
        const alertBox = document.getElementById('alertSuccess');

        // Cambio de estado del botón a "Cargando"
        btnGuardar.disabled = true;
        btnGuardar.classList.add('opacity-75', 'cursor-wait');
        txtGuardar.innerHTML = 'Actualizando MongoDB...';

        // Simular tiempo de carga hacia tu futura API (api_ajustes.php)
        setTimeout(() => {
            // Mostrar alerta de éxito
            alertBox.classList.remove('hidden');

            // Restaurar botón
            btnGuardar.disabled = false;
            btnGuardar.classList.remove('opacity-75', 'cursor-wait');
            txtGuardar.innerHTML = 'Guardar Cambios';

            // Ocultar alerta después de 4 segundos
            setTimeout(() => {
                alertBox.classList.add('hidden');
            }, 4000);

            // Hacemos scroll hacia arriba para que el admin vea la alerta
            window.scrollTo({ top: 0, behavior: 'smooth' });

        }, 1000);
    }
</script>
</body>
</html