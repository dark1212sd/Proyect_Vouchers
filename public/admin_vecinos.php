<?php
session_start();
require __DIR__ . '/config/db.php';

// Protección: Solo Admin o Superuser pueden entrar
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superuser')) {
    header("Location: auth/login.html");
    exit();
}

// Procesar la edición del vecino
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_vecino'])) {
    $id_vecino = new MongoDB\BSON\ObjectId($_POST['id_vecino']);

    $updateData = [
        'nombre' => $_POST['nombre'],
        'dia_cobro' => (int)$_POST['dia_cobro']
    ];

    // Si escribió una contraseña nueva, la actualizamos
    if (!empty($_POST['nueva_password'])) {
        $updateData['password'] = password_hash($_POST['nueva_password'], PASSWORD_DEFAULT);
    }

    try {
        $db->usuarios->updateOne(['_id' => $id_vecino], ['$set' => $updateData]);
        $mensaje = "✅ Datos del vecino actualizados correctamente.";
    } catch (Exception $e) {
        $error = "❌ Error al actualizar.";
    }
}

// Obtener todos los usuarios que son vecinos
$vecinos = $db->usuarios->find(['role' => 'vecino'], ['sort' => ['nombre' => 1]]);
$total_v = $db->usuarios->countDocuments(['role' => 'vecino']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Vecinos - Lanceros</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen relative">

<nav class="bg-slate-900 text-white p-4 shadow-xl sticky top-0 z-40">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
        <div class="flex items-center gap-3">
            <a href="dashboard.php" class="bg-blue-600 p-2 rounded-lg font-black text-white italic text-xl">L</a>
            <span class="font-extrabold tracking-tighter text-lg uppercase">Panel <span class="text-blue-500">Admin</span></span>
        </div>
        <div class="flex items-center gap-6 text-[10px] font-black uppercase tracking-widest">
            <a href="dashboard.php" class="hover:text-blue-400 transition-colors">Volver al Inicio</a>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto p-6 md:p-10">
    <header class="mb-10 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight">Directorio de Vecinos</h1>
            <p class="text-slate-400 text-xs font-bold uppercase mt-1">Total registrados: <?php echo $total_v; ?></p>
        </div>
        <?php if(isset($mensaje)) echo "<span class='text-xs font-bold text-emerald-500 bg-emerald-50 px-4 py-2 rounded-xl animate-pulse border border-emerald-200'>$mensaje</span>"; ?>
    </header>

    <div class="bg-white rounded-[2.5rem] shadow-xl overflow-hidden border border-slate-100 animate__animated animate__fadeInUp">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="p-6 text-[9px] font-black text-slate-400 uppercase tracking-widest">Cédula</th>
                    <th class="p-6 text-[9px] font-black text-slate-400 uppercase tracking-widest">Nombre Completo</th>
                    <th class="p-6 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Día de Cobro</th>
                    <th class="p-6 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 text-sm">
                <?php foreach ($vecinos as $v): ?>
                <tr class="hover:bg-slate-50 transition-all">
                    <td class="p-6 font-bold text-slate-700"><?php echo htmlspecialchars($v['cedula']); ?></td>
                    <td class="p-6 text-slate-600 font-semibold">
                        <?php echo htmlspecialchars($v['nombre'] ?? 'Sin nombre'); ?>
                        <p class="text-[10px] text-slate-400 font-mono mt-1">Usuario: <?php echo htmlspecialchars($v['username']); ?></p>
                    </td>
                    <td class="p-6 text-center font-black text-blue-600 text-lg">
                        <?php echo htmlspecialchars($v['dia_cobro'] ?? '5'); ?>
                    </td>
                    <td class="p-6 flex justify-center gap-2">
                        <button onclick="abrirModal('<?php echo (string)$v['_id']; ?>', '<?php echo addslashes($v['nombre']); ?>', '<?php echo $v['dia_cobro'] ?? '5'; ?>')"
                                class="bg-slate-100 text-slate-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-slate-800 hover:text-white transition-all border border-slate-200">
                            Ajustes
                        </button>
                        <button onclick="window.location.href='dashboard.php?buscar=<?php echo htmlspecialchars($v['cedula']); ?>'"
                                class="bg-blue-100 text-blue-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-blue-600 hover:text-white transition-all border border-blue-200">
                            Ver Pagos
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate__animated animate__fadeIn animate__faster">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md border border-slate-100 overflow-hidden transform scale-95 transition-transform duration-300" id="modalContent">
        <div class="p-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-black text-slate-800">Ajustes del Vecino</h3>
                <button onclick="cerrarModal()" class="text-slate-400 hover:text-rose-500 font-bold text-xl">&times;</button>
            </div>

            <form action="" method="POST" class="space-y-5">
                <input type="hidden" name="id_vecino" id="modal_id">

                <div>
                    <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-2 ml-1">Nombre Completo</label>
                    <input type="text" name="nombre" id="modal_nombre" required
                           class="w-full px-5 py-4 rounded-2xl bg-slate-50 border-none focus:ring-4 focus:ring-blue-100 text-slate-700 font-semibold outline-none">
                </div>

                <div>
                    <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-2 ml-1">Día de Cobro (1 al 31)</label>
                    <input type="number" min="1" max="31" name="dia_cobro" id="modal_dia" required
                           class="w-full px-5 py-4 rounded-2xl bg-slate-50 border-none focus:ring-4 focus:ring-blue-100 text-blue-600 font-black outline-none">
                </div>

                <div class="bg-rose-50 p-5 rounded-2xl border border-rose-100 mt-4">
                    <label class="block text-[10px] font-extrabold text-rose-600 uppercase mb-2">Restablecer Contraseña (Opcional)</label>
                    <input type="password" name="nueva_password" placeholder="Dejar en blanco para no cambiar" minlength="6"
                           class="w-full px-5 py-3 rounded-xl bg-white border-none focus:ring-4 focus:ring-rose-200 text-slate-700 font-semibold outline-none text-sm">
                </div>

                <div class="pt-4">
                    <button type="submit" name="editar_vecino" class="w-full bg-slate-900 text-white font-bold py-4 rounded-2xl hover:bg-blue-600 transition-all shadow-xl">
                        GUARDAR CAMBIOS
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function abrirModal(id, nombre, dia) {
        document.getElementById('modal_id').value = id;
        document.getElementById('modal_nombre').value = nombre;
        document.getElementById('modal_dia').value = dia;

        const modal = document.getElementById('editModal');
        const content = document.getElementById('modalContent');
        modal.classList.remove('hidden');
        setTimeout(() => content.classList.replace('scale-95', 'scale-100'), 10);
    }

    function cerrarModal() {
        const modal = document.getElementById('editModal');
        const content = document.getElementById('modalContent');
        content.classList.replace('scale-100', 'scale-95');
        setTimeout(() => modal.classList.add('hidden'), 200);
    }
</script>
</body>
</html>