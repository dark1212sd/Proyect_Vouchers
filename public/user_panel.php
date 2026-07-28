<?php
// public/user_panel.php
session_start();

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit();
}

require __DIR__ . '/config/db.php';

$userId = (string)$_SESSION['user_id'];

try {
    $usuario = $db->usuarios->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
} catch (Exception $e) {
    $usuario = null;
}

if (!$usuario && isset($_SESSION['username'])) {
    $usuario = $db->usuarios->findOne(['username' => $_SESSION['username']]);
}

$nombreVecino = $usuario['nombre'] ?? $_SESSION['username'] ?? 'Residente';
$cedulaVecino = $usuario['cedula'] ?? 'S/C';
$aptoVecino   = $usuario['apto'] ?? $usuario['departamento'] ?? 'Sin Apto Asignado';
$correoVecino = $usuario['email'] ?? $usuario['correo'] ?? '';
$fotoPerfil   = $usuario['foto_url'] ?? $usuario['avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($nombreVecino) . '&background=0D8B9E&color=fff';

$pagosCursor = $db->pagos->find(['user_id' => $userId], ['sort' => ['created_at' => -1]]);
$pagos = iterator_to_array($pagosCursor);

$totalReportado = 0; $totalAprobado = 0; $enRevision = 0;

foreach ($pagos as $p) {
    $estadoTmp = strtolower(trim($p['estado'] ?? $p['estatus'] ?? ''));
    $montoTmp = floatval((string)($p['monto'] ?? 0));

    if ($estadoTmp !== 'anulado' && $estadoTmp !== 'rechazado') { $totalReportado += $montoTmp; }
    if (in_array($estadoTmp, ['aprobado', 'validado', 'solvente', 'completado'])) { $totalAprobado += $montoTmp; }
    if (in_array($estadoTmp, ['en revisión', 'pendiente', 'verificando'])) { $enRevision++; }
}
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Residente - Alianza Victoriosa</title>
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
        .card-radio:checked + div {
            border-color: #06b6d4;
            background-color: rgba(6, 182, 212, 0.1);
            color: #06b6d4;
            box-shadow: 0 0 15px rgba(6, 182, 212, 0.2);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen selection:bg-cyan-500 selection:text-black">

<!-- BARRA DE NAVEGACIÓN SUPERIOR -->
<nav class="border-b border-slate-800/80 bg-slate-900/50 backdrop-blur-md sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg shadow-cyan-500/20 font-black text-white text-lg">
                AV
            </div>
            <div>
                <h1 class="font-extrabold text-sm sm:text-base bg-gradient-to-r from-white via-slate-200 to-slate-400 bg-clip-text text-transparent">Alianza Victoriosa</h1>
                <p class="text-[10px] font-semibold text-cyan-400 tracking-wider uppercase">Portal Comunal NoSQL</p>
            </div>
        </div>

        <div class="flex items-center gap-3 sm:gap-4">
            <div class="hidden md:flex flex-col text-right">
                <span class="text-xs font-bold text-white"><?php echo htmlspecialchars($nombreVecino); ?></span>
                <span class="text-[11px] text-slate-400">Apto: <strong class="text-cyan-400"><?php echo htmlspecialchars($aptoVecino); ?></strong></span>
            </div>
            <img src="<?php echo htmlspecialchars($fotoPerfil); ?>" alt="Avatar" class="w-9 h-9 rounded-full object-cover border-2 border-cyan-500/30 shadow-md">

            <!-- BOTÓN DE CONFIGURACIÓN -->
            <button onclick="abrirModalPerfil()" title="Configurar Perfil / Correo" class="p-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-cyan-400 border border-slate-700/50 hover:border-cyan-500/50 transition-all shadow-sm">
                <i data-lucide="settings" class="w-4 h-4"></i>
            </button>

            <a href="auth/logout.php" title="Cerrar Sesión" class="p-2 rounded-xl bg-slate-800/80 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 border border-slate-700/50 hover:border-rose-500/30 transition-all">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">

    <!-- ENCABEZADO Y TARJETAS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-gradient-to-br from-slate-900 to-slate-900/50 p-6 rounded-2xl border border-slate-800/80 relative overflow-hidden shadow-xl">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-cyan-500/10 rounded-full blur-xl"></div>
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Mi Solvencia</span>
                <span class="p-2 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20"><i data-lucide="shield-check" class="w-5 h-5"></i></span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-black text-white">Bs. <?php echo number_format($totalAprobado, 2, ',', '.'); ?></span>
            </div>
            <p class="text-xs text-slate-400 mt-2 flex items-center gap-1">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span> Total validado por Tesorería
            </p>
        </div>

        <div class="bg-gradient-to-br from-slate-900 to-slate-900/50 p-6 rounded-2xl border border-slate-800/80 relative overflow-hidden shadow-xl">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-amber-500/10 rounded-full blur-xl"></div>
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">En Revisión</span>
                <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20"><i data-lucide="clock" class="w-5 h-5"></i></span>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-black text-white"><?php echo $enRevision; ?> <span class="text-sm font-normal text-slate-400">reporte(s)</span></span>
            </div>
            <p class="text-xs text-slate-400 mt-2">Esperando auditoría administrativa</p>
        </div>

        <div class="bg-gradient-to-br from-cyan-950/40 via-slate-900 to-slate-900 p-6 rounded-2xl border border-cyan-500/30 flex flex-col justify-between shadow-xl shadow-cyan-950/20">
            <div>
                <h3 class="text-sm font-extrabold text-white mb-1">¿Realizaste un nuevo pago?</h3>
                <p class="text-xs text-slate-300">Sube tu comprobante digital para actualizar tu solvencia en tiempo real.</p>
            </div>
            <div class="mt-4">
                <button onclick="abrirModalVoucher()" class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-black font-extrabold text-xs shadow-lg shadow-cyan-500/25 transition-all flex items-center justify-center gap-2">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Reportar Nuevo Voucher
                </button>
            </div>
        </div>
    </div>

    <!-- TABLA DE HISTORIAL -->
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
        <div class="p-6 border-b border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-extrabold text-white flex items-center gap-2">
                    <i data-lucide="file-text" class="w-5 h-5 text-cyan-400"></i> Mi Historial de Declaraciones
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Registro auditado de todas tus transacciones en MongoDB.</p>
            </div>
            <span class="text-xs text-slate-400 bg-slate-800/80 py-1.5 px-3 rounded-lg border border-slate-700/50 flex items-center gap-1 self-start sm:self-auto font-mono">
                    <i data-lucide="mail" class="w-3.5 h-3.5 text-cyan-400"></i>
                    <?php echo !empty($correoVecino) ? htmlspecialchars($correoVecino) : 'Sin correo configurado'; ?>
                </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                <tr class="border-b border-slate-800/80 bg-slate-900/90 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                    <th class="py-3.5 px-4">Referencia / Banco</th>
                    <th class="py-3.5 px-4">Fecha Pago</th>
                    <th class="py-3.5 px-4">Monto Declarado</th>
                    <th class="py-3.5 px-4">Estatus de Auditoría</th>
                    <th class="py-3.5 px-4 text-right">Acciones</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-xs text-slate-300 font-medium">
                <?php if (empty($pagos)): ?>
                    <tr>
                        <td colspan="5" class="py-12 text-center text-slate-500">
                            <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 opacity-40"></i>
                            <p class="font-bold text-sm">Aún no has reportado ningún voucher</p>
                            <p class="text-xs mt-1">Haz clic en "Reportar Nuevo Voucher" para comenzar.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pagos as $pago): ?>
                        <?php
                        $idPagoStr = (string)$pago['_id'];
                        $estado = strtolower(trim($pago['estado'] ?? $pago['estatus'] ?? 'en revisión'));

                        $fecha = "N/A";
                        if (!empty($pago['fecha_pago'])) $fecha = $pago['fecha_pago'];
                        elseif (!empty($pago['fecha_declaracion']) && $pago['fecha_declaracion'] instanceof MongoDB\BSON\UTCDateTime) $fecha = $pago['fecha_declaracion']->toDateTime()->format('d/m/Y');

                        $ref = $pago['referencia_bancaria'] ?? $pago['referencia'] ?? 'S/R';
                        $montoVal = floatval((string)($pago['monto'] ?? 0));

                        $paso1 = "bg-cyan-400 shadow-lg shadow-cyan-500/50";
                        $paso2 = "bg-slate-700";
                        $paso3 = "bg-slate-700";
                        $textoEstado = "Enviado / Recibido";
                        $colorTexto = "text-cyan-400";

                        if (in_array($estado, ['en revisión', 'pendiente', 'verificando'])) {
                            $paso2 = "bg-amber-400 animate-pulse shadow-lg shadow-amber-500/50";
                            $textoEstado = "En Revisión por Tesorería";
                            $colorTexto = "text-amber-400";
                        } elseif (in_array($estado, ['aprobado', 'validado', 'solvente', 'completado'])) {
                            $paso2 = "bg-emerald-400";
                            $paso3 = "bg-emerald-400 shadow-lg shadow-emerald-500/50";
                            $textoEstado = "Voucher Validado y Solvente";
                            $colorTexto = "text-emerald-400";
                        } elseif ($estado === 'rechazado') {
                            $paso1 = "bg-rose-500"; $paso2 = "bg-rose-500"; $paso3 = "bg-rose-500";
                            $textoEstado = "Auditoría Rechazada";
                            $colorTexto = "text-rose-400";
                        } elseif ($estado === 'anulado') {
                            $paso1 = "bg-slate-600"; $paso2 = "bg-slate-600"; $paso3 = "bg-slate-600";
                            $textoEstado = "Anulado por el Residente";
                            $colorTexto = "text-slate-500 line-through";
                        }
                        ?>
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="py-4 px-4">
                                <span class="font-bold text-white block font-mono"><?php echo htmlspecialchars($ref); ?></span>
                                <span class="text-[10px] text-slate-500 uppercase"><?php echo htmlspecialchars(str_replace('_', ' ', $pago['metodo_pago'] ?? 'Digital')); ?></span>
                            </td>
                            <td class="py-4 px-4 text-slate-400"><?php echo htmlspecialchars($fecha); ?></td>
                            <td class="py-4 px-4 font-black text-white">Bs. <?php echo number_format($montoVal, 2, ',', '.'); ?></td>

                            <td class="py-4 px-4">
                                <div class="max-w-[180px]">
                                    <div class="flex items-center justify-between gap-1.5 mb-1.5">
                                        <span class="h-1.5 w-1/3 rounded-full <?php echo $paso1; ?>"></span>
                                        <span class="h-1.5 w-1/3 rounded-full <?php echo $paso2; ?>"></span>
                                        <span class="h-1.5 w-1/3 rounded-full <?php echo $paso3; ?>"></span>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider block <?php echo $colorTexto; ?>">
                                                <?php echo $textoEstado; ?>
                                            </span>
                                </div>
                            </td>

                            <td class="py-4 px-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <?php $urlSoporte = $pago['soporte_url'] ?? $pago['archivo'] ?? null; if ($urlSoporte): ?>
                                        <a href="<?php echo htmlspecialchars($urlSoporte); ?>" target="_blank" class="text-cyan-400 hover:text-cyan-300 font-bold text-[11px] flex items-center gap-1 underline">
                                            <span>Ver Foto</span>
                                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (in_array($estado, ['en revisión', 'pendiente', 'verificando'])): ?>
                                        <button type="button" onclick="anularVoucher('<?php echo $idPagoStr; ?>', '<?php echo htmlspecialchars($ref); ?>')" title="Anular reporte" class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white transition-all border border-rose-500/20">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    <?php endif; ?>
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

<!-- MODAL 1: WIZARD DE TARJETAS EN 3 PASOS PARA REPORTAR -->
<div id="modalVoucher" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full overflow-hidden shadow-2xl modal-enter">

        <div class="p-6 border-b border-slate-800/80 flex items-center justify-between bg-slate-900/90">
            <div>
                <h3 class="text-base font-extrabold text-white">Declarar Pago de Condominio</h3>
                <p id="indicadorPaso" class="text-xs text-cyan-400 font-bold uppercase tracking-wider mt-0.5">PASO 1 DE 3: MÉTODO Y MONTO</p>
            </div>
            <button onclick="cerrarModalVoucher()" class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- BARRA DE PROGRESO DEL WIZARD -->
        <div class="w-full bg-slate-950 h-1.5 flex">
            <div id="barraPaso" class="bg-gradient-to-r from-cyan-500 to-blue-500 h-full w-1/3 transition-all duration-300"></div>
        </div>

        <form id="formVoucher" class="p-6" enctype="multipart/form-data">
            <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($cedulaVecino); ?>">

            <!-- PASO 1: SELECCIÓN DE TARJETAS Y MONTO -->
            <div id="paso1" class="space-y-4">
                <label class="block text-xs font-bold text-slate-400 uppercase">Selecciona el Método de Pago:</label>

                <div class="grid grid-cols-2 gap-3">
                    <label class="cursor-pointer relative">
                        <input type="radio" name="metodo_pago" value="pago_movil" checked class="sr-only card-radio" onchange="verificarMetodoPago()">
                        <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center gap-3 font-bold text-xs text-slate-300 hover:border-slate-700 transition-all">
                            <i data-lucide="smartphone" class="w-5 h-5"></i> Pago Móvil
                        </div>
                    </label>
                    <label class="cursor-pointer relative">
                        <input type="radio" name="metodo_pago" value="transferencia" class="sr-only card-radio" onchange="verificarMetodoPago()">
                        <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center gap-3 font-bold text-xs text-slate-300 hover:border-slate-700 transition-all">
                            <i data-lucide="building-2" class="w-5 h-5"></i> Transferencia
                        </div>
                    </label>
                    <label class="cursor-pointer relative">
                        <input type="radio" name="metodo_pago" value="zelle" class="sr-only card-radio" onchange="verificarMetodoPago()">
                        <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center gap-3 font-bold text-xs text-slate-300 hover:border-slate-700 transition-all">
                            <i data-lucide="dollar-sign" class="w-5 h-5"></i> Zelle (USD)
                        </div>
                    </label>
                    <label class="cursor-pointer relative">
                        <input type="radio" name="metodo_pago" value="paypal" class="sr-only card-radio" onchange="verificarMetodoPago()">
                        <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center gap-3 font-bold text-xs text-slate-300 hover:border-slate-700 transition-all">
                            <i data-lucide="credit-card" class="w-5 h-5"></i> PayPal (USD)
                        </div>
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Monto Pagado (Bs. o $)</label>
                    <input type="number" step="0.01" name="monto" id="inputMonto" required placeholder="Ej: 150.00" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-cyan-500 font-extrabold font-mono">
                </div>

                <!-- CAMPO DINÁMICO PARA ZELLE / PAYPAL -->
                <div id="campoCorreoRemitente" class="hidden pt-2 border-t border-slate-800/80">
                    <label class="block text-xs font-bold text-cyan-400 uppercase mb-1 flex items-center gap-1.5">
                        <i data-lucide="mail" class="w-3.5 h-3.5"></i> Correo Remitente (De donde enviaste el pago)
                    </label>
                    <input type="email" name="correo_remitente" id="inputCorreoRemitente" placeholder="ejemplo@correo.com" class="w-full bg-slate-950 border border-cyan-500/50 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-cyan-400 font-semibold">
                    <p class="text-[10px] text-slate-400 mt-1">Obligatorio para que Tesorería verifique el ingreso por Zelle/PayPal.</p>
                </div>

                <div class="pt-4">
                    <button type="button" onclick="irAPaso(2)" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-black font-extrabold text-xs shadow-lg shadow-cyan-500/25 transition-all flex items-center justify-center gap-2">
                        Continuar: Referencia y Fecha <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <!-- PASO 2: REFERENCIA E IDENTIFICACIÓN -->
            <div id="paso2" class="space-y-4 hidden">
                <div>
                    <label id="labelReferencia" class="block text-xs font-bold text-slate-400 uppercase mb-1">N° de Referencia Bancaria</label>
                    <input type="text" name="referencia" id="inputReferencia" placeholder="Ej: 849201" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-cyan-500 font-mono font-bold">
                    <p id="subReferencia" class="text-[10px] text-slate-500 mt-1">Ingresa los últimos 6 dígitos de tu comprobante.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Fecha del Pago</label>
                    <input type="date" name="fecha_pago" value="<?php echo date('Y-m-d'); ?>" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-cyan-500 font-semibold">
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="button" onclick="irAPaso(1)" class="w-1/3 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition-all">
                        Atrás
                    </button>
                    <button type="button" onclick="irAPaso(3)" class="w-2/3 py-3.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-black font-extrabold text-xs shadow-lg shadow-cyan-500/25 transition-all flex items-center justify-center gap-2">
                        Continuar: Subir Foto <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <!-- PASO 3: ARCHIVO SOPORTE Y ENVÍO -->
            <div id="paso3" class="space-y-4 hidden">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Comprobante Digital</label>
                    <div class="border-2 border-dashed border-slate-800 hover:border-cyan-500/50 rounded-2xl p-6 text-center transition-colors bg-slate-950/50">
                        <i data-lucide="upload-cloud" class="w-8 h-8 text-cyan-400 mx-auto mb-2"></i>
                        <input type="file" name="comprobante" id="comprobante" accept="image/*,.pdf" class="w-full text-xs text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-extrabold file:bg-cyan-500/10 file:text-cyan-400 hover:file:bg-cyan-500/20 cursor-pointer">
                        <p class="text-[10px] text-slate-500 mt-2">Formatos permitidos: JPG, PNG, WEBP o PDF (Máx. 2MB).</p>
                    </div>
                </div>

                <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800/80 text-[11px] text-slate-400 flex items-start gap-2">
                    <i data-lucide="info" class="w-4 h-4 text-cyan-400 shrink-0 mt-0.5"></i>
                    <span>Al enviar, el sistema auditará tu pago en NoSQL y te notificará por correo.</span>
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="button" onclick="irAPaso(2)" class="w-1/3 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition-all">
                        Atrás
                    </button>
                    <button type="submit" id="btnSubmitVoucher" class="w-2/3 py-3.5 rounded-xl bg-gradient-to-r from-emerald-400 to-cyan-500 hover:from-emerald-300 hover:to-cyan-400 text-black font-black text-xs shadow-lg shadow-emerald-500/25 transition-all flex items-center justify-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Declarar Pago Ahora
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: CONFIGURACIÓN DE PERFIL -->
<div id="modalPerfil" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-md w-full overflow-hidden shadow-2xl modal-enter">
        <div class="p-6 border-b border-slate-800/80 flex items-center justify-between">
            <div>
                <h3 class="text-base font-extrabold text-white">Personalizar mi Perfil</h3>
                <p class="text-xs text-slate-400">Configura tu apartamento y correo para notificaciones.</p>
            </div>
            <button onclick="cerrarModalPerfil()" class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="formPerfil" action="actualizar_perfil.php" method="POST" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Nombre Completo</label>
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombreVecino); ?>" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-cyan-500 font-semibold">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Cédula / ID</label>
                    <input type="text" name="cedula" value="<?php echo htmlspecialchars($cedulaVecino); ?>" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-cyan-500 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Apartamento</label>
                    <input type="text" name="apto" value="<?php echo htmlspecialchars($aptoVecino); ?>" placeholder="Ej: 4-B" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-cyan-500 font-bold text-cyan-400">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Correo Electrónico (Para Recibos Digitales)</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($correoVecino); ?>" placeholder="ejemplo@gmail.com" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-cyan-500 font-semibold">
                <p class="text-[10px] text-slate-500 mt-1">Aquí te llegarán los comprobantes y avisos de anulación en HTML.</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">URL de Foto de Perfil (Opcional)</label>
                <input type="url" name="foto_url" value="<?php echo htmlspecialchars($usuario['foto_url'] ?? $usuario['avatar'] ?? ''); ?>" placeholder="https://..." class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-cyan-500 font-mono text-[11px]">
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-extrabold text-xs border border-slate-700 transition-all flex items-center justify-center gap-2">
                    <i data-lucide="save" class="w-4 h-4 text-cyan-400"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SCRIPTS DE CONTROL DEL WIZARD Y AJAX -->
<script>
    lucide.createIcons();

    const modalVoucher = document.getElementById('modalVoucher');
    const modalPerfil  = document.getElementById('modalPerfil');

    function abrirModalVoucher() {
        modalVoucher.classList.remove('hidden');
        modalVoucher.classList.add('flex');
        irAPaso(1);
    }
    function cerrarModalVoucher() {
        modalVoucher.classList.add('hidden');
        modalVoucher.classList.remove('flex');
    }

    function abrirModalPerfil() {
        modalPerfil.classList.remove('hidden');
        modalPerfil.classList.add('flex');
    }
    function cerrarModalPerfil() {
        modalPerfil.classList.add('hidden');
        modalPerfil.classList.remove('flex');
    }

    window.addEventListener('click', (e) => {
        if (e.target === modalVoucher) cerrarModalVoucher();
        if (e.target === modalPerfil)  cerrarModalPerfil();
    });

    // =========================================================
    // CONTROL DEL WIZARD EN 3 PASOS
    // =========================================================
    function irAPaso(paso) {
        if (paso === 2) {
            const monto = document.getElementById('inputMonto').value;
            if (!monto || parseFloat(monto) <= 0) {
                alert('⚠️ Por favor ingresa un monto válido mayor a cero antes de continuar.');
                return;
            }
            const metodo = document.querySelector('input[name="metodo_pago"]:checked').value;
            if ((metodo === 'zelle' || metodo === 'paypal') && !document.getElementById('inputCorreoRemitente').value) {
                alert('⚠️ Para Zelle o PayPal debes indicar el Correo Remitente.');
                return;
            }
        }

        if (paso === 3) {
            const ref = document.getElementById('inputReferencia').value;
            if (!ref) {
                alert('⚠️ Por favor ingresa el número o ID de referencia del pago.');
                return;
            }
        }

        document.getElementById('paso1').classList.add('hidden');
        document.getElementById('paso2').classList.add('hidden');
        document.getElementById('paso3').classList.add('hidden');

        document.getElementById(`paso${paso}`).classList.remove('hidden');

        const indicador = document.getElementById('indicadorPaso');
        const barra     = document.getElementById('barraPaso');

        if (paso === 1) {
            indicador.innerText = "PASO 1 DE 3: MÉTODO Y MONTO";
            barra.className = "bg-gradient-to-r from-cyan-500 to-blue-500 h-full w-1/3 transition-all duration-300";
        } else if (paso === 2) {
            indicador.innerText = "PASO 2 DE 3: REFERENCIA E IDENTIFICACIÓN";
            barra.className = "bg-gradient-to-r from-cyan-500 to-blue-500 h-full w-2/3 transition-all duration-300";
        } else {
            indicador.innerText = "PASO 3 DE 3: SUBIR COMPROBANTE";
            barra.className = "bg-gradient-to-r from-cyan-500 to-emerald-400 h-full w-full transition-all duration-300";
        }
    }

    function verificarMetodoPago() {
        const metodo = document.querySelector('input[name="metodo_pago"]:checked').value;
        const campoCorreo = document.getElementById('campoCorreoRemitente');
        const labelRef = document.getElementById('labelReferencia');
        const subRef   = document.getElementById('subReferencia');

        if (metodo === 'zelle' || metodo === 'paypal') {
            campoCorreo.classList.remove('hidden');
            labelRef.innerText = `ID o Referencia de ${metodo.toUpperCase()}`;
            subRef.innerText   = `Coloca el código o ID de transacción del recibo de ${metodo.toUpperCase()}.`;
        } else {
            campoCorreo.classList.add('hidden');
            labelRef.innerText = "N° de Referencia Bancaria";
            subRef.innerText   = "Ingresa los últimos 6 dígitos de tu comprobante.";
        }
    }

    document.getElementById('formVoucher').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitVoucher');
        const textoOriginal = btn.innerHTML;

        btn.innerHTML = `<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-black inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Procesando NoSQL...`;
        btn.disabled = true;

        const formData = new FormData(this);

        try {
            const response = await fetch('procesar_pago.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.status === 'success') {
                alert('✅ ' + data.message);
                window.location.reload();
            } else {
                alert('❌ Error: ' + (data.message || 'No se pudo procesar el pago.'));
                btn.innerHTML = textoOriginal;
                btn.disabled = false;
            }
        } catch (error) {
            alert('❌ Error de conexión con el servidor.');
            console.error(error);
            btn.innerHTML = textoOriginal;
            btn.disabled = false;
        }
    });

    async function anularVoucher(idPago, referencia) {
        if (!confirm(`¿Estás seguro de que deseas ANULAR el reporte N° ${referencia}?\n\nEsta acción cambiará el estado a Anulado y te notificaremos por correo.`)) {
            return;
        }

        try {
            const response = await fetch('anular_pago.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: idPago })
            });

            const data = await response.json();

            if (data.status === 'success') {
                alert('✅ ' + data.message);
                window.location.reload();
            } else {
                alert('❌ Error: ' + (data.message || 'No se pudo anular el reporte.'));
            }
        } catch (error) {
            alert('❌ Error de conexión al intentar anular.');
            console.error(error);
        }
    }
</script>
</body>
</html>