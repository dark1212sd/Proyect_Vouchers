<?php
// public/admin_vecinos.php
session_start();

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

// Verificación estricta de seguridad: Solo Admins
$rol = $_SESSION['role'] ?? $_SESSION['rol'] ?? 'user';
if (!isset($_SESSION['user_id']) || !in_array($rol, ['admin', 'superuser'])) {
    header('Location: auth/login.php');
    exit();
}

require_once __DIR__ . '/config/db.php';

// Obtener todos los usuarios que NO son administradores
$cursorVecinos = $db->usuarios->find(
        ['role' => ['$ne' => 'admin']],
        ['sort' => ['apto' => 1, 'departamento' => 1, 'nombre' => 1]]
);
$vecinos = iterator_to_array($cursorVecinos);
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Vecinos - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .modal-enter { animation: fadeIn 0.2s ease-out forwards; }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .glow-cyan { box-shadow: 0 0 20px -5px rgba(0, 242, 254, 0.4); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen selection:bg-cyan-500 selection:text-black flex flex-col">

<!-- BARRA DE NAVEGACIÓN -->
<header class="bg-slate-900/90 border-b border-slate-800/80 sticky top-0 z-40 backdrop-blur-md">
    <div class="max-w-7xl mx-auto px-4 h-20 flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a href="dashboard.php" class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-cyan-400 transition-colors" title="Volver al Dashboard">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <span class="text-lg font-black text-white tracking-tight">DIRECTORIO<span class="text-cyan-400">VECINAL</span></span>
                <span class="text-[10px] font-bold text-slate-500 tracking-widest uppercase block mt-0.5">Gestión de Usuarios</span>
            </div>
        </div>

        <button onclick="abrirModalVecino('crear')" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-400 hover:to-blue-400 text-slate-950 font-black text-xs transition-all flex items-center gap-2 glow-cyan">
            <i data-lucide="user-plus" class="w-4 h-4"></i>
            <span class="hidden sm:inline">Registrar Vecino</span>
        </button>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-grow">

    <!-- CONTENEDOR DE LA TABLA -->
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-3xl overflow-hidden shadow-2xl backdrop-blur-sm">
        <div class="p-6 border-b border-slate-800/80 flex items-center justify-between bg-slate-900/90">
            <div>
                <h2 class="text-base font-extrabold text-white flex items-center gap-2">
                    <i data-lucide="users" class="w-5 h-5 text-cyan-400"></i> Comunidad Activa
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Lista de residentes con acceso al portal.</p>
            </div>
            <span class="bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 text-xs font-bold px-3 py-1 rounded-full">
                <?php echo count($vecinos); ?> Registrados
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                <tr class="border-b border-slate-800/80 bg-slate-950 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                    <th class="py-4 px-6">Apto</th>
                    <th class="py-4 px-6">Nombre del Residente</th>
                    <th class="py-4 px-6">Cédula / ID</th>
                    <th class="py-4 px-6">Contacto (Correo)</th>
                    <th class="py-4 px-6 text-right">Acciones</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-xs text-slate-300 font-medium">
                <?php if (empty($vecinos)): ?>
                    <tr>
                        <td colspan="5" class="py-12 text-center text-slate-500">
                            <i data-lucide="users-2" class="w-12 h-12 mx-auto mb-3 text-cyan-500/30"></i>
                            <p class="font-bold text-sm text-white">Directorio Vacío</p>
                            <p class="text-xs mt-1">Aún no has registrado ningún residente en el sistema.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($vecinos as $v):
                        $idStr = (string)$v['_id'];
                        $apto = $v['apto'] ?? $v['departamento'] ?? 'S/A';
                        $nombre = $v['nombre'] ?? $v['username'] ?? 'Desconocido';
                        $cedula = $v['cedula'] ?? 'S/C';
                        $correo = $v['email'] ?? $v['correo'] ?? 'Sin correo';
                        $datosJSON = htmlspecialchars(json_encode([
                                'id' => $idStr,
                                'nombre' => $nombre,
                                'apto' => $apto,
                                'cedula' => $cedula,
                                'email' => $correo
                        ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="py-4 px-6">
                                    <span class="font-black text-cyan-400 text-sm bg-cyan-500/10 px-3 py-1 rounded-lg border border-cyan-500/20">
                                        <?php echo htmlspecialchars($apto); ?>
                                    </span>
                            </td>
                            <td class="py-4 px-6 font-bold text-white text-sm flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-cyan-400 shrink-0">
                                    <?php echo strtoupper(substr($nombre, 0, 1)); ?>
                                </div>
                                <?php echo htmlspecialchars($nombre); ?>
                            </td>
                            <td class="py-4 px-6 font-mono text-slate-400">
                                <?php echo htmlspecialchars($cedula); ?>
                            </td>
                            <td class="py-4 px-6 text-slate-400">
                                <?php echo htmlspecialchars($correo); ?>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick="abrirModalVecino('editar', <?php echo $datosJSON; ?>)" title="Editar Datos" class="p-2 rounded-lg bg-slate-800 hover:bg-cyan-500/20 text-slate-400 hover:text-cyan-400 transition-colors border border-slate-700 hover:border-cyan-500/30">
                                        <i data-lucide="edit-2" class="w-4 h-4"></i>
                                    </button>
                                    <button onclick="resetearClave('<?php echo $idStr; ?>', '<?php echo htmlspecialchars($nombre, ENT_QUOTES); ?>')" title="Reiniciar Contraseña a: 123456" class="p-2 rounded-lg bg-slate-800 hover:bg-amber-500/20 text-slate-400 hover:text-amber-400 transition-colors border border-slate-700 hover:border-amber-500/30">
                                        <i data-lucide="key-round" class="w-4 h-4"></i>
                                    </button>
                                    <button onclick="eliminarVecino('<?php echo $idStr; ?>', '<?php echo htmlspecialchars($nombre, ENT_QUOTES); ?>')" title="Eliminar Registro" class="p-2 rounded-lg bg-slate-800 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 transition-colors border border-slate-700 hover:border-rose-500/30">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- MODAL: CREAR / EDITAR VECINO -->
<div id="modalVecino" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-slate-900 border border-slate-700 rounded-3xl max-w-md w-full overflow-hidden shadow-2xl modal-enter relative">
        <div id="modalHeaderColor" class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-cyan-400 to-blue-500 z-10"></div>

        <div class="p-6 border-b border-slate-800/80 flex items-center justify-between bg-slate-900 z-10">
            <div>
                <h3 id="modalTitle" class="text-base font-extrabold text-white">Registrar Vecino</h3>
                <p class="text-xs text-slate-400 mt-0.5">Ingresa los datos del residente.</p>
            </div>
            <button type="button" onclick="cerrarModalVecino()" class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="formVecino" class="p-6 space-y-4">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="user_id" id="formUserId" value="">

            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Nombre Completo</label>
                <input type="text" name="nombre" id="inputNombre" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Apartamento</label>
                    <input type="text" name="apto" id="inputApto" required placeholder="Ej: 4-B" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm font-bold text-cyan-400 focus:outline-none focus:border-cyan-500 uppercase">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Cédula / ID</label>
                    <input type="text" name="cedula" id="inputCedula" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm font-mono text-white focus:outline-none focus:border-cyan-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Correo Electrónico (Login)</label>
                <input type="email" name="email" id="inputEmail" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-500">
            </div>

            <div id="campoPassword">
                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Contraseña de Acceso</label>
                <input type="text" name="password" id="inputPassword" placeholder="Ej: 123456" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm font-mono text-white focus:outline-none focus:border-cyan-500">
                <p class="text-[10px] text-slate-500 mt-1">El usuario podrá cambiarla luego en su panel.</p>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="cerrarModalVecino()" class="w-1/3 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition-all">Cancelar</button>
                <button type="submit" id="btnSubmitVecino" class="w-2/3 py-3 rounded-xl bg-cyan-500 hover:bg-cyan-600 text-slate-950 font-extrabold text-xs shadow-lg transition-all flex items-center justify-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> <span id="btnSubmitText">Guardar Vecino</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    lucide.createIcons();

    const modal = document.getElementById('modalVecino');
    const form = document.getElementById('formVecino');

    function abrirModalVecino(modo, datos = null) {
        form.reset();
        document.getElementById('formAction').value = modo === 'crear' ? 'create' : 'update';

        const titulo = document.getElementById('modalTitle');
        const btnText = document.getElementById('btnSubmitText');
        const headerColor = document.getElementById('modalHeaderColor');
        const campoPass = document.getElementById('campoPassword');

        if (modo === 'crear') {
            titulo.innerText = 'Registrar Nuevo Vecino';
            btnText.innerText = 'Guardar Vecino';
            headerColor.className = 'absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-cyan-400 to-blue-500 z-10';
            campoPass.classList.remove('hidden');
            document.getElementById('inputPassword').required = true;
        } else {
            titulo.innerText = 'Editar Datos del Vecino';
            btnText.innerText = 'Actualizar Datos';
            headerColor.className = 'absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-400 to-cyan-500 z-10';

            // Ocultar campo de contraseña al editar (se cambia por el botón de reset en la tabla)
            campoPass.classList.add('hidden');
            document.getElementById('inputPassword').required = false;

            // Cargar datos
            document.getElementById('formUserId').value = datos.id;
            document.getElementById('inputNombre').value = datos.nombre;
            document.getElementById('inputApto').value = datos.apto;
            document.getElementById('inputCedula').value = datos.cedula;
            document.getElementById('inputEmail').value = datos.email;
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function cerrarModalVecino() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // ENVÍO DE FORMULARIO (CREAR O ACTUALIZAR)
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitVecino');
        const txtOrig = btn.innerHTML;
        btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Procesando...`;
        btn.disabled = true;

        const formData = new FormData(this);

        try {
            const response = await fetch('api_vecinos.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.status === 'success') {
                window.location.reload();
            } else {
                alert('❌ Error: ' + data.message);
                btn.innerHTML = txtOrig; btn.disabled = false; lucide.createIcons();
            }
        } catch (error) {
            alert('❌ Error de conexión con el servidor.');
            btn.innerHTML = txtOrig; btn.disabled = false; lucide.createIcons();
        }
    });

    // REINICIAR CONTRASEÑA
    async function resetearClave(id, nombre) {
        if (!confirm(`¿Seguro que deseas reiniciar la contraseña de ${nombre} a "123456"?`)) return;

        const formData = new FormData();
        formData.append('action', 'reset_password');
        formData.append('user_id', id);

        try {
            const response = await fetch('api_vecinos.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.status === 'success') {
                alert(`✅ Contraseña de ${nombre} reiniciada a: 123456`);
            } else {
                alert('❌ Error: ' + data.message);
            }
        } catch (error) {
            alert('❌ Error de red.');
        }
    }

    // ELIMINAR VECINO
    async function eliminarVecino(id, nombre) {
        if (!confirm(`⚠️ ADVERTENCIA: ¿Estás totalmente seguro de eliminar a ${nombre}?\n\nEsta acción borrará su acceso al sistema.`)) return;

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('user_id', id);

        try {
            const response = await fetch('api_vecinos.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.status === 'success') {
                window.location.reload();
            } else {
                alert('❌ Error: ' + data.message);
            }
        } catch (error) {
            alert('❌ Error de red.');
        }
    }
</script>
</body>
</html>