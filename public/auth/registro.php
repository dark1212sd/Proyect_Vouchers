<?php
// public/auth/registro.php
session_start();

if (isset($_SESSION['role']) || isset($_SESSION['rol']) || isset($_SESSION['user_id'])) {
    $rol = $_SESSION['role'] ?? $_SESSION['rol'] ?? 'user';
    $destino = ($rol === 'superuser') ? '/super_dashboard.php' : (($rol === 'admin') ? '/dashboard.php' : '/user_panel.php');
    header("Location: " . $destino);
    exit();
}
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta Comunal - VoucherCheck</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { colors: { neon: { cyan: '#00f2fe', emerald: '#10b981' } } } } }
    </script>
    <style>
        .animate-bg-zoom { animation: slowZoom 15s infinite alternate ease-in-out; }
        @keyframes slowZoom { 0% { transform: scale(1); } 100% { transform: scale(1.05); } }
        .glow-cyan { box-shadow: 0 0 25px -5px rgba(0, 242, 254, 0.3); }
        .glow-input:focus { box-shadow: 0 0 15px -3px rgba(0, 242, 254, 0.25); }
        .animate-fade-in-right { animation: fadeInRight 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes fadeInRight { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex overflow-x-hidden">

<div class="flex w-full min-h-screen">

    <!-- Columna Izquierda: Branding -->
    <div class="hidden lg:flex lg:w-1/2 bg-slate-900 relative flex-col justify-between p-12 overflow-hidden border-r border-slate-800/80">
        <div class="absolute inset-0 z-0 opacity-20">
            <img src="https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=1500&q=80" alt="Background" class="w-full h-full object-cover animate-bg-zoom">
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/80 to-slate-900/40"></div>
        </div>
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-gradient-to-tr from-cyan-500/20 to-emerald-500/10 rounded-full blur-[100px] pointer-events-none"></div>

        <div class="relative z-10 flex items-center space-x-3">
            <img src="/assets/img/logo_alianza_victoriosa_anime.svg" alt="Alianza Victoriosa" class="w-14 h-14 glow-cyan rounded-2xl">
            <div>
                <span class="text-xl font-black tracking-tight text-white block leading-none">VOUCHER<span class="text-cyan-400">CHECK</span></span>
                <span class="text-[10px] font-bold text-emerald-400 tracking-widest uppercase block mt-0.5">Comunidad Alianza Victoriosa</span>
            </div>
        </div>

        <div class="relative z-10 max-w-lg my-auto py-12">
            <h2 class="text-4xl font-black text-white tracking-tight leading-tight mb-6">Únete a la autogestión de <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-emerald-400">Alianza Victoriosa</span>.</h2>
            <p class="text-slate-400 text-base leading-relaxed mb-8">Crea tu usuario para reportar transferencias, recibir recibos digitales por correo y descargar tus certificados de solvencia.</p>
        </div>
        <div class="relative z-10 text-xs text-slate-500 flex justify-between items-center">
            <span>&copy; <?php echo date('Y'); ?> Sistema de Gestión Comunal.</span>
        </div>
    </div>

    <!-- Columna Derecha: Formulario -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 bg-slate-950 relative">
        <a href="/index.php" class="absolute top-6 right-6 inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-slate-900 border border-slate-800 text-xs font-semibold text-slate-400 hover:text-cyan-400 transition-all"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Volver al inicio</a>

        <div class="w-full max-w-md animate-fade-in-right my-auto py-8">
            <div class="mb-8">
                <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight mb-2">Nuevo Registro</h1>
                <p class="text-sm text-slate-400">Completa tus datos personales y correo electrónico.</p>
            </div>

            <form id="registerForm" action="procesar_registro.php" method="POST" class="space-y-4">

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Nombre Completo</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500"><i data-lucide="user" class="w-5 h-5"></i></div>
                        <input type="text" name="nombre" required placeholder="Ej: Emerson Rodríguez" class="w-full pl-11 pr-4 py-3 bg-slate-900 border border-slate-800 rounded-xl text-white text-sm focus:outline-none focus:border-cyan-400 glow-input transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Cédula</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500"><i data-lucide="id-card" class="w-4 h-4"></i></div>
                            <input type="text" name="cedula" required placeholder="Ej: V-28192031" class="w-full pl-9 pr-3 py-3 bg-slate-900 border border-slate-800 rounded-xl text-white text-sm focus:outline-none focus:border-cyan-400 glow-input transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Usuario</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500"><i data-lucide="at-sign" class="w-4 h-4"></i></div>
                            <input type="text" name="username" required placeholder="Ej: emerson_r" class="w-full pl-9 pr-3 py-3 bg-slate-900 border border-slate-800 rounded-xl text-white text-sm focus:outline-none focus:border-cyan-400 glow-input transition-all">
                        </div>
                    </div>
                </div>

                <!-- NUEVO CAMPO: CORREO ELECTRÓNICO -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Correo Electrónico <span class="text-[10px] text-cyan-400 font-normal normal-case ml-1">(Para recibos)</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500"><i data-lucide="mail" class="w-5 h-5"></i></div>
                        <input type="email" name="email" required placeholder="Ej: tu_correo@gmail.com" class="w-full pl-11 pr-4 py-3 bg-slate-900 border border-slate-800 rounded-xl text-white text-sm focus:outline-none focus:border-cyan-400 glow-input transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Contraseña</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500"><i data-lucide="lock" class="w-5 h-5"></i></div>
                        <input type="password" id="password" name="password" required placeholder="••••••••••••" class="w-full pl-11 pr-11 py-3 bg-slate-900 border border-slate-800 rounded-xl text-white text-sm focus:outline-none focus:border-cyan-400 glow-input transition-all">
                        <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-500 hover:text-cyan-400 transition-colors"><i data-lucide="eye" id="eyeIcon" class="w-5 h-5"></i></button>
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="w-full py-4 rounded-xl bg-gradient-to-r from-cyan-500 via-teal-400 to-emerald-400 text-slate-950 font-extrabold text-sm glow-cyan hover:opacity-95 transition-all flex items-center justify-center gap-2.5 mt-6 group">
                    <svg id="btnSpinner" class="animate-spin -ml-1 mr-2 h-5 w-5 text-slate-950 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span id="btnText">Registrar Mi Cuenta Comunal</span>
                    <i data-lucide="check-circle-2" id="btnIcon" class="w-4 h-4 stroke-[2.5] group-hover:scale-110 transition-transform"></i>
                </button>
            </form>

            <div class="relative my-6 flex items-center justify-center border-t border-slate-800">
                <span class="absolute bg-slate-950 px-3 text-xs text-slate-500 font-medium uppercase tracking-wider">o si ya estás registrado</span>
            </div>

            <a href="/auth/login.php" class="w-full py-3.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-cyan-400 font-bold text-sm border border-cyan-500/30 transition-all flex items-center justify-center gap-2 shadow-lg group">
                <i data-lucide="log-in" class="w-4 h-4 text-cyan-400 group-hover:translate-x-0.5 transition-transform"></i>
                <span>Ya tengo cuenta, Iniciar Sesión</span>
            </a>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    togglePassword.addEventListener('click', () => {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        togglePassword.innerHTML = type === 'text' ? '<i data-lucide="eye-off" class="w-5 h-5 text-cyan-400"></i>' : '<i data-lucide="eye" class="w-5 h-5"></i>';
        lucide.createIcons();
    });

    const registerForm = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnIcon = document.getElementById('btnIcon');
    const btnSpinner = document.getElementById('btnSpinner');

    registerForm.addEventListener('submit', function() {
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-80', 'cursor-wait');
        btnSpinner.classList.remove('hidden');
        if (btnIcon) btnIcon.classList.add('hidden');
        btnText.textContent = 'Guardando en MongoDB...';
    });
</script>
</body>
</html>