<?php
// public/index.php
session_start();

// 1. LÓGICA INTELIGENTE CON RUTAS ABSOLUTAS DESDE LA RAÍZ (/)
$urlPortal = "/auth/login.php";
$textoBoton = "Ingresar al Portal";
$iconoBoton = "log-in";

if (isset($_SESSION['role']) || isset($_SESSION['rol']) || isset($_SESSION['user_id'])) {
    $rol = $_SESSION['role'] ?? $_SESSION['rol'] ?? 'user';

    if ($rol === 'superuser') {
        $urlPortal = "/super_dashboard.php";
    } elseif ($rol === 'admin') {
        $urlPortal = "/dashboard.php";
    } else {
        $urlPortal = "/user_panel.php";
    }

    $textoBoton = "Ir a Mi Panel";
    $iconoBoton = "layout-dashboard";
}
?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VoucherCheck - Auditoría y Control de Pagos</title>

    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Animate On Scroll (AOS) CSS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

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
            100% { transform: scale(1.08); }
        }
        .animate-hero-zoom {
            animation: slowZoom 20s infinite alternate ease-in-out;
        }
        .glow-cyan {
            box-shadow: 0 0 25px -5px rgba(0, 242, 254, 0.3);
        }
        /* Clase para el link activo en el menú (Scroll-Spy) */
        .nav-link-active {
            color: #00f2fe !important;
            font-weight: 700 !important;
            text-shadow: 0 0 12px rgba(0, 242, 254, 0.6);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 font-sans antialiased selection:bg-cyan-500 selection:text-slate-950 min-h-screen flex flex-col justify-between">

<!-- BARRA DE NAVEGACIÓN -->
<header class="fixed top-0 left-0 right-0 z-50 bg-slate-950/80 backdrop-blur-md border-b border-slate-800/80 transition-all duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">

        <div class="flex items-center space-x-3" data-aos="fade-right" data-aos-duration="800">
            <img src="/assets/img/logo_alianza_victoriosa_anime.svg" alt="Emblema Alianza Victoriosa" class="w-12 h-12">
            <div>
                <span class="text-xl font-black tracking-tight text-white block leading-none">VOUCHER<span class="text-cyan-400">CHECK</span></span>
                <span class="text-xs font-semibold text-slate-400 tracking-widest uppercase block mt-0.5">Alianza Victoriosa</span>
            </div>
        </div>

        <!-- Menú inteligente de navegación -->
        <nav id="desktop-nav" class="hidden md:flex items-center space-x-8 font-medium text-sm text-slate-400" data-aos="fade-down" data-aos-duration="800">
            <a href="#inicio" class="hover:text-cyan-400 transition-all duration-200 py-1">Inicio</a>
            <a href="#rastreo" class="hover:text-cyan-400 transition-all duration-200 py-1 flex items-center gap-1">
                <i data-lucide="search" class="w-4 h-4"></i> Consultar Estado
            </a>
            <a href="#proceso" class="hover:text-cyan-400 transition-all duration-200 py-1">¿Cómo Funciona?</a>
            <a href="#beneficios" class="hover:text-cyan-400 transition-all duration-200 py-1">Seguridad</a>
        </nav>

        <!-- ACCESO DINÁMICO SEGÚN LA SESIÓN ACTIVA -->
        <div data-aos="fade-left" data-aos-duration="800">
            <a href="<?php echo $urlPortal; ?>" class="inline-flex items-center justify-center px-6 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 text-slate-950 to-emerald-400 font-bold text-sm glow-cyan hover:opacity-95 hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200">
                <i data-lucide="<?php echo $iconoBoton; ?>" class="w-4 h-4 mr-2 stroke-[2.5]"></i>
                <?php echo $textoBoton; ?>
            </a>
        </div>

    </div>
</header>

<!-- HERO SECTION -->
<section id="inicio" class="relative min-h-screen flex items-center justify-center overflow-hidden pt-24 pb-16">
    <div class="absolute inset-0 z-0 overflow-hidden opacity-30">
        <img
                src="https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=2000&q=80"
                alt="Fondo digital oscuro"
                class="w-full h-full object-cover object-center animate-hero-zoom"
        >
        <div class="absolute inset-0 bg-gradient-to-b from-slate-950 via-slate-950/80 to-slate-950"></div>
    </div>

    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[300px] bg-gradient-to-tr from-cyan-500/20 to-emerald-500/10 blur-[120px] pointer-events-none -z-10"></div>

    <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center my-auto">

        <div class="inline-block" data-aos="zoom-in-up" data-aos-duration="1000">
                <span class="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold tracking-wide uppercase bg-slate-900/90 text-cyan-400 border border-cyan-500/30 mb-6 backdrop-blur-md shadow-lg shadow-cyan-500/10">
                    <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping mr-2"></span>
                    Plataforma de Trazabilidad Financiera NoSQL
                </span>
        </div>

        <h1 class="text-4xl sm:text-6xl md:text-7xl font-black text-white tracking-tight leading-none mb-6" data-aos="fade-up" data-aos-delay="200" data-aos-duration="1000">
            Control y Auditoría de <br>
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-teal-300 to-emerald-400">Vouchers de Pago</span>
        </h1>

        <p class="max-w-2xl mx-auto text-lg sm:text-xl text-slate-300 font-normal leading-relaxed mb-10" data-aos="fade-up" data-aos-delay="400" data-aos-duration="1000">
            Verifica el estatus de tus transferencias en tiempo real, audita números de referencia y gestiona tu solvencia comunal de manera 100% digital.
        </p>

        <!-- BOTONES DE ACCIÓN PÚBLICOS -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-16" data-aos="fade-up" data-aos-delay="600" data-aos-duration="1000">
            <a href="#rastreo" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-gradient-to-r from-cyan-500 to-emerald-400 text-slate-950 font-extrabold text-base glow-cyan hover:scale-[1.02] active:scale-[0.98] transition-all duration-200 flex items-center justify-center shadow-lg">
                <i data-lucide="search" class="w-5 h-5 mr-2 stroke-[2.5]"></i>
                Consultar Estado de Voucher
            </a>
            <a href="#proceso" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-cyan-400 font-bold text-base border border-cyan-500/30 hover:border-cyan-400/60 transition-all duration-200 flex items-center justify-center shadow-lg">
                <i data-lucide="help-circle" class="w-5 h-5 mr-2"></i>
                ¿Cómo Funciona?
            </a>
        </div>

        <!-- BUSCADOR DE ESTATUS DE VOUCHERS -->
        <div id="rastreo" class="max-w-3xl mx-auto bg-slate-900/90 border border-slate-800 p-6 sm:p-8 rounded-3xl backdrop-blur-xl shadow-2xl relative overflow-hidden scroll-mt-28" data-aos="zoom-in" data-aos-delay="800">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 via-teal-400 to-emerald-500"></div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6 text-left">
                <div>
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <i data-lucide="activity" class="w-5 h-5 text-cyan-400"></i>
                        Rastreo en Tiempo Real
                    </h3>
                    <p class="text-xs text-slate-400">Verifica si tu comprobante ya fue auditado por tesorería sin iniciar sesión.</p>
                </div>
                <span class="px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-semibold flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Motor Activo
                    </span>
            </div>

            <form action="validar_pago.php" method="GET" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-grow">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500">
                        <i data-lucide="hash" class="w-5 h-5"></i>
                    </div>
                    <input
                            type="text"
                            name="referencia"
                            required
                            placeholder="Ej: 48291048 (N° de Referencia o Cédula)"
                            class="w-full pl-11 pr-4 py-3.5 bg-slate-950 border border-slate-700 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 transition-all"
                    >
                </div>
                <button type="submit" class="px-8 py-3.5 bg-slate-800 hover:bg-cyan-500 hover:text-slate-950 text-cyan-400 font-bold rounded-xl border border-cyan-500/30 hover:border-transparent transition-all duration-200 flex items-center justify-center gap-2 text-sm shrink-0">
                    <span>Verificar Ahora</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>

            <div class="grid grid-cols-3 gap-2 mt-6 pt-6 border-t border-slate-800/80 text-center text-[11px] text-slate-400">
                <div class="flex items-center justify-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <span>En Revisión</span>
                </div>
                <div class="flex items-center justify-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>Aprobado / Solvente</span>
                </div>
                <div class="flex items-center justify-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                    <span>Rechazado</span>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- SECCIÓN: PROTOCOLO DE AUDITORÍA -->
<section id="proceso" class="py-24 bg-slate-950 relative z-10 border-t border-slate-900 scroll-mt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center max-w-3xl mx-auto mb-16" data-aos="fade-up">
            <h2 class="text-xs font-bold text-cyan-400 tracking-widest uppercase mb-2">Protocolo de Auditoría</h2>
            <p class="text-3xl sm:text-4xl font-black text-white tracking-tight">¿Cómo validamos tu voucher?</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

            <div class="p-8 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-cyan-500/50 hover:shadow-2xl hover:shadow-cyan-500/10 hover:-translate-y-1.5 transition-all duration-300 group" data-aos="fade-up" data-aos-delay="100">
                <div class="w-14 h-14 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 flex items-center justify-center mb-6 group-hover:bg-cyan-400 group-hover:text-slate-950 transition-all duration-300 shadow-sm">
                    <i data-lucide="upload-cloud" class="w-7 h-7 stroke-[2]"></i>
                </div>
                <span class="text-xs font-bold text-cyan-400 uppercase tracking-wider block mb-1">Paso 01</span>
                <h3 class="text-xl font-bold text-white mb-3">Carga Digital</h3>
                <p class="text-slate-400 leading-relaxed text-sm">Sube la foto o captura de pantalla de tu transferencia. El sistema extrae el número de referencia y monto reportado.</p>
            </div>

            <div class="p-8 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-teal-500/50 hover:shadow-2xl hover:shadow-teal-500/10 hover:-translate-y-1.5 transition-all duration-300 group" data-aos="fade-up" data-aos-delay="200">
                <div class="w-14 h-14 rounded-xl bg-teal-500/10 text-teal-400 border border-teal-500/20 flex items-center justify-center mb-6 group-hover:bg-teal-400 group-hover:text-slate-950 transition-all duration-300 shadow-sm">
                    <i data-lucide="cpu" class="w-7 h-7 stroke-[2]"></i>
                </div>
                <span class="text-xs font-bold text-teal-400 uppercase tracking-wider block mb-1">Paso 02</span>
                <h3 class="text-xl font-bold text-white mb-3">Cotejo Antifraude</h3>
                <p class="text-slate-400 leading-relaxed text-sm">Nuestra base de datos verifica en milisegundos que el comprobante no haya sido utilizado previamente por otro usuario.</p>
            </div>

            <div class="p-8 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-emerald-500/50 hover:shadow-2xl hover:shadow-emerald-500/10 hover:-translate-y-1.5 transition-all duration-300 group" data-aos="fade-up" data-aos-delay="300">
                <div class="w-14 h-14 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center mb-6 group-hover:bg-emerald-400 group-hover:text-slate-950 transition-all duration-300 shadow-sm">
                    <i data-lucide="file-check" class="w-7 h-7 stroke-[2]"></i>
                </div>
                <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider block mb-1">Paso 03</span>
                <h3 class="text-xl font-bold text-white mb-3">Solvencia Inmediata</h3>
                <p class="text-slate-400 leading-relaxed text-sm">Tras la confirmación de tesorería, obtén un certificado digital de solvencia con código de verificación único.</p>
            </div>

        </div>
    </div>
</section>

<!-- SECCIÓN: ARQUITECTURA FINTECH -->
<section id="beneficios" class="py-20 bg-slate-900/50 border-t border-slate-900 relative scroll-mt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div data-aos="fade-right" data-aos-duration="1000">
                <span class="text-xs font-bold text-emerald-400 tracking-widest uppercase mb-2 block">Arquitectura FinTech</span>
                <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-white leading-tight mb-6">
                    Seguridad de datos e integridad financiera
                </h2>
                <p class="text-slate-300 text-base leading-relaxed mb-8">
                    Este software reemplaza los procesos manuales propensos al error humano, garantizando un registro inmutable y consultable de todos los ingresos comunales bajo los más altos estándares de desarrollo.
                </p>

                <div class="space-y-4">
                    <div class="flex items-start space-x-3">
                        <div class="bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 p-1.5 rounded-lg mt-0.5">
                            <i data-lucide="database" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-white text-sm">Almacenamiento NoSQL (MongoDB)</h4>
                            <p class="text-slate-400 text-xs">Escalabilidad y rapidez en búsquedas de números de referencia bancaria masivos.</p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 p-1.5 rounded-lg mt-0.5">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-white text-sm">Encriptación y Trazabilidad</h4>
                            <p class="text-slate-400 text-xs">Cada voucher queda ligado criptográficamente al usuario que lo reportó para auditorías futuras.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-tr from-slate-900 to-slate-800 p-8 rounded-3xl border border-slate-700/80 glow-cyan relative" data-aos="zoom-in" data-aos-duration="1000">
                <div class="absolute -top-4 -right-4 bg-gradient-to-r from-cyan-500 to-emerald-400 text-slate-950 p-3 rounded-2xl font-bold shadow-lg">
                    <i data-lucide="shield" class="w-8 h-8 stroke-[2.5]"></i>
                </div>
                <span class="text-xs text-cyan-400 font-mono block mb-2">SYSTEM STATUS // ONLINE</span>
                <div class="text-2xl font-black text-white mb-6">Motor de Verificación</div>

                <div class="grid grid-cols-2 gap-4 border-t border-slate-800 pt-6">
                    <div>
                        <span class="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-blue-400 block mb-1">0%</span>
                        <span class="text-xs text-slate-400 font-medium">Margen de Duplicidad</span>
                    </div>
                    <div>
                        <span class="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-teal-400 to-emerald-400 block mb-1">PHP 8.5</span>
                        <span class="text-xs text-slate-400 font-medium">Backend de Alta Velocidad</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- FOOTER -->
<footer class="bg-slate-950 text-white py-12 border-t border-slate-900">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <div class="flex items-center justify-center space-x-2 mb-4">
            <i data-lucide="shield-check" class="w-5 h-5 text-cyan-400"></i>
            <span class="text-base font-black tracking-wider text-white">VOUCHER<span class="text-cyan-400">CHECK</span></span>
        </div>
        <p class="text-slate-500 text-xs max-w-md mx-auto mb-6">
            Sistema de Gestión y Auditoría de Comprobantes de Pago desarrollado como Trabajo de Grado para el control financiero comunal.
        </p>
        <div class="text-[11px] text-slate-600 font-mono">
            &copy; <?php echo date('Y'); ?> Todos los derechos reservados.
        </div>
    </div>
</footer>

<!-- SCRIPTS DE ICONOS, ANIMACIÓN Y SCROLL-SPY -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    // 1. Inicializar iconos
    lucide.createIcons();

    // 2. Inicializar animaciones AOS
    AOS.init({
        once: true,
        offset: 60,
        easing: 'ease-out-cubic',
    });

    // 3. Lógica de Scroll-Spy: Ilumina el link del menú según la posición actual
    document.addEventListener('DOMContentLoaded', () => {
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('#desktop-nav a');

        const highlightNavigation = () => {
            let scrollPosition = window.scrollY + 150;

            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.offsetHeight;
                const sectionId = section.getAttribute('id');

                if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                    navLinks.forEach(link => {
                        link.classList.remove('nav-link-active');
                        if (link.getAttribute('href') === `#${sectionId}`) {
                            link.classList.add('nav-link-active');
                        }
                    });
                }
            });
        };

        window.addEventListener('scroll', highlightNavigation);
        highlightNavigation();
    });
</script>
</body>
</html>