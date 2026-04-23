<?php
session_start();
require __DIR__ . '/config/db.php';

// Protección: Solo Admin o Superuser pueden entrar
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superuser')) {
    header("Location: auth/login.html");
    exit();
}

// Obtener solo los pagos con estatus "pendiente"
$pagos_pendientes = $db->vouchers->find(
    ['estatus' => 'pendiente'],
    ['sort' => ['fecha_declaracion' => 1]] // 1 para ver primero los más antiguos
);

$total_pendientes = $db->vouchers->countDocuments(['estatus' => 'pendiente']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validación de Pagos - Lanceros</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

<nav class="bg-slate-900 text-white p-4 shadow-xl sticky top-0 z-50">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
        <div class="flex items-center gap-3">
            <a href="dashboard.php" class="bg-amber-500 p-2 rounded-lg font-black text-white text-xl flex items-center justify-center w-10 h-10">⏳</a>
            <span class="font-extrabold tracking-tighter text-lg uppercase">Validaciones</span>
        </div>
        <div class="flex items-center gap-6 text-[10px] font-black uppercase tracking-widest">
            <a href="dashboard.php" class="hover:text-amber-400 transition-colors">Volver al Inicio</a>
            <span class="bg-slate-800 px-3 py-1 rounded-full border border-slate-700 text-emerald-400">En línea</span>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto p-6 md:p-10">

    <header class="mb-10 flex justify-between items-end animate__animated animate__fadeInDown">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight">Bandeja de Entrada</h1>
            <p class="text-slate-400 text-xs font-bold uppercase mt-1">
                Tienes <span class="text-amber-500 font-black"><?php echo $total_pendientes; ?></span> pagos por revisar.
            </p>
        </div>
        <?php if($total_pendientes > 0): ?>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-amber-500 animate-pulse"></span>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Trabajo Pendiente</span>
            </div>
        <?php else: ?>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Todo al día</span>
            </div>
        <?php endif; ?>
    </header>

    <div class="bg-white rounded-[2.5rem] shadow-xl overflow-hidden border border-slate-100 animate__animated animate__fadeInUp">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="p-6 text-[9px] font-black text-slate-400 uppercase tracking-widest">Propietario / Ref</th>
                    <th class="p-6 text-[9px] font-black text-slate-400 uppercase tracking-widest">Detalles del Pago</th>
                    <th class="p-6 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Comprobante</th>
                    <th class="p-6 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 text-sm">
                <?php
                $count = 0;
                foreach ($pagos_pendientes as $pago):
                    $count++;

                    // Extraer datos de forma segura (Defensiva)
                    $cedula_vecino = $pago['cedula_vecino'] ?? 'Sin Cédula';
                    $monto = isset($pago['monto']) ? (string)$pago['monto'] : '0.00';
                    $referencia = $pago['referencia_bancaria'] ?? 'N/A';
                    $metodo_pago = $pago['metodo_pago'] ?? 'antiguo';

                    // Manejo seguro de la fecha
                    $fecha_texto = 'Fecha desconocida';
                    if (isset($pago['fecha_declaracion']) && $pago['fecha_declaracion'] instanceof MongoDB\BSON\UTCDateTime) {
                        $fecha_texto = $pago['fecha_declaracion']->toDateTime()->format('d/m/Y H:i');
                    }

                    // Buscar el nombre del usuario basado en la cédula
                    $vecino = $db->usuarios->findOne(['cedula' => $cedula_vecino]);
                    $nombre_vecino = $vecino ? ($vecino['nombre'] ?? 'Usuario Desconocido') : 'Usuario Desconocido';
                ?>
                <tr class="hover:bg-slate-50 transition-all group" id="fila-<?php echo $pago['_id']; ?>">
                    <td class="p-6">
                        <p class="font-black text-slate-700 text-sm"><?php echo htmlspecialchars($nombre_vecino); ?></p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase mt-1">CI: <?php echo htmlspecialchars($cedula_vecino); ?></p>
                        <p class="text-[10px] font-mono text-slate-500 mt-1">Ref: <?php echo htmlspecialchars($referencia); ?></p>
                    </td>
                    <td class="p-6">
                        <span class="font-black text-emerald-600 block text-lg">Bs. <?php echo htmlspecialchars($monto); ?></span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase flex items-center gap-1 mt-1">
                            <?php
                                if($metodo_pago === 'efectivo') echo "💵 Efectivo";
                                elseif($metodo_pago === 'pagomovil') echo "📱 Pago Móvil";
                                elseif($metodo_pago === 'transferencia') echo "🏦 Transferencia";
                                elseif($metodo_pago === 'electronico') echo "💻 Pago Electrónico";
                                else echo "⚠️ Registro Antiguo";
                            ?>
                            <?php if(isset($pago['divisa'])) echo " <span class='bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full'>{$pago['divisa']}</span>"; ?>
                            <?php if(isset($pago['plataforma'])) echo " <span class='bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full'>{$pago['plataforma']}</span>"; ?>
                        </span>
                        <p class="text-[9px] text-slate-400 font-bold uppercase mt-2">Declarado el: <?php echo $fecha_texto; ?></p>
                    </td>
                    <td class="p-6 text-center">
                        <?php
                        if(isset($pago['soporte_url']) && $pago['soporte_url'] != '') {
                            // Extraemos solo el nombre de la imagen, evitando duplicidad de rutas
                            $solo_nombre = basename($pago['soporte_url']);
                            $ruta_final = "uploads/vouchers/" . $solo_nombre;
                        ?>
                            <a href="<?php echo htmlspecialchars($ruta_final); ?>" target="_blank"
                               class="inline-block bg-blue-50 text-blue-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-blue-600 hover:text-white transition-all border border-blue-100">
                                Ver Imagen
                            </a>
                        <?php } else { ?>
                            <span class="inline-block bg-slate-100 text-slate-400 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border border-slate-200">
                                Sin foto
                            </span>
                        <?php } ?>
                    </td>

                    <td class="p-6 flex justify-center items-center gap-2 h-full">
                        <a href="chat_pago.php?id=<?php echo (string)$pago['_id']; ?>"
                           class="bg-blue-100 text-blue-600 w-12 h-12 rounded-2xl flex items-center justify-center hover:bg-blue-600 hover:text-white hover:scale-110 transition-all shadow-md group-hover:shadow-xl" title="Abrir Soporte">
                            💬
                        </a>

                        <button onclick="validarPago('<?php echo (string)$pago['_id']; ?>')"
                                class="bg-slate-900 text-white w-12 h-12 rounded-2xl flex items-center justify-center hover:bg-emerald-500 hover:scale-110 transition-all shadow-md group-hover:shadow-xl" title="Aprobar Pago">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if($count === 0): ?>
                <tr>
                    <td colspan="4" class="p-16 text-center">
                        <div class="text-4xl mb-4">🎉</div>
                        <p class="text-slate-400 font-bold uppercase text-xs tracking-widest">No hay pagos pendientes de validación</p>
                        <p class="text-slate-300 text-[10px] mt-2">Todo el trabajo está al día.</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
async function validarPago(id) {
    if (!confirm('¿Confirmas que este pago es legítimo y el dinero está en la cuenta de la Junta?')) return;

    try {
        const response = await fetch('aprobar_pago.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });

        const result = await response.json();

        if (result.status === 'success') {
            const fila = document.getElementById('fila-' + id);
            fila.classList.add('animate__animated', 'animate__fadeOutRight');

            setTimeout(() => {
                fila.remove();
                const tbody = document.querySelector('tbody');
                if(tbody.children.length === 0) {
                    window.location.reload();
                }
            }, 500);

        } else {
            alert("Error al procesar: " + result.message);
        }
    } catch (error) {
        alert("Error de red al intentar aprobar.");
    }
}
</script>
</body>
</html>