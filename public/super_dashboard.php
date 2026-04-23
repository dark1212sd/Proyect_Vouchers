<?php
session_start();
require __DIR__ . '/config/db.php'; // Conexión a MongoDB

// 1. Protección de ruta
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superuser') {
    header("Location: auth/login.html");
    exit();
}

// 2. Procesar creación de nuevo administrador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_admin'])) {
    $nuevo_user = $_POST['username'];
    $pass_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $existe = $db->usuarios->findOne(['username' => $nuevo_user]);
    if (!$existe) {
        $db->usuarios->insertOne([
            'username' => $nuevo_user,
            'password' => $pass_hash,
            'role' => 'admin',
            'nombre' => $_POST['nombre'],
            'cedula' => $_POST['cedula'], // <--- AQUÍ SE GUARDA LA CÉDULA
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ]);
        $mensaje = "✅ Administrador creado correctamente.";
    } else {
        $error = "❌ El nombre de usuario ya existe.";
    }
}

// 3. Obtener datos para la vista
$admins = $db->usuarios->find(['role' => 'admin']);
$total_vecinos = $db->usuarios->countDocuments(['role' => 'vecino']);
$total_vouchers = $db->vouchers->countDocuments([]);
$pendientes = $db->vouchers->countDocuments(['estatus' => 'pendiente']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mando Maestro - Lanceros de la Victoria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 min-h-screen text-slate-200">

    <nav class="bg-slate-900/50 backdrop-blur-md border-b border-white/10 p-4 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="bg-red-600 p-2 rounded-lg font-black text-white italic">S</div>
                <span class="font-extrabold tracking-tighter text-white">SISTEMA <span class="text-red-500">ROOT</span></span>
            </div>
            <div class="flex items-center gap-6">
                <span class="text-[10px] text-slate-400 bg-white/5 px-3 py-1 rounded-full uppercase tracking-tighter">Superusuario: <?php echo $_SESSION['username']; ?></span>
                <a href="auth/logout.php" class="text-red-400 text-xs font-bold hover:text-red-300 transition-colors">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto p-6 md:p-10">

        <header class="mb-12">
            <h1 class="text-4xl font-black text-white mb-8">Panel de Control Global</h1>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-slate-900 p-6 rounded-3xl border border-white/5">
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Váuchers Totales</p>
                    <span class="text-3xl font-black text-white"><?php echo $total_vouchers; ?></span>
                </div>
                <div class="bg-slate-900 p-6 rounded-3xl border border-white/5">
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Por Validar</p>
                    <span class="text-3xl font-black text-amber-500"><?php echo $pendientes; ?></span>
                </div>
                <div class="bg-slate-900 p-6 rounded-3xl border border-white/5">
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mb-1">Vecinos Activos</p>
                    <span class="text-3xl font-black text-blue-500"><?php echo $total_vecinos; ?></span>
                </div>
                <div class="bg-red-600/10 p-6 rounded-3xl border border-red-600/20">
                    <p class="text-red-500/70 text-[10px] font-black uppercase tracking-widest mb-1">Servidores</p>
                    <span class="text-xl font-black text-red-500 italic uppercase">En Línea</span>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">

            <section class="lg:col-span-2">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-extrabold text-white">Administradores del Sistema</h2>
                    <?php if(isset($mensaje)) echo "<span class='text-xs font-bold text-emerald-400 animate-pulse'>$mensaje</span>"; ?>
                    <?php if(isset($error)) echo "<span class='text-xs font-bold text-red-400 animate-pulse'>$error</span>"; ?>
                </div>

                <div class="bg-slate-900 rounded-[2.5rem] overflow-hidden border border-white/5 shadow-2xl">
                    <table class="w-full text-left">
                        <thead class="bg-white/5 border-b border-white/5">
                            <tr>
                                <th class="p-6 text-[10px] font-black text-slate-500 uppercase tracking-widest">Nombre</th>
                                <th class="p-6 text-[10px] font-black text-slate-500 uppercase tracking-widest">Cédula</th>
                                <th class="p-6 text-[10px] font-black text-slate-500 uppercase tracking-widest">Usuario</th>
                                <th class="p-6 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <?php foreach ($admins as $ad): ?>
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="p-6 font-bold text-white"><?php echo $ad['nombre']; ?></td>
                                <td class="p-6 font-bold text-slate-300"><?php echo isset($ad['cedula']) ? $ad['cedula'] : 'N/A'; ?></td>
                                <td class="p-6 text-slate-400 font-mono text-xs"><?php echo $ad['username']; ?></td>
                                <td class="p-6 flex justify-center gap-3">
                                    <button class="bg-slate-800 text-slate-400 p-2 rounded-xl hover:bg-red-600 hover:text-white transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <aside>
                <div class="bg-gradient-to-br from-slate-900 to-slate-950 p-8 rounded-[2.5rem] border border-white/10 shadow-2xl">
                    <h3 class="text-lg font-black text-white mb-6 uppercase tracking-tighter">Registrar Administrador</h3>

                    <form action="" method="POST" class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 ml-1">Nombre Completo</label>
                            <input type="text" name="nombre" required placeholder="Ej: Juan Pérez"
                                   class="w-full bg-slate-800 border-none rounded-2xl px-5 py-4 text-sm text-white focus:ring-2 focus:ring-red-500 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 ml-1">Cédula</label>
                            <input type="text" name="cedula" required placeholder="Ej: V-12345678"
                                   class="w-full bg-slate-800 border-none rounded-2xl px-5 py-4 text-sm text-white focus:ring-2 focus:ring-red-500 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 ml-1">Nombre de Usuario</label>
                            <input type="text" name="username" required placeholder="admin_comunal"
                                   class="w-full bg-slate-800 border-none rounded-2xl px-5 py-4 text-sm text-white focus:ring-2 focus:ring-red-500 outline-none transition-all font-mono">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 ml-1">Contraseña Temporal</label>
                            <input type="password" name="password" required placeholder="••••••••"
                                   class="w-full bg-slate-800 border-none rounded-2xl px-5 py-4 text-sm text-white focus:ring-2 focus:ring-red-500 outline-none transition-all">
                        </div>

                        <div class="pt-4">
                            <button type="submit" name="crear_admin" class="w-full bg-red-600 text-white font-black py-5 rounded-2xl hover:bg-white hover:text-red-600 transform hover:-translate-y-1 transition-all shadow-xl shadow-red-900/20">
                                DAR ACCESO ADMIN
                            </button>
                        </div>
                    </form>
                </div>

                <div class="mt-6 bg-blue-600/5 p-6 rounded-[2rem] border border-blue-600/10">
                    <h4 class="text-xs font-black text-blue-500 uppercase mb-2">Nota de Seguridad</h4>
                    <p class="text-[10px] text-slate-500 leading-relaxed">Solo cree cuentas de administrador para personal autorizado de la Junta Comunal. Estas cuentas pueden validar y rechazar aportes financieros.</p>
                </div>
            </aside>
        </div>
    </div>

    <p class="text-center pb-10 text-slate-700 text-[9px] font-bold uppercase tracking-[0.4em]">Superuser Master Console • Grupo 16 UNETI</p>

</body>
</html>