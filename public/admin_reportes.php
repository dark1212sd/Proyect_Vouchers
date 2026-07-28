<?php
// public/admin_reportes.php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'superuser'])) {
    header("Location: /auth/login.php"); exit();
}
require_once __DIR__ . '/config/db.php';

// Cálculos del mes en curso
$mesActual = date('m'); $anioActual = date('Y');
$pagos = iterator_to_array($db->pagos->find());

$totalMes = 0; $totalAprobado = 0; $metodos = ['pago_movil' => 0, 'transferencia' => 0, 'efectivo' => 0, 'electronico' => 0];

foreach ($pagos as $p) {
    $fechaPago = !empty($p['fecha_pago']) ? strtotime($p['fecha_pago']) : ($p['created_at'] ? $p['created_at']->toDateTime()->getTimestamp() : 0);
    if ($fechaPago && date('m', $fechaPago) === $mesActual && date('Y', $fechaPago) === $anioActual) {
        $monto = floatval((string)($p['monto'] ?? 0));
        $totalMes += $monto;
        if (in_array(strtolower($p['estado'] ?? ''), ['aprobado', 'validado', 'solvente'])) $totalAprobado += $monto;

        $m = $p['metodo_pago'] ?? 'transferencia';
        if(isset($metodos[$m])) $metodos[$m] += $monto;
    }
}
$porcentajeAprobado = ($totalMes > 0) ? round(($totalAprobado / $totalMes) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <title>Reportes - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { neon: { cyan: '#00f2fe', emerald: '#10b981' } } } } }</script>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex flex-col">

<header class="bg-slate-900/90 border-b border-slate-800/80 sticky top-0 z-40 print:hidden">
    <div class="max-w-[90rem] mx-auto px-4 h-20 flex items-center space-x-4">
        <a href="/dashboard.php" class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-cyan-400"><i data-lucide="arrow-left"></i></a>
        <div><span class="text-lg font-black text-white">REPORTE<span class="text-emerald-400">FINANCIERO</span></span></div>
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 py-8 w-full">

    <div class="flex justify-between items-end mb-8 print:hidden">
        <div>
            <h1 class="text-2xl font-black text-white">Cierre Mensual</h1>
            <p class="text-sm text-slate-400">Periodo: <?php echo date('F Y'); ?></p>
        </div>
        <button onclick="window.print()" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs border border-slate-700 flex items-center gap-2">
            <i data-lucide="printer" class="w-4 h-4"></i> Imprimir / PDF
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Ingresos Declarados (Mes)</h3>
            <span class="text-4xl font-black text-white block mb-4">Bs. <?php echo number_format($totalMes, 2, ',', '.'); ?></span>

            <div class="w-full bg-slate-950 h-2.5 rounded-full overflow-hidden mb-2">
                <div class="bg-emerald-400 h-full" style="width: <?php echo $porcentajeAprobado; ?>%"></div>
            </div>
            <div class="flex justify-between text-[10px] font-bold uppercase text-slate-500">
                <span>Auditado: <?php echo $porcentajeAprobado; ?>%</span>
                <span>Meta: 100%</span>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Distribución por Método</h3>
            <div class="space-y-4">
                <?php
                $colores = ['pago_movil' => 'bg-cyan-400', 'transferencia' => 'bg-teal-400', 'electronico' => 'bg-blue-400', 'efectivo' => 'bg-emerald-400'];
                $nombres = ['pago_movil' => 'Pago Móvil', 'transferencia' => 'Transferencia', 'electronico' => 'Divisa Digital', 'efectivo' => 'Efectivo'];
                foreach($metodos as $key => $valor):
                    $pct = ($totalMes > 0) ? round(($valor / $totalMes) * 100) : 0;
                    ?>
                    <div>
                        <div class="flex justify-between text-xs font-bold text-white mb-1">
                            <span><?php echo $nombres[$key]; ?></span>
                            <span>Bs. <?php echo number_format($valor, 2, ',', '.'); ?></span>
                        </div>
                        <div class="w-full bg-slate-950 h-1.5 rounded-full overflow-hidden">
                            <div class="<?php echo $colores[$key]; ?> h-full" style="width: <?php echo $pct; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</main>
<script>lucide.createIcons();</script>
</body>
</html>