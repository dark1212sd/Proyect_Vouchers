<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: auth/login.html");
    exit();
}

require __DIR__ . '/config/db.php';

// 1. OBTENER ESTADÍSTICAS
$total_recaudado = 0;
$total_pendiente = 0;
$vouchers = $db->vouchers->find();
foreach ($vouchers as $v) {
    if ($v['estatus'] === 'validado') $total_recaudado += (float)(string)$v['monto'];
    if ($v['estatus'] === 'pendiente') $total_pendiente += (float)(string)$v['monto'];
}
$total_registros = $db->vouchers->countDocuments();
$pagos_pendientes = $db->vouchers->countDocuments(['estatus' => 'pendiente']);

// 2. LÓGICA DEL CALENDARIO DE COBROS
$mes_actual = date('n');
$anio_actual = date('Y');
$dias_del_mes = cal_days_in_month(CAL_GREGORIAN, $mes_actual, $anio_actual);
$primer_dia_semana = date('w', mktime(0, 0, 0, $mes_actual, 1, $anio_actual)); // 0 (Dom) a 6 (Sab)

// Agrupar vecinos por su "Día de cobro"
$vecinos = $db->usuarios->find(['role' => 'vecino'], ['sort' => ['nombre' => 1]]);
$cobros_por_dia = [];

foreach ($vecinos as $vecino) {
    // Si el vecino no tiene un día configurado, asumimos que es el día 5 por defecto
    $dia_cobro = $vecino['dia_cobro'] ?? 5;
    $nombre_corto = explode(' ', $vecino['nombre'])[0];
    $cobros_por_dia[$dia_cobro][] = $nombre_corto;
}

$nombres_meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$nombre_mes_actual = $nombres_meses[$mes_actual - 1];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administración - Lanceros</title>
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
            <div class="bg-blue-600 p-2 rounded-lg font-black text-white italic text-xl">L</div>
            <span class="font-extrabold tracking-tighter text-lg uppercase">Lanceros <span class="text-blue-500">Admin</span></span>
        </div>
        <div class="flex items-center gap-6 text-[10px] font-black uppercase tracking-widest">
            <span class="bg-slate-800 px-3 py-1 rounded-full border border-slate-700 text-emerald-400">Admin: <?php echo $_SESSION['username']; ?></span>
            <a href="auth/logout.php" class="text-red-400 hover:text-red-300 transition-colors">Salir</a>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto p-6 md:p-10">

    <header class="mb-10 animate__animated animate__fadeInDown">
        <h2 class="text-3xl font-black text-slate-800 tracking-tight">Panel de Control Principal</h2>
        <p class="text-slate-400 text-xs font-bold uppercase mt-1">Selecciona una acción o revisa el calendario</p>
    </header>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12 animate__animated animate__fadeInUp">

        <a href="admin_validaciones.php" class="block bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200 hover:shadow-xl hover:border-amber-400 hover:-translate-y-1 transition-all group cursor-pointer relative overflow-hidden">
            <?php if($pagos_pendientes > 0): ?>
                <span class="absolute top-4 right-4 bg-rose-500 text-white w-6 h-6 flex items-center justify-center rounded-full text-xs font-bold animate-pulse"><?php echo $pagos_pendientes; ?></span>
            <?php endif; ?>
            <div class="bg-amber-100 text-amber-600 w-14 h-14 flex items-center justify-center rounded-2xl text-2xl mb-4 group-hover:scale-110 transition-transform">⏳</div>
            <h3 class="font-black text-slate-800 text-lg">Por Validar</h3>
            <p class="text-[10px] font-bold text-slate-400 uppercase mt-1">Bs. <?php echo number_format($total_pendiente, 2); ?></p>
            <p class="text-xs text-slate-500 mt-2">Revisar y aprobar los últimos pagos reportados.</p>
        </a>

        <a href="admin_vecinos.php" class="block bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200 hover:shadow-xl hover:border-blue-500 hover:-translate-y-1 transition-all group cursor-pointer">
            <div class="bg-blue-100 text-blue-600 w-14 h-14 flex items-center justify-center rounded-2xl text-2xl mb-4 group-hover:scale-110 transition-transform">👥</div>
            <h3 class="font-black text-slate-800 text-lg">Gestión Usuarios</h3>
            <p class="text-[10px] font-bold text-slate-400 uppercase mt-1">Configurar vecinos</p>
            <p class="text-xs text-slate-500 mt-2">Claves, datos básicos y días de cobro asignados.</p>
        </a>

        <a href="admin_reportes.php" class="block bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200 hover:shadow-xl hover:border-emerald-500 hover:-translate-y-1 transition-all group cursor-pointer">
            <div class="bg-emerald-100 text-emerald-600 w-14 h-14 flex items-center justify-center rounded-2xl text-2xl mb-4 group-hover:scale-110 transition-transform">💰</div>
            <h3 class="font-black text-slate-800 text-lg">Reportes</h3>
            <p class="text-[10px] font-bold text-slate-400 uppercase mt-1">Recaudado: Bs. <?php echo number_format($total_recaudado, 2); ?></p>
            <p class="text-xs text-slate-500 mt-2">Generar balances e historial financiero.</p>
        </a>

        <a href="admin_ajustes.php" class="block bg-slate-900 p-6 rounded-[2rem] shadow-lg shadow-slate-200 hover:shadow-xl hover:bg-blue-600 hover:-translate-y-1 transition-all group cursor-pointer text-white">
            <div class="bg-white/10 text-white w-14 h-14 flex items-center justify-center rounded-2xl text-2xl mb-4 group-hover:scale-110 transition-transform">⚙️</div>
            <h3 class="font-black text-white text-lg">Ajustes del Portal</h3>
            <p class="text-[10px] font-bold text-slate-300 uppercase mt-1">Cuentas bancarias</p>
            <p class="text-xs text-slate-400 group-hover:text-blue-100 mt-2">Editar instrucciones de pago y cuota mensual.</p>
        </a>
    </div>

    <div class="bg-white rounded-[2.5rem] shadow-xl overflow-hidden border border-slate-200 animate__animated animate__fadeInUp">
        <div class="p-6 md:p-8 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Calendario de Cobros</h2>
                <p class="text-slate-500 text-xs font-bold uppercase mt-1">Mes actual: <span class="text-blue-600"><?php echo $nombre_mes_actual . ' ' . $anio_actual; ?></span></p>
            </div>
            <div class="hidden md:flex gap-4">
                <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-rose-100 border border-rose-300"></span><span class="text-[10px] font-bold text-slate-500 uppercase">Día de pago</span></div>
                <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-slate-100 border border-slate-200"></span><span class="text-[10px] font-bold text-slate-500 uppercase">Sin eventos</span></div>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <div class="grid grid-cols-7 gap-2 mb-2 text-center">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Dom</div>
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Lun</div>
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Mar</div>
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Mié</div>
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Jue</div>
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Vie</div>
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Sáb</div>
            </div>

            <div class="grid grid-cols-7 gap-2 md:gap-4">
                <?php
                // Rellenar espacios en blanco al inicio del mes
                for ($i = 0; $i < $primer_dia_semana; $i++) {
                    echo '<div class="min-h-[80px] rounded-2xl bg-transparent"></div>';
                }

                // Generar los días del mes
                for ($dia = 1; $dia <= $dias_del_mes; $dia++) {
                    $es_hoy = ($dia == date('j'));
                    $hay_cobro = isset($cobros_por_dia[$dia]);

                    // Clases dinámicas dependiendo de si hay cobro y si es hoy
                    $bg_class = $hay_cobro ? 'bg-rose-50 border-rose-200' : 'bg-slate-50 border-slate-100';
                    $text_class = $es_hoy ? 'bg-blue-600 text-white shadow-md' : 'text-slate-600';
                    $border_class = $es_hoy ? 'border-blue-500 ring-2 ring-blue-200 ring-offset-2' : '';

                    echo "<div class='min-h-[100px] border rounded-2xl p-2 flex flex-col transition-all hover:shadow-md {$bg_class} {$border_class}'>";
                    echo "<div class='flex justify-between items-start'>";
                    echo "<span class='w-8 h-8 flex items-center justify-center rounded-full text-sm font-black {$text_class}'>{$dia}</span>";
                    echo "</div>";

                    // Mostrar los vecinos que deben pagar este día
                    if ($hay_cobro) {
                        echo "<div class='mt-2 flex flex-wrap gap-1'>";
                        foreach ($cobros_por_dia[$dia] as $n) {
                            echo "<span class='text-[9px] font-bold bg-white text-rose-600 px-2 py-1 rounded-md border border-rose-100 w-full truncate' title='Le toca pagar a: {$n}'>💵 {$n}</span>";
                        }
                        echo "</div>";
                    }

                    echo "</div>";
                }
                ?>
            </div>
        </div>
    </div>

</div>

</body>
</html>