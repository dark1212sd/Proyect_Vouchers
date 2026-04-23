<?php
session_start();
require __DIR__ . '/config/db.php';

// Protección: Solo Admin o Superuser pueden entrar
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superuser')) {
    header("Location: auth/login.html");
    exit();
}

// Variables para cálculos
$total_recaudado = 0;
$total_pendiente = 0;
$desglose_metodos = [
    'pagomovil' => 0,
    'transferencia' => 0,
    'efectivo' => 0,
    'electronico' => 0,
    'otro' => 0
];

$lista_validados = [];

// Obtener TODOS los váuchers para hacer el balance
$todos_los_pagos = $db->vouchers->find([], ['sort' => ['fecha_declaracion' => -1]]);

foreach ($todos_los_pagos as $pago) {
    $monto = (float)(string)($pago['monto'] ?? 0);
    $estatus = $pago['estatus'] ?? 'pendiente';
    $metodo = $pago['metodo_pago'] ?? 'otro';

    if ($estatus === 'validado') {
        $total_recaudado += $monto;

        // Sumar al método de pago correspondiente
        if (isset($desglose_metodos[$metodo])) {
            $desglose_metodos[$metodo] += $monto;
        } else {
            $desglose_metodos['otro'] += $monto;
        }

        // Guardar en la lista para la tabla (solo validados)
        $lista_validados[] = $pago;

    } elseif ($estatus === 'pendiente') {
        $total_pendiente += $monto;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Financieros - Lanceros</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }

        /* Ocultar elementos en la impresión PDF */
        @media print {
            .no-print { display: none !important; }
            body { background-color: white; }
            .print-shadow-none { box-shadow: none !important; border: 1px solid #e2e8f0; }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

<nav class="bg-slate-900 text-white p-4 shadow-xl sticky top-0 z-50 no-print">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
        <div class="flex items-center gap-3">
            <a href="dashboard.php" class="bg-emerald-500 p-2 rounded-lg font-black text-white text-xl flex items-center justify-center w-10 h-10">📁</a>
            <span class="font-extrabold tracking-tighter text-lg uppercase">Reportes <span class="text-emerald-400">Financieros</span></span>
        </div>
        <div class="flex items-center gap-6 text-[10px] font-black uppercase tracking-widest">
            <a href="dashboard.php" class="hover:text-emerald-400 transition-colors">Volver al Inicio</a>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto p-6 md:p-10">

    <header class="mb-10 flex flex-col md:flex-row md:justify-between md:items-end gap-4 animate__animated animate__fadeInDown">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight">Balance General</h1>
            <p class="text-slate-400 text-xs font-bold uppercase mt-1">Resumen de todos los ingresos validados</p>
        </div>

        <button onclick="window.print()" class="no-print bg-slate-900 text-white px-6 py-3 rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-lg flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Exportar a PDF
        </button>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10 animate__animated animate__fadeInUp">

        <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 p-8 rounded-[2.5rem] shadow-xl shadow-emerald-200 text-white print-shadow-none">
            <p class="text-emerald-100 text-xs font-black uppercase tracking-widest mb-2">Total Recaudado (Validado)</p>
            <span class="text-5xl font-black">Bs. <?php echo number_format($total_recaudado, 2); ?></span>
            <div class="mt-6 p-4 bg-white/10 rounded-2xl border border-white/20">
                <p class="text-xs font-bold text-emerald-50 flex justify-between">
                    <span>Dinero en tránsito (Por validar):</span>
                    <span>Bs. <?php echo number_format($total_pendiente, 2); ?></span>
                </p>
            </div>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] shadow-xl border border-slate-100 print-shadow-none">
            <p class="text-slate-400 text-xs font-black uppercase tracking-widest mb-6">Desglose por Método de Pago</p>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-bold text-slate-600 flex items-center gap-2"><span class="text-xl">📱</span> Pago Móvil</span>
                    <span class="text-sm font-black text-slate-800">Bs. <?php echo number_format($desglose_metodos['pagomovil'], 2); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-bold text-slate-600 flex items-center gap-2"><span class="text-xl">🏦</span> Transferencia</span>
                    <span class="text-sm font-black text-slate-800">Bs. <?php echo number_format($desglose_metodos['transferencia'], 2); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-bold text-slate-600 flex items-center gap-2"><span class="text-xl">💵</span> Efectivo</span>
                    <span class="text-sm font-black text-slate-800">Bs. <?php echo number_format($desglose_metodos['efectivo'], 2); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-bold text-slate-600 flex items-center gap-2"><span class="text-xl">💻</span> Electrónico (Zelle/PayPal)</span>
                    <span class="text-sm font-black text-slate-800">Bs. <?php echo number_format($desglose_metodos['electronico'], 2); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-[2.5rem] shadow-xl overflow-hidden border border-slate-100 animate__animated animate__fadeInUp print-shadow-none">
        <div class="p-6 bg-slate-50 border-b border-slate-100">
            <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">Historial de Ingresos Validados</h3>
        </div>
        <table class="w-full text-left">
            <thead class="bg-white border-b border-slate-100">
                <tr>
                    <th class="p-5 text-[9px] font-black text-slate-400 uppercase tracking-widest">Fecha / Ref</th>
                    <th class="p-5 text-[9px] font-black text-slate-400 uppercase tracking-widest">Vecino (Cédula)</th>
                    <th class="p-5 text-[9px] font-black text-slate-400 uppercase tracking-widest">Método</th>
                    <th class="p-5 text-[9px] font-black text-slate-400 uppercase tracking-widest text-right">Monto (Bs)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 text-sm">
                <?php
                $count = 0;
                foreach ($lista_validados as $pago):
                    $count++;

                    // Manejo de variables
                    $cedula_vecino = $pago['cedula_vecino'] ?? 'N/A';
                    $referencia = $pago['referencia_bancaria'] ?? 'N/A';
                    $monto = (float)(string)($pago['monto'] ?? 0);
                    $metodo = $pago['metodo_pago'] ?? 'N/A';

                    // Fecha segura
                    $fecha_texto = 'Desconocida';
                    if (isset($pago['fecha_declaracion']) && $pago['fecha_declaracion'] instanceof MongoDB\BSON\UTCDateTime) {
                        $fecha_texto = $pago['fecha_declaracion']->toDateTime()->format('d/m/Y');
                    }

                    // Buscar vecino
                    $vecino = $db->usuarios->findOne(['cedula' => $cedula_vecino]);
                    $nombre_vecino = $vecino ? ($vecino['nombre'] ?? 'Desconocido') : 'Desconocido';
                ?>
                <tr class="hover:bg-slate-50 transition-all">
                    <td class="p-5">
                        <p class="font-bold text-slate-700 text-xs"><?php echo $fecha_texto; ?></p>
                        <p class="text-[9px] font-mono text-slate-400 uppercase mt-1">Ref: <?php echo htmlspecialchars($referencia); ?></p>
                    </td>
                    <td class="p-5">
                        <p class="font-bold text-slate-700 text-xs"><?php echo htmlspecialchars($nombre_vecino); ?></p>
                        <p class="text-[9px] font-bold text-slate-400 mt-1">CI: <?php echo htmlspecialchars($cedula_vecino); ?></p>
                    </td>
                    <td class="p-5">
                        <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-md text-[10px] font-bold uppercase tracking-widest">
                            <?php echo htmlspecialchars($metodo); ?>
                        </span>
                    </td>
                    <td class="p-5 text-right font-black text-emerald-600">
                        <?php echo number_format($monto, 2); ?>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if($count === 0): ?>
                <tr>
                    <td colspan="4" class="p-10 text-center text-slate-400 font-bold uppercase text-xs">
                        Aún no hay ingresos validados para mostrar.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>