<?php
session_start();
require __DIR__ . '/config/db.php';

// 1. Protección de ruta: Solo vecinos entran aquí
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vecino') {
    header("Location: auth/login.html");
    exit();
}

$username = $_SESSION['username'];

// 2. Obtener datos personales del usuario
$usuario = $db->usuarios->findOne(['username' => $username]);

// 3. Obtener historial de pagos
$mis_pagos = $db->vouchers->find(
    ['cedula_vecino' => $usuario['cedula']],
    ['sort' => ['fecha_declaracion' => -1]]
);

// 4. Lógica de Calendario (COMPATIBLE CON PHP 8+)
$meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$mes_siguiente = (int)date('n', strtotime('first day of next month')) - 1;
$proximo_pago = "05 de " . $meses[$mes_siguiente];

// Variables para sumar los totales en una sola pasada y evitar el error "Cursors cannot rewind"
$total_validado = 0;
$total_pendiente = 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Panel - Lanceros de la Victoria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <nav class="bg-white border-b border-slate-200 p-4 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="bg-blue-600 p-2 rounded-xl font-black text-white italic">L</div>
                <span class="font-extrabold tracking-tighter text-slate-800 text-lg uppercase">Portal <span class="text-blue-600">Vecino</span></span>
            </div>
            <div class="flex items-center gap-6">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">¡Hola, <?php echo htmlspecialchars(explode(' ', $usuario['nombre'])[0]); ?>!</span>
                <a href="auth/logout.php" class="bg-slate-100 text-slate-600 px-4 py-2 rounded-xl text-xs font-bold hover:bg-rose-50 hover:text-rose-600 transition-all">Salir</a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto p-6 md:p-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <div class="lg:col-span-4 space-y-8">
                <section class="bg-white p-8 rounded-[2.5rem] shadow-xl shadow-slate-200/50 border border-slate-100 animate__animated animate__fadeInLeft">
                    <div class="flex flex-col items-center text-center">
                        <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center text-4xl mb-4 border-4 border-white shadow-lg">👤</div>
                        <h2 class="text-xl font-black text-slate-800"><?php echo htmlspecialchars($usuario['nombre'] ?? 'Vecino'); ?></h2>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1"><?php echo htmlspecialchars($usuario['cedula']); ?></p>
                        <span class="mt-4 px-3 py-1 bg-emerald-100 text-emerald-600 text-[10px] font-black rounded-full uppercase">Cuenta Activa</span>
                    </div>

                    <div class="mt-8 pt-8 border-t border-slate-50 space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-black text-slate-400 uppercase">Usuario</span>
                            <span class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($usuario['username']); ?></span>
                        </div>
                    </div>
                </section>

                <section class="bg-blue-600 p-8 rounded-[2.5rem] shadow-xl shadow-blue-200 text-white animate__animated animate__fadeInLeft" style="animation-delay: 0.1s">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="bg-white/20 p-3 rounded-2xl">📅</div>
                        <h3 class="font-black uppercase tracking-tighter">Agenda Comunal</h3>
                    </div>
                    <p class="text-blue-100 text-xs font-medium mb-1">Tu próxima cuota vence el:</p>
                    <p class="text-2xl font-black"><?php echo $proximo_pago; ?></p>
                </section>
            </div>

            <div class="lg:col-span-8">
                <div class="flex justify-between items-end mb-6">
                    <div>
                        <h2 class="text-2xl font-black text-slate-800 tracking-tight">Mis Declaraciones</h2>
                        <p class="text-slate-400 text-xs font-bold uppercase mt-1">Historial completo de váuchers</p>
                    </div>
                    <a href="index.php" class="bg-slate-900 text-white px-6 py-3 rounded-2xl text-xs font-bold hover:bg-blue-600 transition-all shadow-lg shadow-slate-200 flex items-center gap-2">
                        <span>+</span> NUEVO PAGO
                    </a>
                </div>

                <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden animate__animated animate__fadeInUp">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th class="p-6 text-[10px] font-black text-slate-400 uppercase tracking-widest">Referencia</th>
                                <th class="p-6 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Monto</th>
                                <th class="p-6 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Estado</th>
                                <th class="p-6 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php
                            $count = 0;
                            foreach ($mis_pagos as $pago):
                                $count++;
                                $esPendiente = ($pago['estatus'] === 'pendiente');
                                $colorEstatus = $esPendiente ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600';

                                // SUMA OPTIMIZADA
                                $monto_actual = (float)(string)$pago['monto'];
                                if ($pago['estatus'] === 'validado') {
                                    $total_validado += $monto_actual;
                                } elseif ($pago['estatus'] === 'pendiente') {
                                    $total_pendiente += $monto_actual;
                                }

                                // Manejo seguro de la fecha
                                $fecha_texto = 'Desconocida';
                                if (isset($pago['fecha_declaracion']) && $pago['fecha_declaracion'] instanceof MongoDB\BSON\UTCDateTime) {
                                    $fecha_texto = $pago['fecha_declaracion']->toDateTime()->format('d/m/Y');
                                }
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-6">
                                    <p class="font-bold text-slate-700 text-sm">#<?php echo htmlspecialchars($pago['referencia_bancaria'] ?? 'N/A'); ?></p>
                                    <p class="text-[9px] text-slate-400 font-bold uppercase mt-0.5">Enviado: <?php echo $fecha_texto; ?></p>
                                </td>
                                <td class="p-6 text-center">
                                    <span class="font-black text-blue-600 text-sm">Bs. <?php echo number_format($monto_actual, 2); ?></span>
                                </td>
                                <td class="p-6 text-center">
                                    <span class="px-3 py-1.5 rounded-full text-[9px] font-black uppercase tracking-widest <?php echo $colorEstatus; ?>">
                                        <?php echo htmlspecialchars($pago['estatus']); ?>
                                    </span>
                                </td>

                                <td class="p-6 text-right space-y-2">
                                    <?php
                                    if (isset($pago['soporte_url']) && $pago['soporte_url'] != '') {
                                        $solo_nombre = basename($pago['soporte_url']);
                                        $ruta_final = "uploads/vouchers/" . $solo_nombre;
                                    ?>
                                        <a href="<?php echo htmlspecialchars($ruta_final); ?>" target="_blank"
                                           class="block text-[10px] font-black text-slate-400 hover:text-blue-600 transition-colors underline mb-2">
                                            VER IMAGEN
                                        </a>
                                    <?php } else { ?>
                                        <span class="block text-[10px] font-bold text-slate-300 mb-2">SIN FOTO</span>
                                    <?php } ?>

                                    <a href="chat_pago.php?id=<?php echo (string)$pago['_id']; ?>"
                                       class="inline-block bg-slate-900 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-blue-600 transition-all shadow-md w-full text-center">
                                        💬 Soporte
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if ($count === 0): ?>
                            <tr>
                                <td colspan="4" class="p-20 text-center">
                                    <div class="text-4xl mb-4">📭</div>
                                    <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">Aún no has reportado ningún pago</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mt-8">
                    <div class="bg-emerald-500/10 p-6 rounded-[2rem] border border-emerald-500/10 flex items-center gap-4">
                        <div class="text-2xl">✅</div>
                        <div>
                            <p class="text-[9px] font-black text-emerald-600 uppercase tracking-widest">Total Validado</p>
                            <span class="text-lg font-black text-slate-800">Bs. <?php echo number_format($total_validado, 2); ?></span>
                        </div>
                    </div>
                    <div class="bg-amber-500/10 p-6 rounded-[2rem] border border-amber-500/10 flex items-center gap-4">
                        <div class="text-2xl">⏳</div>
                        <div>
                            <p class="text-[9px] font-black text-amber-600 uppercase tracking-widest">En Revisión</p>
                            <span class="text-lg font-black text-slate-800">Bs. <?php echo number_format($total_pendiente, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>