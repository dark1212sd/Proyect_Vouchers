<?php
// public/dashboard.php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Protección: Solo Admin o Superuser
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'superuser'])) {
    header("Location: /auth/login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

// 1. Filtro por Banco o Referencia (Si viene por GET)
$filtroBanco = $_GET['banco'] ?? '';
$condicionBusqueda = [];
if (!empty($filtroBanco)) {
    // Busca coincidencias parciales con el banco o referencia usando Regex
    $condicionBusqueda['$or'] = [
            ['banco' => new MongoDB\BSON\Regex($filtroBanco, 'i')],
            ['referencia' => new MongoDB\BSON\Regex($filtroBanco, 'i')],
            ['referencia_bancaria' => new MongoDB\BSON\Regex($filtroBanco, 'i')]
    ];
}

// 2. Obtener todos los pagos (aplicando filtro si existe)
$pagosCursor = $db->pagos->find($condicionBusqueda, ['sort' => ['created_at' => -1]]);
$pagos = iterator_to_array($pagosCursor);

// 3. Obtener total de usuarios registrados
$totalUsuarios = $db->usuarios->countDocuments(['role' => 'user']);

// 4. Calcular métricas globales y agrupar para el calendario
$totalValidado = 0;
$totalEnRevision = 0;
$pendientesCount = 0;

$mesActual = date('m');
$anioActual = date('Y');
$pagosPorDia = []; // Array para llenar el calendario interactivo

foreach ($pagos as $pago) {
    $monto  = floatval((string)($pago['monto'] ?? 0));
    $estado = strtolower(trim($pago['estado'] ?? $pago['estatus'] ?? 'en revisión'));

    if (in_array($estado, ['aprobado', 'validado', 'solvente'])) {
        $totalValidado += $monto;
    } elseif (in_array($estado, ['en revisión', 'pendiente'])) {
        $totalEnRevision += $monto;
        $pendientesCount++;
    }

    // Agrupar para el calendario (Solo pagos de este mes)
    $fechaPago = null;
    if (!empty($pago['fecha_pago'])) {
        $fechaPago = strtotime($pago['fecha_pago']);
    } elseif (!empty($pago['fecha_declaracion']) && $pago['fecha_declaracion'] instanceof MongoDB\BSON\UTCDateTime) {
        $fechaPago = $pago['fecha_declaracion']->toDateTime()->getTimestamp();
    } elseif (!empty($pago['created_at']) && $pago['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
        $fechaPago = $pago['created_at']->toDateTime()->getTimestamp();
    }

    if ($fechaPago && date('m', $fechaPago) === $mesActual && date('Y', $fechaPago) === $anioActual) {
        $dia = (int)date('d', $fechaPago);
        if (!isset($pagosPorDia[$dia])) {
            $pagosPorDia[$dia] = [];
        }
        $pagosPorDia[$dia][] = [
                'nombre' => $pago['nombre'] ?? 'Vecino',
                'estado' => $estado
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Alianza Victoriosa</title>

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
                        neon: { cyan: '#00f2fe', emerald: '#10b981', blue: '#4facfe', amber: '#f59e0b' }
                    }
                }
            }
        }
    </script>

    <style>
        .glow-cyan { box-shadow: 0 0 25px -5px rgba(0, 242, 254, 0.3); }
        .glow-emerald { box-shadow: 0 0 25px -5px rgba(16, 185, 129, 0.3); }
        .glow-amber { box-shadow: 0 0 25px -5px rgba(245, 158, 11, 0.25); }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .animate-modal { animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards; }

        /* Ocultar scrollbar para la tabla y el calendario */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex flex-col selection:bg-cyan-500 selection:text-slate-950 overflow-x-hidden">

<!-- HEADER ADMIN -->
<header class="bg-slate-900/90 border-b border-slate-800/80 backdrop-blur-md sticky top-0 z-40">
    <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div class="relative">
                <img src="/assets/img/logo_alianza_victoriosa_anime.svg" alt="Admin" class="w-11 h-11 glow-cyan rounded-xl">
                <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-rose-500 border-2 border-slate-900 rounded-full flex items-center justify-center animate-pulse" title="Admin Mode"></div>
            </div>
            <div>
                <span class="text-lg font-black tracking-tight text-white block leading-none">VOUCHER<span class="text-cyan-400">ADMIN</span></span>
                <span class="text-[10px] font-bold text-amber-400 tracking-widest uppercase block mt-0.5">Comité de Finanzas</span>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="hidden sm:block text-right mr-2">
                <p class="text-xs font-bold text-white"><?php echo htmlspecialchars($_SESSION['nombre'] ?? $_SESSION['username']); ?></p>
                <p class="text-[10px] text-slate-400 font-mono uppercase"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Tesorero'); ?></p>
            </div>
            <a href="/auth/logout.php" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-800 hover:bg-rose-500/20 text-slate-300 hover:text-rose-400 border border-slate-700 hover:border-rose-500/30 text-xs font-bold transition-all">
                <i data-lucide="log-out" class="w-4 h-4"></i>
                <span>Salir</span>
            </a>
        </div>
    </div>
</header>

<!-- CONTENIDO PRINCIPAL -->
<main class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-grow w-full">

    <!-- ==========================================
         MÉTRICAS GLOBALES (KPIs)
         ========================================== -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Solvente -->
        <div class="bg-slate-900/80 border border-emerald-500/30 rounded-3xl p-6 backdrop-blur-xl glow-emerald relative overflow-hidden">
            <div class="absolute -right-4 -top-4 opacity-10"><i data-lucide="trending-up" class="w-32 h-32 text-emerald-500"></i></div>
            <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider block mb-1">Recaudación Validada</span>
            <span class="text-3xl font-black text-white block mb-2">Bs. <?php echo number_format($totalValidado, 2, ',', '.'); ?></span>
            <span class="text-[10px] text-slate-400 bg-slate-950 px-2 py-1 rounded-md border border-slate-800">Total en bóveda del edificio</span>
        </div>

        <!-- Por Revisar -->
        <div class="bg-slate-900/80 border border-amber-500/30 rounded-3xl p-6 backdrop-blur-xl glow-amber relative overflow-hidden">
            <div class="absolute -right-4 -top-4 opacity-10"><i data-lucide="alert-circle" class="w-32 h-32 text-amber-500"></i></div>
            <span class="text-xs font-bold text-amber-400 uppercase tracking-wider block mb-1">Por Auditar / En Revisión</span>
            <span class="text-3xl font-black text-white block mb-2">Bs. <?php echo number_format($totalEnRevision, 2, ',', '.'); ?></span>
            <span class="text-[10px] text-amber-400 bg-amber-500/10 border border-amber-500/20 px-2 py-1 rounded-md">
                    <i data-lucide="bell" class="w-3 h-3 inline mr-1 -mt-0.5"></i><?php echo $pendientesCount; ?> Vouchers en cola
                </span>
        </div>

        <!-- Total Usuarios -->
        <div class="bg-slate-900/80 border border-cyan-500/30 rounded-3xl p-6 backdrop-blur-xl glow-cyan relative overflow-hidden">
            <div class="absolute -right-4 -top-4 opacity-10"><i data-lucide="users" class="w-32 h-32 text-cyan-500"></i></div>
            <span class="text-xs font-bold text-cyan-400 uppercase tracking-wider block mb-1">Residentes Registrados</span>
            <span class="text-3xl font-black text-white block mb-2"><?php echo $totalUsuarios; ?> <span class="text-sm text-slate-500">Aptos.</span></span>
            <span class="text-[10px] text-slate-400 bg-slate-950 px-2 py-1 rounded-md border border-slate-800">Comunidad Activa</span>
        </div>
    </div>

    <!-- ==========================================
         MÓDULOS DE ADMINISTRACIÓN (ACCESOS RÁPIDOS)
         ========================================== -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

        <!-- Gestión de Vecinos -->
        <a href="/admin_vecinos.php" class="bg-slate-900 border border-slate-800 rounded-2xl p-4 flex items-center gap-4 hover:bg-slate-800/80 hover:border-cyan-500/50 transition-all group shadow-lg">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 flex items-center justify-center shrink-0 group-hover:scale-110 group-hover:bg-cyan-500 group-hover:text-slate-950 transition-all">
                <i data-lucide="users" class="w-6 h-6 stroke-[2]"></i>
            </div>
            <div>
                <h4 class="text-sm font-bold text-white tracking-wide">Gestión de Vecinos</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">Crear, editar o suspender cuentas</p>
            </div>
        </a>

        <!-- Reportes Financieros -->
        <a href="/admin_reportes.php" class="bg-slate-900 border border-slate-800 rounded-2xl p-4 flex items-center gap-4 hover:bg-slate-800/80 hover:border-emerald-500/50 transition-all group shadow-lg">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center shrink-0 group-hover:scale-110 group-hover:bg-emerald-500 group-hover:text-slate-950 transition-all">
                <i data-lucide="bar-chart-3" class="w-6 h-6 stroke-[2]"></i>
            </div>
            <div>
                <h4 class="text-sm font-bold text-white tracking-wide">Reportes y Cierres</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">Generar PDF y métricas mensuales</p>
            </div>
        </a>

        <!-- Ajustes del Portal -->
        <a href="/admin_ajustes.php" class="bg-slate-900 border border-slate-800 rounded-2xl p-4 flex items-center gap-4 hover:bg-slate-800/80 hover:border-blue-500/50 transition-all group shadow-lg">
            <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-400 border border-blue-500/20 flex items-center justify-center shrink-0 group-hover:scale-110 group-hover:bg-blue-500 group-hover:text-slate-950 transition-all">
                <i data-lucide="settings" class="w-6 h-6 stroke-[2]"></i>
            </div>
            <div>
                <h4 class="text-sm font-bold text-white tracking-wide">Ajustes de Portal</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">Configurar cuotas, bancos y roles</p>
            </div>
        </a>

    </div>

    <!-- ==========================================
         BARRA DE HERRAMIENTAS (Filtros y Calendario)
         ========================================== -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 flex flex-col sm:flex-row justify-between items-center gap-4 shadow-lg">

        <!-- Buscador / Filtro por Banco o Referencia -->
        <form action="" method="GET" class="w-full sm:w-auto flex gap-2">
            <div class="relative w-full sm:w-72">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500"><i data-lucide="search" class="w-4 h-4"></i></div>
                <input type="text" name="banco" value="<?php echo htmlspecialchars($filtroBanco); ?>" placeholder="Buscar Banco o N° Referencia..." class="w-full pl-9 pr-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-xs focus:outline-none focus:border-cyan-400 transition-all">
            </div>
            <button type="submit" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-2">
                <span>Filtrar</span>
            </button>
            <?php if(!empty($filtroBanco)): ?>
                <a href="/dashboard.php" class="px-3 py-2.5 bg-rose-500/10 text-rose-400 rounded-xl border border-rose-500/20 hover:bg-rose-500 hover:text-white text-xs font-bold transition-all flex items-center" title="Limpiar Filtro">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            <?php endif; ?>
        </form>

        <!-- Botón para abrir el Modal del Calendario -->
        <button onclick="openModal('modalCalendario')" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-emerald-400 text-slate-950 font-extrabold text-xs glow-cyan hover:scale-[1.02] transition-all flex items-center justify-center gap-2 shadow-lg">
            <i data-lucide="calendar-days" class="w-4 h-4 stroke-[2.5]"></i>
            <span>Ver Calendario de Cobros</span>
        </button>
    </div>

    <!-- ==========================================
         TABLA CENTRAL DE AUDITORÍA
         ========================================== -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 backdrop-blur-xl shadow-xl">
        <div class="mb-6 pb-4 border-b border-slate-800 flex justify-between items-end">
            <div>
                <h2 class="text-lg font-black text-white flex items-center gap-2"><i data-lucide="clipboard-list" class="w-5 h-5 text-cyan-400"></i>Centro de Auditoría Comunal</h2>
                <p class="text-xs text-slate-400">Supervisa, aprueba o rechaza los reportes de pago de los residentes.</p>
            </div>
        </div>

        <div class="overflow-x-auto no-scrollbar pb-16">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead>
                <tr class="border-b border-slate-800 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    <th class="py-3 px-4">Residente</th>
                    <th class="py-3 px-4">Ref. / Banco</th>
                    <th class="py-3 px-4">Fecha Pago</th>
                    <th class="py-3 px-4">Monto (Bs.)</th>
                    <th class="py-3 px-4">Estado</th>
                    <th class="py-3 px-4 text-center">Soporte</th>
                    <th class="py-3 px-4 text-right">Acciones</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-xs text-slate-300 font-medium">
                <?php if (count($pagos) > 0): ?>
                    <?php foreach ($pagos as $pago):
                        $idStr  = (string)$pago['_id'];
                        $estado = strtolower(trim($pago['estado'] ?? $pago['estatus'] ?? 'en revisión'));

                        $fecha  = "N/A";
                        if (!empty($pago['fecha_pago'])) $fecha = $pago['fecha_pago'];
                        elseif (!empty($pago['fecha_declaracion']) && $pago['fecha_declaracion'] instanceof MongoDB\BSON\UTCDateTime) $fecha = $pago['fecha_declaracion']->toDateTime()->format('d/m/Y');
                        elseif (!empty($pago['created_at']) && $pago['created_at'] instanceof MongoDB\BSON\UTCDateTime) $fecha = $pago['created_at']->toDateTime()->format('d/m/Y');

                        $monto  = floatval((string)($pago['monto'] ?? 0));
                        $banco  = $pago['banco'] ?? $pago['plataforma'] ?? $pago['metodo_pago'] ?? 'S/R';
                        $referencia = $pago['referencia'] ?? $pago['referencia_bancaria'] ?? 'N/A';

                        $claseBadge = 'bg-amber-500/10 text-amber-400 border-amber-500/20 animate-pulse';
                        if (in_array($estado, ['aprobado', 'validado', 'solvente'])) {
                            $claseBadge = 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
                        } elseif ($estado === 'rechazado') {
                            $claseBadge = 'bg-rose-500/10 text-rose-400 border-rose-500/20';
                        }
                        ?>
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="py-3.5 px-4">
                                <span class="font-bold text-white block"><?php echo htmlspecialchars($pago['nombre'] ?? 'Vecino'); ?></span>
                                <span class="text-[9px] text-slate-500 font-mono">C.I: <?php echo htmlspecialchars($pago['cedula'] ?? $pago['cedula_vecino'] ?? 'N/A'); ?></span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-bold text-white block font-mono tracking-widest text-cyan-300"><?php echo htmlspecialchars($referencia); ?></span>
                                <span class="text-[10px] text-slate-500 uppercase truncate max-w-[180px] block" title="<?php echo htmlspecialchars($banco); ?>"><?php echo htmlspecialchars($banco); ?></span>
                            </td>
                            <td class="py-3.5 px-4 text-slate-400"><?php echo htmlspecialchars($fecha); ?></td>
                            <td class="py-3.5 px-4 font-black text-white">Bs. <?php echo number_format($monto, 2, ',', '.'); ?></td>
                            <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border inline-block <?php echo $claseBadge; ?>">
                                        <?php echo htmlspecialchars($pago['estado'] ?? $pago['estatus'] ?? 'En Revisión'); ?>
                                    </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <?php
                                $urlSoporte = $pago['soporte_url'] ?? $pago['archivo'] ?? null;
                                if ($urlSoporte && !str_starts_with($urlSoporte, '/uploads/') && !str_starts_with($urlSoporte, 'http')) {
                                    $urlSoporte = '/uploads/vouchers/' . $urlSoporte;
                                }
                                if ($urlSoporte):
                                    ?>
                                    <a href="<?php echo htmlspecialchars($urlSoporte); ?>" target="_blank" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-950 border border-slate-700 hover:border-cyan-400 hover:text-cyan-400 transition-all shadow" title="Ver Comprobante">
                                        <i data-lucide="image" class="w-4 h-4"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-600 text-[10px]">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <?php if ($estado === 'en revisión' || $estado === 'pendiente'): ?>
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Botón Aprobar -->
                                        <button onclick="confirmAction('aprobar', '<?php echo $idStr; ?>')" class="px-3 py-1.5 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500 hover:text-slate-950 font-bold transition-all flex items-center gap-1.5 shadow">
                                            <i data-lucide="check" class="w-3.5 h-3.5 stroke-[3]"></i> Aprobar
                                        </button>
                                        <!-- Botón Rechazar -->
                                        <button onclick="confirmAction('rechazar', '<?php echo $idStr; ?>')" class="px-3 py-1.5 rounded-lg bg-rose-500/10 text-rose-400 border border-rose-500/20 hover:bg-rose-500 hover:text-slate-950 font-bold transition-all flex items-center gap-1.5 shadow">
                                            <i data-lucide="x" class="w-3.5 h-3.5 stroke-[3]"></i> Rechazar
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-500 italic">Auditado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="py-12 text-center">
                            <i data-lucide="shield-check" class="w-12 h-12 text-slate-700 mx-auto mb-3"></i>
                            <p class="text-slate-400 font-bold">No hay pagos registrados bajo este criterio.</p>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- ==========================================
     MODAL: CALENDARIO DE COBROS (VISUAL PHP)
     ========================================== -->
<div id="modalCalendario" class="fixed inset-0 z-50 bg-slate-950/90 backdrop-blur-md flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-6xl w-full p-6 sm:p-8 relative overflow-hidden shadow-2xl animate-modal flex flex-col h-[85vh]">
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 via-teal-400 to-emerald-400"></div>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 shrink-0">
            <div>
                <h3 class="text-2xl font-black text-white flex items-center gap-2">
                    <i data-lucide="calendar-check" class="w-6 h-6 text-cyan-400"></i>
                    Calendario de Cobros
                </h3>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">
                    MES ACTUAL: <span class="text-cyan-400"><?php echo strtoupper(date('F Y')); ?></span>
                </p>
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3 text-[10px] font-bold uppercase tracking-wider text-slate-400 bg-slate-950 px-4 py-2 rounded-xl border border-slate-800">
                    <span class="flex items-center gap-1.5"><div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div> Validado</span>
                    <span class="flex items-center gap-1.5"><div class="w-2.5 h-2.5 rounded-full bg-amber-500"></div> Revisión</span>
                </div>
                <button type="button" onclick="closeModal('modalCalendario')" class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-white hover:bg-rose-500 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>

        <div class="flex-grow overflow-y-auto pr-2 no-scrollbar">
            <div class="grid grid-cols-7 gap-2 mb-2 text-center sticky top-0 bg-slate-900 z-10 py-2">
                <?php
                $diasSemana = ['DOM', 'LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB'];
                foreach($diasSemana as $d) echo "<div class='text-[10px] font-black text-slate-500 uppercase tracking-widest'>$d</div>";
                ?>
            </div>

            <div class="grid grid-cols-7 gap-2 pb-4">
                <?php
                $primerDiaMes = mktime(0,0,0, date('m'), 1, date('Y'));
                $diasEnMes = date('t', $primerDiaMes);
                $diaSemanaInicio = date('w', $primerDiaMes); // 0 (Dom) a 6 (Sab)

                for ($i = 0; $i < $diaSemanaInicio; $i++) {
                    echo "<div class='min-h-[100px] bg-transparent border border-dashed border-slate-800/50 rounded-2xl'></div>";
                }

                $diaActual = (int)date('d');
                for ($dia = 1; $dia <= $diasEnMes; $dia++) {
                    $esHoy = ($dia === $diaActual) ? 'border-cyan-500/50 bg-cyan-950/20 shadow-[0_0_15px_rgba(0,242,254,0.1)]' : 'border-slate-800 bg-slate-950';
                    $textoDia = ($dia === $diaActual) ? 'text-cyan-400 font-black' : 'text-slate-400 font-bold';

                    echo "<div class='min-h-[120px] p-2 rounded-2xl border flex flex-col transition-colors hover:border-slate-600 $esHoy'>";
                    echo "<span class='text-xs block mb-2 $textoDia'>$dia</span>";

                    echo "<div class='flex flex-col gap-1.5 overflow-y-auto no-scrollbar flex-grow'>";
                    if (isset($pagosPorDia[$dia])) {
                        foreach ($pagosPorDia[$dia] as $p) {
                            $colorPildora = (in_array($p['estado'], ['aprobado', 'validado', 'solvente']))
                                    ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                    : 'bg-amber-500/10 text-amber-400 border border-amber-500/20';

                            $primerNombre = explode(' ', trim($p['nombre']))[0];

                            echo "<div class='text-[9px] font-bold px-1.5 py-1 rounded-md flex items-center gap-1.5 truncate $colorPildora' title='{$p['nombre']} - {$p['estado']}'>";
                            echo "<i data-lucide='banknote' class='w-2.5 h-2.5 shrink-0'></i> <span class='truncate'>$primerNombre</span>";
                            echo "</div>";
                        }
                    }
                    echo "</div></div>";
                }
                ?>
            </div>
        </div>

    </div>
</div>

<!-- SCRIPTS -->
<script>
    lucide.createIcons();

    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    window.addEventListener('click', function(e) {
        if (e.target.id === 'modalCalendario') closeModal('modalCalendario');
    });

    // Mock para las acciones (Esto lo conectaremos a la API de auditoría después)
    async function confirmAction(action, id) {
        const accionTexto = action === 'aprobar' ? 'APROBAR' : 'RECHAZAR';

        if(confirm(`¿Estás seguro de que deseas ${accionTexto} este comprobante NoSQL?`)) {
            try {
                // Hacemos la petición a tu archivo existente
                const response = await fetch('aprobar_pago.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    // Enviamos los datos como un objeto JSON
                    body: JSON.stringify({
                        id: id,
                        accion: action
                    })
                });

                const data = await response.json();

                if (data.status === 'success') {
                    // Recargar la página automáticamente para ver reflejado el cambio
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Ocurrió un error al intentar comunicarse con el servidor.');
            }
        }
    }
</script>
</body>
</html>