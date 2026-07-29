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
    $objId = new MongoDB\BSON\ObjectId($userId);
} catch (Exception $e) {
    $objId = $userId;
}

try {
    $usuario = $db->usuarios->findOne(['_id' => $objId]);
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

// ==========================================
// NUEVO: OBTENER CUENTAS OFICIALES DEL ADMIN
// ==========================================
try {
    $configCuentas = $db->configuracion->findOne(['tipo' => 'cuentas_recaudacion']);
} catch (Exception $e) {
    $configCuentas = null;
}

$zelleEmail   = $configCuentas['zelle_email'] ?? 'No configurado';
$paypalEmail  = $configCuentas['paypal_email'] ?? 'No configurado';
$pmBanco      = $configCuentas['pm_banco'] ?? 'No configurado';
$pmTelefono   = $configCuentas['pm_telefono'] ?? 'No configurado';
$pmCedula     = $configCuentas['pm_cedula'] ?? 'No configurado';
$qrUrl        = $configCuentas['qr_url'] ?? null;
$transBanco   = $configCuentas['transfer_banco'] ?? 'No configurado';
$transCuenta  = $configCuentas['transfer_cuenta'] ?? 'No configurado';
$transNombre  = $configCuentas['transfer_nombre'] ?? 'No configurado';
$transRif     = $configCuentas['transfer_rif'] ?? 'No configurado';

// Obtener el historial de pagos
$condicionPagos = [
        '$or' => [
                ['user_id' => $userId],
                ['user_id' => $objId]
        ]
];

$pagosCursor = $db->pagos->find($condicionPagos, ['sort' => ['fecha_declaracion' => -1, 'created_at' => -1]]);
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
        .glow-cyan { box-shadow: 0 0 20px -5px rgba(0, 242, 254, 0.4); }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #0f172a; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen selection:bg-cyan-500 selection:text-black">

<!-- BARRA DE NAVEGACIÓN SUPERIOR -->
<nav class="border-b border-slate-800/80 bg-slate-900/50 backdrop-blur-md sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <img src="assets/img/logo_alianza_victoriosa_anime.svg" alt="Logo" class="w-10 h-10 glow-cyan rounded-xl bg-slate-950 p-1 border border-slate-800">
            <div>
                <span class="font-black text-lg tracking-tight text-white block leading-none">VOUCHER<span class="text-cyan-400">CHECK</span></span>
                <span class="text-[9px] font-bold text-emerald-400 tracking-widest uppercase block mt-0.5">Portal Comunal NoSQL</span>
            </div>
        </div>

        <div class="flex items-center gap-3 sm:gap-4">
            <div class="hidden md:flex flex-col text-right">
                <span class="text-xs font-bold text-white"><?php echo htmlspecialchars($nombreVecino); ?></span>
                <span class="text-[11px] text-slate-400">Apto: <strong class="text-cyan-400"><?php echo htmlspecialchars($aptoVecino); ?></strong></span>
            </div>
            <img src="<?php echo htmlspecialchars($fotoPerfil); ?>" alt="Avatar" class="w-9 h-9 rounded-full object-cover border-2 border-cyan-500/30 shadow-md">

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

    <!-- SECCIÓN 1: ESTADÍSTICAS Y ACCIÓN PRINCIPAL -->
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
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span> Total validado
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
            <p class="text-xs text-slate-400 mt-2">Esperando auditoría</p>
        </div>

        <div class="bg-gradient-to-br from-cyan-950/40 via-slate-900 to-slate-900 p-6 rounded-2xl border border-cyan-500/30 flex flex-col justify-between shadow-xl shadow-cyan-950/20">
            <div>
                <h3 class="text-sm font-extrabold text-white mb-1">¿Realizaste un nuevo pago?</h3>
                <p class="text-xs text-slate-300">Sube tu comprobante digital para actualizar tu solvencia.</p>
            </div>
            <div class="mt-4">
                <button onclick="abrirModalVoucher()" class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-black font-extrabold text-xs shadow-lg shadow-cyan-500/25 transition-all flex items-center justify-center gap-2">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Reportar Nuevo Pago
                </button>
            </div>
        </div>
    </div>

    <!-- SECCIÓN 2: TABLA DE HISTORIAL -->
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
                    <th class="py-3.5 px-4">N° Rastreo Oficial</th>
                    <th class="py-3.5 px-4">Referencia / Banco</th>
                    <th class="py-3.5 px-4">Fecha / Hora</th>
                    <th class="py-3.5 px-4">Monto Declarado</th>
                    <th class="py-3.5 px-4">Estatus de Auditoría</th>
                    <th class="py-3.5 px-4 text-right">Acciones</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-xs text-slate-300 font-medium">
                <?php if (empty($pagos)): ?>
                    <tr>
                        <td colspan="6" class="py-12 text-center text-slate-500">
                            <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 opacity-40"></i>
                            <p class="font-bold text-sm">Aún no has reportado ningún voucher</p>
                            <p class="text-xs mt-1">Haz clic en "Reportar Nuevo Pago" para comenzar.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pagos as $pago): ?>
                        <?php
                        $idPagoStr = (string)$pago['_id'];
                        $rastreo = $pago['numero_rastreo'] ?? 'S/N';
                        $estado = strtolower(trim($pago['estado'] ?? $pago['estatus'] ?? 'en revisión'));

                        // Capturar datos del rechazo si existen
                        $motivo_rechazo = $pago['motivo_rechazo'] ?? 'El comprobante no cumple con las normativas o no se reflejó en nuestra cuenta.';
                        $imagen_rechazo = $pago['imagen_rechazo'] ?? '';

                        $fecha = "N/A";
                        if (!empty($pago['fecha_pago'])) {
                            $fechaObj = date_create($pago['fecha_pago']);
                            $fecha = $fechaObj ? date_format($fechaObj, 'd/m/Y h:i A') : $pago['fecha_pago'];
                        } elseif (!empty($pago['fecha_declaracion']) && $pago['fecha_declaracion'] instanceof MongoDB\BSON\UTCDateTime) {
                            $fecha = $pago['fecha_declaracion']->toDateTime()->format('d/m/Y h:i A');
                        }

                        $ref = $pago['referencia_bancaria'] ?? $pago['referencia'] ?? 'S/R';
                        $montoVal = floatval((string)($pago['monto'] ?? 0));

                        $metodo = $pago['metodo_pago'] ?? 'Digital';
                        $monedaStr = (in_array(strtolower($metodo), ['zelle', 'paypal'])) ? '$' : 'Bs.';

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
                            $textoEstado = "Voucher Validado";
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
                                <span class="font-mono font-black text-cyan-400 text-xs bg-cyan-500/10 px-2 py-1 rounded-md border border-cyan-500/20 block w-max">
                                    <?php echo htmlspecialchars($rastreo); ?>
                                </span>
                            </td>

                            <td class="py-4 px-4">
                                <span class="font-bold text-white block font-mono"><?php echo htmlspecialchars($ref); ?></span>
                                <span class="text-[10px] text-slate-500 uppercase">
                                    <?php echo htmlspecialchars(str_replace('_', ' ', $metodo)); ?>
                                    <?php if(!empty($pago['banco_origen'])) echo " - " . htmlspecialchars($pago['banco_origen']); ?>
                                </span>
                            </td>
                            <td class="py-4 px-4 text-slate-400"><?php echo htmlspecialchars($fecha); ?></td>
                            <td class="py-4 px-4 font-black text-white"><?php echo $monedaStr; ?> <?php echo number_format($montoVal, 2, ',', '.'); ?></td>

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

                                    <?php if ($estado === 'rechazado'): ?>
                                        <!-- BOTÓN DE VER MOTIVO -->
                                        <button type="button" onclick="abrirMotivoRechazo('<?php echo htmlspecialchars($motivo_rechazo, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($imagen_rechazo, ENT_QUOTES); ?>')" class="py-1 px-3 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white transition-all border border-rose-500/20 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1">
                                            <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i> Ver Motivo
                                        </button>
                                    <?php elseif (in_array($estado, ['en revisión', 'pendiente', 'verificando'])): ?>
                                        <!-- BOTÓN ANULAR -->
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

<!-- BOTÓN FLOTANTE (FAB) PARA VER CUENTAS OFICIALES -->
<button onclick="abrirModalCuentas()" class="fixed bottom-6 right-6 z-40 bg-slate-900 border border-slate-700 hover:border-cyan-500 text-cyan-400 rounded-full p-4 shadow-2xl hover:scale-105 transition-all flex items-center justify-center group">
    <i data-lucide="landmark" class="w-6 h-6"></i>
    <span class="max-w-0 overflow-hidden group-hover:max-w-xs transition-all duration-300 ease-in-out whitespace-nowrap font-bold text-sm group-hover:ml-2">Cuentas Oficiales</span>
</button>

<!-- MODAL 3: DATOS DE RECAUDACIÓN DINÁMICOS -->
<div id="modalCuentas" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-slate-900 border border-cyan-500/30 rounded-3xl max-w-md w-full flex flex-col max-h-[90vh] shadow-2xl modal-enter relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-cyan-400 to-emerald-400 z-10"></div>

        <div class="p-5 border-b border-slate-800/80 flex items-center justify-between shrink-0 bg-slate-900 z-10">
            <div>
                <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                    <i data-lucide="landmark" class="w-5 h-5 text-cyan-400"></i> Cuentas del Condominio
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">Selecciona un método de pago.</p>
            </div>
            <button onclick="cerrarModalCuentas()" class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="p-4 bg-slate-950/50 border-b border-slate-800 shrink-0">
            <div class="grid grid-cols-2 gap-2">
                <button id="tab-pago_movil" onclick="mostrarDatosPago('pago_movil')" class="py-2 px-3 rounded-lg bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 font-bold text-[11px] flex items-center justify-center gap-1.5 transition-all">
                    <i data-lucide="smartphone" class="w-3.5 h-3.5"></i> Pago Móvil
                </button>
                <button id="tab-transferencia" onclick="mostrarDatosPago('transferencia')" class="py-2 px-3 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 hover:text-white font-bold text-[11px] flex items-center justify-center gap-1.5 transition-all">
                    <i data-lucide="building-2" class="w-3.5 h-3.5"></i> Transferencia
                </button>
                <button id="tab-zelle" onclick="mostrarDatosPago('zelle')" class="py-2 px-3 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 hover:text-white font-bold text-[11px] flex items-center justify-center gap-1.5 transition-all">
                    <i data-lucide="dollar-sign" class="w-3.5 h-3.5"></i> Zelle
                </button>
                <button id="tab-paypal" onclick="mostrarDatosPago('paypal')" class="py-2 px-3 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 hover:text-white font-bold text-[11px] flex items-center justify-center gap-1.5 transition-all">
                    <i data-lucide="credit-card" class="w-3.5 h-3.5"></i> PayPal
                </button>
            </div>
        </div>

        <div class="p-5 overflow-y-auto custom-scrollbar flex-1 relative">

            <!-- TAB: PAGO MÓVIL (Con QR Dinámico) -->
            <div id="content-pago_movil" class="space-y-5 animate-[fadeIn_0.3s_ease-in-out]">

                <div class="flex justify-center">
                    <div class="w-40 h-40 bg-white p-2 rounded-2xl flex flex-col items-center justify-center border-[3px] border-cyan-500 shadow-[0_0_20px_rgba(6,182,212,0.3)] relative overflow-hidden">
                        <?php if ($qrUrl): ?>
                            <!-- Mostrar QR desde la base de datos -->
                            <img src="<?php echo htmlspecialchars($qrUrl); ?>" alt="Código QR" class="w-full h-full object-contain">
                        <?php else: ?>
                            <!-- Fallback si el admin no ha subido QR -->
                            <i data-lucide="qr-code" class="w-full h-full text-slate-300"></i>
                            <span class="absolute text-[8px] font-bold text-slate-400 text-center px-2">QR NO CONFIGURADO</span>
                        <?php endif; ?>
                        <span class="absolute -bottom-3 bg-cyan-500 text-black text-[9px] font-black px-3 py-1 rounded-full uppercase tracking-widest border-2 border-[#0f172a]">ESCANEAR</span>
                    </div>
                </div>

                <!-- Datos leídos de la DB -->
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-3">
                    <div class="flex justify-between items-center border-b border-slate-800/80 pb-2">
                        <span class="text-[11px] font-bold text-slate-500 uppercase">Banco</span>
                        <span class="text-sm font-bold text-white flex items-center gap-1.5"><i data-lucide="building" class="w-4 h-4 text-cyan-400"></i> <?php echo htmlspecialchars($pmBanco); ?></span>
                    </div>
                    <div class="flex justify-between items-center border-b border-slate-800/80 pb-2">
                        <span class="text-[11px] font-bold text-slate-500 uppercase">Teléfono</span>
                        <span class="text-sm font-mono font-bold text-cyan-400"><?php echo htmlspecialchars($pmTelefono); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[11px] font-bold text-slate-500 uppercase">RIF / Cédula</span>
                        <span class="text-sm font-mono font-bold text-white"><?php echo htmlspecialchars($pmCedula); ?></span>
                    </div>
                </div>
            </div>

            <!-- TAB: TRANSFERENCIA BANCARIA -->
            <div id="content-transferencia" class="space-y-4 hidden animate-[fadeIn_0.3s_ease-in-out]">
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-3">
                    <div class="flex justify-between items-center border-b border-slate-800/80 pb-2">
                        <span class="text-[11px] font-bold text-slate-500 uppercase">Banco</span>
                        <span class="text-sm font-bold text-white"><?php echo htmlspecialchars($transBanco); ?></span>
                    </div>
                    <div class="flex flex-col border-b border-slate-800/80 pb-2">
                        <span class="text-[11px] font-bold text-slate-500 uppercase mb-1">Número de Cuenta</span>
                        <span class="text-sm font-mono font-bold text-cyan-400 tracking-wider"><?php echo htmlspecialchars($transCuenta); ?></span>
                    </div>
                    <div class="flex justify-between items-center border-b border-slate-800/80 pb-2">
                        <span class="text-[11px] font-bold text-slate-500 uppercase">Beneficiario</span>
                        <span class="text-sm font-bold text-white"><?php echo htmlspecialchars($transNombre); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[11px] font-bold text-slate-500 uppercase">RIF</span>
                        <span class="text-sm font-mono font-bold text-white"><?php echo htmlspecialchars($transRif); ?></span>
                    </div>
                </div>
            </div>

            <!-- TAB: ZELLE -->
            <div id="content-zelle" class="space-y-4 hidden animate-[fadeIn_0.3s_ease-in-out]">
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-3">
                    <div class="flex flex-col border-b border-slate-800/80 pb-2 text-center">
                        <div class="w-12 h-12 bg-emerald-500/10 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i data-lucide="dollar-sign" class="w-6 h-6 text-emerald-400"></i>
                        </div>
                        <span class="text-[11px] font-bold text-slate-500 uppercase mb-1">Correo Electrónico Zelle</span>
                        <span class="text-base font-mono font-bold text-emerald-400"><?php echo htmlspecialchars($zelleEmail); ?></span>
                    </div>
                    <div class="flex justify-between items-center text-center px-4">
                        <span class="text-[11px] font-bold text-slate-500 uppercase">Titular de la Cuenta</span>
                        <span class="text-sm font-bold text-white"><?php echo htmlspecialchars($transNombre); ?></span>
                    </div>
                </div>
            </div>

            <!-- TAB: PAYPAL -->
            <div id="content-paypal" class="space-y-4 hidden animate-[fadeIn_0.3s_ease-in-out]">
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-3">
                    <div class="flex flex-col border-b border-slate-800/80 pb-2 text-center">
                        <div class="w-12 h-12 bg-blue-500/10 rounded-full flex items-center justify-center mx-auto mb-2">
                            <i data-lucide="credit-card" class="w-6 h-6 text-blue-400"></i>
                        </div>
                        <span class="text-[11px] font-bold text-slate-500 uppercase mb-1">Correo PayPal</span>
                        <span class="text-sm font-mono font-bold text-blue-400"><?php echo htmlspecialchars($paypalEmail); ?></span>
                    </div>
                    <div class="bg-blue-500/10 border border-blue-500/20 p-3 rounded-lg flex items-start gap-2">
                        <i data-lucide="info" class="w-4 h-4 text-blue-400 shrink-0 mt-0.5"></i>
                        <p class="text-[10px] text-slate-400">Asegúrate de marcar el pago como "Enviar dinero a un amigo o familiar" para evitar comisiones extra.</p>
                    </div>
                </div>
            </div>

        </div>

        <div class="p-4 bg-slate-950 border-t border-slate-800 shrink-0 text-center z-10">
            <button onclick="cerrarModalCuentas(); abrirModalVoucher();" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-black font-extrabold text-xs shadow-lg transition-all flex items-center justify-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i> Ya pagué, quiero reportarlo
            </button>
        </div>
    </div>
</div>

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
                    <label id="labelMonto" class="block text-xs font-bold text-slate-400 uppercase mb-1">Monto Pagado (Bs.)</label>
                    <input type="number" step="0.01" min="1" max="500000" name="monto" id="inputMonto" required placeholder="Ej: 1500.00" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-cyan-500 font-extrabold font-mono">
                    <p id="lblMontoMax" class="text-[10px] text-slate-500 mt-1">Límite máximo permitido: 500,000 Bs.</p>
                </div>

                <div class="pt-4">
                    <button type="button" onclick="irAPaso(2)" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-black font-extrabold text-xs shadow-lg shadow-cyan-500/25 transition-all flex items-center justify-center gap-2">
                        Continuar a Datos de Pago <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <!-- PASO 2: DATOS DEL PAGO -->
            <div id="paso2" class="space-y-4 hidden">

                <div id="campoBanco">
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Banco de Origen</label>
                    <select name="banco_origen" id="inputBanco" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-cyan-500 font-semibold appearance-none">
                        <option value="">Selecciona tu banco...</option>
                        <option value="100% Banco">100% Banco</option>
                        <option value="Bancamiga">Bancamiga</option>
                        <option value="Bancaribe">Bancaribe</option>
                        <option value="Banco Activo">Banco Activo</option>
                        <option value="Banco Bicentenario">Banco Bicentenario</option>
                        <option value="Banco Caroní">Banco Caroní</option>
                        <option value="Banco de Venezuela (BDV)">Banco de Venezuela (BDV)</option>
                        <option value="Banco del Tesoro">Banco del Tesoro</option>
                        <option value="Banco Exterior">Banco Exterior</option>
                        <option value="Banco Mercantil">Banco Mercantil</option>
                        <option value="Banco Nacional de Crédito (BNC)">Banco Nacional de Crédito (BNC)</option>
                        <option value="Banco Plaza">Banco Plaza</option>
                        <option value="Banco Provincial">Banco Provincial</option>
                        <option value="Banco Venezolano de Crédito">Banco Venezolano de Crédito</option>
                        <option value="Banesco">Banesco</option>
                        <option value="Banplus">Banplus</option>
                        <option value="BFC Banco Fondo Común">BFC Banco Fondo Común</option>
                        <option value="Otros">Otro Banco...</option>
                    </select>
                </div>

                <div id="campoTelefono">
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Teléfono Emisor (Tu celular)</label>
                    <div class="flex">
                        <select name="prefijo_telefono" id="inputPrefijo" class="bg-slate-950 border border-r-0 border-slate-800 rounded-l-xl px-3 py-3 text-white text-sm focus:outline-none focus:border-cyan-500 font-bold appearance-none">
                            <option value="0412">0412</option>
                            <option value="0414">0414</option>
                            <option value="0424">0424</option>
                            <option value="0416">0416</option>
                            <option value="0426">0426</option>
                        </select>
                        <input type="text" name="telefono_emisor" id="inputTelefono" placeholder="1234567" maxlength="7" pattern="\d{7}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="w-full bg-slate-950 border border-slate-800 rounded-r-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-cyan-500 font-mono font-bold">
                    </div>
                </div>

                <div id="campoCorreoRemitente" class="hidden">
                    <label class="block text-xs font-bold text-cyan-400 uppercase mb-1 flex items-center gap-1.5">
                        <i data-lucide="mail" class="w-3.5 h-3.5"></i> Correo Electrónico Remitente
                    </label>
                    <input type="email" name="correo_remitente" id="inputCorreoRemitente" placeholder="ejemplo@correo.com" class="w-full bg-slate-950 border border-cyan-500/50 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-cyan-400 font-semibold">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label id="labelReferencia" class="block text-xs font-bold text-slate-400 uppercase mb-1">Referencia</label>
                        <input type="text" name="referencia" id="inputReferencia" required placeholder="Ej: 849201" maxlength="8" pattern="\d{4,8}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-cyan-500 font-mono font-bold">
                        <p id="subReferencia" class="text-[10px] text-slate-500 mt-1">Últimos 4 a 8 dígitos.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Fecha y Hora</label>
                        <input type="datetime-local" name="fecha_pago" value="<?php echo date('Y-m-d\TH:i'); ?>" max="<?php echo date('Y-m-d\TH:i'); ?>" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-cyan-500 font-semibold">
                    </div>
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="button" onclick="irAPaso(1)" class="w-1/3 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition-all">Atrás</button>
                    <button type="button" onclick="irAPaso(3)" class="w-2/3 py-3.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-black font-extrabold text-xs shadow-lg shadow-cyan-500/25 transition-all flex items-center justify-center gap-2">
                        Continuar: Subir Foto <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <!-- PASO 3: ARCHIVO SOPORTE Y ENVÍO -->
            <div id="paso3" class="space-y-4 hidden">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Comprobante Digital</label>
                    <div class="border-2 border-dashed border-slate-800 hover:border-cyan-500/50 rounded-2xl p-6 text-center transition-colors bg-slate-950/50 relative">
                        <i data-lucide="upload-cloud" class="w-8 h-8 text-cyan-400 mx-auto mb-2"></i>
                        <input type="file" name="soporte" id="comprobante" accept=".jpg,.jpeg,.png,.webp,.pdf" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="mostrarNombreArchivo(this, 'fileNameVoucher')">
                        <div class="flex items-center justify-center gap-2 pointer-events-none mt-2">
                            <span class="px-3 py-1 rounded border border-slate-700 bg-slate-900 text-cyan-400 text-[10px] font-bold">Seleccionar archivo</span>
                            <span id="fileNameVoucher" class="text-xs text-slate-400 truncate max-w-[150px]">Ningún archivo</span>
                        </div>
                        <p class="text-[10px] text-slate-500 mt-2">Formatos permitidos: JPG, PNG, WEBP o PDF (Máx. 5MB).</p>
                    </div>
                </div>

                <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800/80 text-[11px] text-slate-400 flex items-start gap-2">
                    <i data-lucide="info" class="w-4 h-4 text-cyan-400 shrink-0 mt-0.5"></i>
                    <span>Al enviar, el sistema auditará tu pago en NoSQL y te notificará por correo.</span>
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="button" onclick="irAPaso(2)" class="w-1/3 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition-all">Atrás</button>
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
                <p class="text-xs text-slate-400">Actualiza tus datos y sube tu foto de residente.</p>
            </div>
            <button onclick="cerrarModalPerfil()" class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="formPerfil" action="actualizar_perfil.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Foto de Perfil</label>
                <div class="relative border-2 border-dashed border-slate-700 hover:border-cyan-500/50 rounded-xl p-4 text-center transition-all bg-slate-950/50">
                    <input type="file" name="foto_perfil" accept="image/jpeg, image/png, image/webp" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="mostrarNombreArchivo(this, 'fileNamePerfil')">
                    <div class="flex flex-col items-center justify-center gap-1 pointer-events-none">
                        <i data-lucide="camera" class="w-5 h-5 text-cyan-400 mb-1"></i>
                        <span id="fileNamePerfil" class="text-xs text-slate-400 font-semibold bg-slate-900 px-3 py-1 rounded-md border border-slate-800">Haz clic para subir foto...</span>
                    </div>
                </div>
            </div>

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
                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Correo Electrónico</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($correoVecino); ?>" placeholder="ejemplo@gmail.com" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-cyan-500 font-semibold">
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-extrabold text-xs border border-slate-700 transition-all flex items-center justify-center gap-2 shadow-lg">
                    <i data-lucide="save" class="w-4 h-4 text-cyan-400"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
     MODAL DE MOTIVO DE RECHAZO
========================================== -->
<div id="modalRechazo" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-slate-900 border border-rose-500/30 rounded-3xl max-w-sm w-full overflow-hidden shadow-2xl modal-enter relative">
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-rose-500 to-orange-500 z-10"></div>
        <div class="p-5 border-b border-slate-800/80 flex items-center justify-between bg-slate-900 z-10">
            <div>
                <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-400"></i> Pago Rechazado
                </h3>
            </div>
            <button onclick="cerrarModalRechazo()" class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="p-6">
            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Mensaje de Administración:</label>
            <div class="bg-slate-950 p-4 rounded-xl border border-rose-500/20 text-sm text-slate-300 mb-5">
                <p id="textoMotivoRechazo"></p>
            </div>

            <div id="contenedorImgRechazo" class="hidden">
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Evidencia Adjunta:</label>
                <div class="bg-slate-950 rounded-xl border border-slate-800 p-2 overflow-hidden flex justify-center">
                    <img id="imgRechazo" src="" alt="Evidencia de Rechazo" class="max-h-48 object-contain rounded-lg">
                </div>
                <a id="btnVerImgCompleta" href="#" target="_blank" class="mt-2 block text-center text-[11px] font-bold text-cyan-400 hover:text-cyan-300 transition-colors underline">Ver imagen en tamaño completo</a>
            </div>
        </div>
        <div class="p-4 bg-slate-950 border-t border-slate-800 text-center">
            <button onclick="cerrarModalRechazo(); abrirModalVoucher();" class="w-full py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-extrabold text-xs transition-all">
                Volver a reportar el pago corregido
            </button>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();

    const modalVoucher = document.getElementById('modalVoucher');
    const modalPerfil  = document.getElementById('modalPerfil');
    const modalCuentas = document.getElementById('modalCuentas');
    const modalRechazo = document.getElementById('modalRechazo');

    function abrirModalVoucher() {
        modalVoucher.classList.remove('hidden'); modalVoucher.classList.add('flex');
        irAPaso(1);
    }
    function cerrarModalVoucher() {
        modalVoucher.classList.add('hidden'); modalVoucher.classList.remove('flex');
    }
    function abrirModalPerfil() {
        modalPerfil.classList.remove('hidden'); modalPerfil.classList.add('flex');
    }
    function cerrarModalPerfil() {
        modalPerfil.classList.add('hidden'); modalPerfil.classList.remove('flex');
    }
    function abrirModalCuentas() {
        modalCuentas.classList.remove('hidden'); modalCuentas.classList.add('flex');
        mostrarDatosPago('pago_movil'); // Mostrar siempre pago móvil al abrir
    }
    function cerrarModalCuentas() {
        modalCuentas.classList.add('hidden'); modalCuentas.classList.remove('flex');
    }

    function abrirMotivoRechazo(motivo, urlImagen) {
        document.getElementById('textoMotivoRechazo').innerText = motivo;
        const contenedorImg = document.getElementById('contenedorImgRechazo');
        const imgTag = document.getElementById('imgRechazo');
        const linkImg = document.getElementById('btnVerImgCompleta');

        if (urlImagen && urlImagen !== '') {
            contenedorImg.classList.remove('hidden');
            imgTag.src = urlImagen;
            linkImg.href = urlImagen;
        } else {
            contenedorImg.classList.add('hidden');
        }

        modalRechazo.classList.remove('hidden');
        modalRechazo.classList.add('flex');
    }

    function cerrarModalRechazo() {
        modalRechazo.classList.add('hidden');
        modalRechazo.classList.remove('flex');
    }

    window.addEventListener('click', (e) => {
        if (e.target === modalVoucher) cerrarModalVoucher();
        if (e.target === modalPerfil)  cerrarModalPerfil();
        if (e.target === modalCuentas) cerrarModalCuentas();
        if (e.target === modalRechazo) cerrarModalRechazo();
    });

    function mostrarNombreArchivo(input, spanId) {
        const span = document.getElementById(spanId);
        if (input.files && input.files.length > 0) {
            span.innerText = input.files[0].name;
            span.classList.add('text-white', 'border-cyan-500/50');
        } else {
            span.innerText = spanId === 'fileNamePerfil' ? "Subir nueva foto..." : "Ningún archivo";
            span.classList.remove('text-white', 'border-cyan-500/50');
        }
    }

    // =========================================================
    // LÓGICA DE TABS PARA MODAL DE CUENTAS
    // =========================================================
    function mostrarDatosPago(metodo) {
        const metodos = ['pago_movil', 'transferencia', 'zelle', 'paypal'];

        metodos.forEach(m => {
            document.getElementById(`content-${m}`).classList.add('hidden');
            const btn = document.getElementById(`tab-${m}`);
            btn.className = "py-2 px-3 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 hover:text-white font-bold text-[11px] flex items-center justify-center gap-1.5 transition-all";
        });

        document.getElementById(`content-${metodo}`).classList.remove('hidden');

        const btnActivo = document.getElementById(`tab-${metodo}`);
        btnActivo.className = "py-2 px-3 rounded-lg bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 font-bold text-[11px] flex items-center justify-center gap-1.5 transition-all";
    }

    // =========================================================
    // LÓGICA DINÁMICA DE CAMPOS (REPORTE DE PAGO)
    // =========================================================
    function verificarMetodoPago() {
        const metodo = document.querySelector('input[name="metodo_pago"]:checked').value;

        const campoCorreo = document.getElementById('campoCorreoRemitente');
        const inputCorreo = document.getElementById('inputCorreoRemitente');

        const campoBanco = document.getElementById('campoBanco');
        const inputBanco = document.getElementById('inputBanco');

        const campoTelefono = document.getElementById('campoTelefono');
        const inputTelefono = document.getElementById('inputTelefono');
        const inputPrefijo  = document.getElementById('inputPrefijo');

        const labelRef = document.getElementById('labelReferencia');
        const subRef   = document.getElementById('subReferencia');
        const inputRef = document.getElementById('inputReferencia');

        const labelMonto  = document.getElementById('labelMonto');
        const inputMonto  = document.getElementById('inputMonto');
        const lblMontoMax = document.getElementById('lblMontoMax');

        campoCorreo.classList.add('hidden'); inputCorreo.required = false;
        campoBanco.classList.add('hidden');  inputBanco.required = false;
        campoTelefono.classList.add('hidden'); inputTelefono.required = false; inputPrefijo.required = false;

        if (metodo === 'zelle' || metodo === 'paypal') {
            campoCorreo.classList.remove('hidden');
            inputCorreo.required = true;
            inputCorreo.type = "email";

            labelRef.innerText = `ID o Referencia de ${metodo.toUpperCase()}`;
            subRef.innerText   = `Coloca el código alfanumérico generado por la App.`;
            inputRef.maxLength = 25;
            inputRef.removeAttribute('pattern');
            inputRef.setAttribute('oninput', '');

            labelMonto.innerText = "Monto Pagado (USD $)";
            inputMonto.placeholder = "Ej: 50.00";
            inputMonto.max = 5000;
            lblMontoMax.innerText = `Límite máximo permitido: $ 5,000 USD.`;

        } else if (metodo === 'pago_movil') {
            campoBanco.classList.remove('hidden'); inputBanco.required = true;
            campoTelefono.classList.remove('hidden'); inputTelefono.required = true; inputPrefijo.required = true;

            labelRef.innerText = "N° de Referencia Bancaria";
            subRef.innerText   = "Exclusivamente los últimos 4 a 8 números.";
            inputRef.maxLength = 8;
            inputRef.pattern = "\\d{4,8}";
            inputRef.setAttribute('oninput', "this.value = this.value.replace(/[^0-9]/g, '')");

            labelMonto.innerText = "Monto Pagado (Bs.)";
            inputMonto.placeholder = "Ej: 1500.00";
            inputMonto.max = 500000;
            lblMontoMax.innerText = `Límite máximo permitido: 500,000 Bs.`;

        } else if (metodo === 'transferencia') {
            campoBanco.classList.remove('hidden'); inputBanco.required = true;

            labelRef.innerText = "N° de Referencia Bancaria";
            subRef.innerText   = "Exclusivamente los últimos 4 a 8 números.";
            inputRef.maxLength = 8;
            inputRef.pattern = "\\d{4,8}";
            inputRef.setAttribute('oninput', "this.value = this.value.replace(/[^0-9]/g, '')");

            labelMonto.innerText = "Monto Pagado (Bs.)";
            inputMonto.placeholder = "Ej: 5000.00";
            inputMonto.max = 9999999;
            lblMontoMax.innerText = `Límite máximo permitido: 9,999,999 Bs.`;
        }
    }

    document.addEventListener('DOMContentLoaded', verificarMetodoPago);

    function irAPaso(paso) {
        if (paso === 2) {
            const monto = document.getElementById('inputMonto');
            if (!monto.value || parseFloat(monto.value) <= 0) {
                alert('⚠️ Por favor ingresa un monto válido mayor a cero.'); return;
            }
            if (parseFloat(monto.value) > parseFloat(monto.max)) {
                alert(`⚠️ El monto supera el límite máximo permitido para este método de pago.`); return;
            }
        }

        if (paso === 3) {
            const form = document.getElementById('formVoucher');
            if (!form.reportValidity()) return;
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
            indicador.innerText = "PASO 2 DE 3: DATOS DEL PAGO";
            barra.className = "bg-gradient-to-r from-cyan-500 to-blue-500 h-full w-2/3 transition-all duration-300";
        } else {
            indicador.innerText = "PASO 3 DE 3: SUBIR COMPROBANTE";
            barra.className = "bg-gradient-to-r from-cyan-500 to-emerald-400 h-full w-full transition-all duration-300";
        }
    }

    // =========================================================
    // ENVÍO FETCH SEGURO (FORM DATA BLINDADO)
    // =========================================================
    document.getElementById('formVoucher').addEventListener('submit', async function(e) {
        e.preventDefault();

        const fileInput = document.getElementById('comprobante');
        if (!fileInput.files || fileInput.files.length === 0) {
            alert('❌ Error: Debes adjuntar la captura o foto del comprobante.'); return;
        }

        const archivoFisico = fileInput.files[0];
        if (archivoFisico.size > (5 * 1024 * 1024)) {
            alert('❌ Error: La imagen supera el límite de 5 MB.'); return;
        }

        const btn = document.getElementById('btnSubmitVoucher');
        const textoOriginal = btn.innerHTML;

        btn.innerHTML = `<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-black inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Auditando...`;
        btn.disabled = true;

        const formData = new FormData(this);
        formData.set('soporte', archivoFisico);

        try {
            const response = await fetch('procesar_pago.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.status === 'success') {
                alert('✅ ' + data.message + '\n\nTu N° de Rastreo Oficial es: ' + data.numero_rastreo);
                window.location.reload();
            } else {
                alert('❌ Error: ' + (data.message || 'Fallo interno.'));
                btn.innerHTML = textoOriginal; btn.disabled = false;
            }
        } catch (error) {
            alert('❌ Error de conexión.');
            btn.innerHTML = textoOriginal; btn.disabled = false;
        }
    });

    async function anularVoucher(idPago, referencia) {
        if (!confirm(`¿Estás seguro de que deseas ANULAR el reporte N° ${referencia}?`)) return;

        try {
            const response = await fetch('anular_pago.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: idPago })
            });
            const data = await response.json();
            if (data.status === 'success') {
                alert('✅ ' + data.message); window.location.reload();
            } else {
                alert('❌ Error: ' + (data.message || 'No se pudo anular.'));
            }
        } catch (error) {
            alert('❌ Error de conexión al anular.');
        }
    }
</script>
</body>
</html>