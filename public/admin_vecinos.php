<?php
// public/admin_vecinos.php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Protección de sesión
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'superuser'])) {
    header("Location: /auth/login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

// Búsqueda de usuarios (Filtro en tiempo real)
$filtro = $_GET['buscar'] ?? '';
$condicion = [];
if (!empty($filtro)) {
    $condicion['$or'] = [
            ['nombre' => new MongoDB\BSON\Regex($filtro, 'i')],
            ['cedula' => new MongoDB\BSON\Regex($filtro, 'i')],
            ['apartamento' => new MongoDB\BSON\Regex($filtro, 'i')],
            ['email' => new MongoDB\BSON\Regex($filtro, 'i')]
    ];
}

// Obtenemos los usuarios ordenados alfabéticamente
$usuarios = iterator_to_array($db->usuarios->find($condicion, ['sort' => ['nombre' => 1]]));
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Vecinos - Admin</title>

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
                        neon: { cyan: '#00f2fe', emerald: '#10b981', amber: '#f59e0b' }
                    }
                }
            }
        }
    </script>

    <style>
        .glow-cyan { box-shadow: 0 0 25px -5px rgba(0, 242, 254, 0.3); }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .animate-modal { animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex flex-col selection:bg-cyan-500 selection:text-slate-950">

<!-- HEADER ADMIN -->
<header class="bg-slate-900/90 border-b border-slate-800/80 backdrop-blur-md sticky top-0 z-40">
    <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a href="/dashboard.php" class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-cyan-400 transition-colors" title="Volver al Dashboard">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <span class="text-lg font-black tracking-tight text-white block leading-none">GESTIÓN<span class="text-cyan-400">VECINAL</span></span>
                <span class="text-[10px] font-bold text-slate-500 tracking-widest uppercase block mt-0.5">Directorio de Residentes</span>
            </div>
        </div>
        <div class="hidden sm:flex items-center gap-2">
                <span class="px-3 py-1 rounded-full bg-slate-800 border border-slate-700 text-xs font-bold text-slate-300">
                    Total: <span class="text-cyan-400"><?php echo count($usuarios); ?></span>
                </span>
        </div>
    </div>
</header>

<!-- CONTENIDO PRINCIPAL -->
<main class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-grow w-full">

    <!-- BARRA DE HERRAMIENTAS -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 flex flex-col sm:flex-row justify-between items-center gap-4 shadow-lg">
        <!-- Buscador -->
        <form action="" method="GET" class="w-full sm:w-auto flex gap-2">
            <div class="relative w-full sm:w-80">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500"><i data-lucide="search" class="w-4 h-4"></i></div>
                <input type="text" name="buscar" value="<?php echo htmlspecialchars($filtro); ?>" placeholder="Buscar por nombre, cédula, correo o apto..." class="w-full pl-9 pr-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-xs focus:outline-none focus:border-cyan-400 transition-all">
            </div>
            <button type="submit" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition-all">Buscar</button>
            <?php if(!empty($filtro)): ?>
                <a href="/admin_vecinos.php" class="px-3 py-2.5 bg-rose-500/10 text-rose-400 rounded-xl border border-rose-500/20 hover:bg-rose-500 hover:text-white transition-all"><i data-lucide="x" class="w-4 h-4"></i></a>
            <?php endif; ?>
        </form>

        <!-- Botón Registrar -->
        <button onclick="document.getElementById('modalNuevoVecino').classList.remove('hidden')" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-emerald-400 text-slate-950 font-extrabold text-xs glow-cyan hover:scale-[1.02] transition-all flex items-center justify-center gap-2 shadow-lg">
            <i data-lucide="user-plus" class="w-4 h-4 stroke-[2.5]"></i>
            <span>Registrar Residente</span>
        </button>
    </div>

    <!-- TABLA DE VECINOS -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 shadow-xl overflow-hidden">
        <div class="overflow-x-auto no-scrollbar">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead>
                <tr class="border-b border-slate-800 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    <th class="py-3 px-4">Residente</th>
                    <th class="py-3 px-4">Contacto (Email / Tel)</th>
                    <th class="py-3 px-4">Cédula</th>
                    <th class="py-3 px-4">Ubicación</th>
                    <th class="py-3 px-4">Rol en Sistema</th>
                    <th class="py-3 px-4 text-right">Acciones</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-xs text-slate-300">
                <?php if(count($usuarios) > 0): ?>
                    <?php foreach ($usuarios as $usr):
                        $rol = $usr['role'] ?? 'user';
                        $badgeRol = ($rol === 'admin' || $rol === 'superuser') ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20';
                        $textoRol = ($rol === 'admin') ? 'Tesorero' : (($rol === 'superuser') ? 'Súper Admin' : 'Vecino');
                        ?>
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="py-3.5 px-4 font-bold text-white flex items-center gap-3">
                                <?php if(!empty($usr['avatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($usr['avatar']); ?>" class="w-8 h-8 rounded-full object-cover border border-slate-700">
                                <?php else: ?>
                                    <div class="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center text-slate-500"><i data-lucide="user" class="w-4 h-4"></i></div>
                                <?php endif; ?>
                                <div class="flex flex-col">
                                    <span><?php echo htmlspecialchars($usr['nombre'] ?? $usr['username']); ?></span>
                                    <span class="text-[9px] text-slate-500 font-normal">@<?php echo htmlspecialchars($usr['username'] ?? 'N/A'); ?></span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="block text-cyan-400 font-mono text-[10px]"><?php echo htmlspecialchars($usr['email'] ?? 'Sin correo'); ?></span>
                                <span class="block text-slate-400 mt-0.5"><?php echo htmlspecialchars($usr['telefono'] ?? 'Sin teléfono'); ?></span>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-slate-400"><?php echo htmlspecialchars($usr['cedula'] ?? 'S/R'); ?></td>
                            <td class="py-3.5 px-4">
                                <span class="font-bold text-emerald-400 block"><?php echo htmlspecialchars($usr['apartamento'] ?? 'N/A'); ?></span>
                                <span class="text-[10px] text-slate-500 block"><?php echo htmlspecialchars($usr['torre'] ?? 'Sin Torre'); ?></span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-1 rounded-md text-[9px] font-black uppercase tracking-widest border <?php echo $badgeRol; ?>"><?php echo $textoRol; ?></span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <button class="p-1.5 rounded-lg bg-slate-800 hover:bg-cyan-500 hover:text-slate-950 transition-colors inline-block shadow" title="Editar Perfil"><i data-lucide="edit" class="w-4 h-4"></i></button>
                                <button class="p-1.5 rounded-lg bg-slate-800 hover:bg-rose-500 hover:text-white transition-colors inline-block ml-1 shadow" title="Suspender Usuario"><i data-lucide="ban" class="w-4 h-4"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-500">
                            <i data-lucide="users" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                            No se encontraron residentes con ese criterio de búsqueda.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- ==========================================
     MODAL: REGISTRAR NUEVO VECINO (Actualizado)
     ========================================== -->
<div id="modalNuevoVecino" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 sm:p-8 relative overflow-hidden shadow-2xl animate-modal">
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 to-emerald-400"></div>

        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-black text-white flex items-center gap-2">
                <i data-lucide="user-plus" class="w-5 h-5 text-cyan-400"></i>
                Nuevo Residente
            </h3>
            <button onclick="document.getElementById('modalNuevoVecino').classList.add('hidden')" class="text-slate-500 hover:text-white transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Formulario conectado a procesar_registro.php -->
        <form action="/auth/procesar_registro.php" method="POST" class="space-y-4 text-xs">

            <div>
                <label class="block font-bold text-slate-400 mb-1.5">Nombre Completo</label>
                <input type="text" name="nombre" required placeholder="Ej: Emerson Rodríguez" class="w-full px-3 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white focus:border-cyan-400 focus:outline-none transition-colors">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-400 mb-1.5">Cédula</label>
                    <input type="text" name="cedula" required placeholder="Ej: V-28192031" class="w-full px-3 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white focus:border-cyan-400 focus:outline-none transition-colors">
                </div>
                <div>
                    <label class="block font-bold text-slate-400 mb-1.5">Apto. Asignado</label>
                    <input type="text" name="apartamento" placeholder="Ej: 4-B" class="w-full px-3 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white focus:border-cyan-400 focus:outline-none transition-colors">
                </div>
            </div>

            <!-- NUEVO CAMPO: CORREO ELECTRÓNICO OBLIGATORIO -->
            <div>
                <label class="block font-bold text-slate-400 mb-1.5">Correo Electrónico <span class="text-rose-400">*</span></label>
                <input type="email" name="email" required placeholder="Para envío de recibos digitales" class="w-full px-3 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white font-bold text-cyan-400 focus:border-cyan-400 focus:outline-none transition-colors">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-400 mb-1.5">Usuario de Login</label>
                    <input type="text" name="username" required placeholder="Ej: emerson_r" class="w-full px-3 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white focus:border-cyan-400 focus:outline-none transition-colors">
                </div>
                <div>
                    <label class="block font-bold text-slate-400 mb-1.5">Contraseña</label>
                    <input type="password" name="password" required placeholder="Clave temporal" class="w-full px-3 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white focus:border-cyan-400 focus:outline-none transition-colors">
                </div>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="document.getElementById('modalNuevoVecino').classList.add('hidden')" class="w-1/2 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold transition-colors">Cancelar</button>
                <button type="submit" class="w-1/2 py-3 rounded-xl bg-gradient-to-r from-cyan-500 to-emerald-400 hover:opacity-90 text-slate-950 font-extrabold transition-opacity flex items-center justify-center gap-1.5 glow-cyan">
                    <i data-lucide="save" class="w-4 h-4"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SCRIPTS -->
<script>
    lucide.createIcons();

    // Cierra el modal si se hace clic fuera del contenido
    window.addEventListener('click', function(e) {
        const modal = document.getElementById('modalNuevoVecino');
        if (e.target === modal) {
            modal.classList.add('hidden');
        }
    });
</script>
</body>
</html>