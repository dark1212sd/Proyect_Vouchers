<?php
// public/dashboard.php
session_start();

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

// Verificación estricta de seguridad: Solo Admins
$rol = $_SESSION['role'] ?? $_SESSION['rol'] ?? 'user';
if (!isset($_SESSION['user_id']) || !in_array($rol, ['admin', 'superuser'])) {
    header('Location: auth/login.php');
    exit();
}

require __DIR__ . '/config/db.php';

$nombreAdmin = $_SESSION['nombre'] ?? $_SESSION['username'] ?? 'Administrador';

// =================================================================
// LÓGICA DE ESTADÍSTICAS Y EXTRACCIÓN DE PAGOS PENDIENTES
// =================================================================
$todosLosPagosCursor = $db->pagos->find([], ['sort' => ['created_at' => -1, 'fecha_declaracion' => -1]]);

$totalValidado = 0;
$totalPendiente = 0;
$countPendientes = 0;
$pagosPendientes = [];

foreach ($todosLosPagosCursor as $pago) {
    // Normalizamos el estatus para evitar problemas de mayúsculas, minúsculas o espacios
    $est = strtolower(trim($pago['estado'] ?? $pago['estatus'] ?? 'en revisión'));
    $monto = floatval((string)($pago['monto'] ?? 0));

    if (in_array($est, ['aprobado', 'validado', 'completado', 'solvente'])) {
        $totalValidado += $monto;
    } elseif (in_array($est, ['en revisión', 'pendiente', 'verificando', 'revision'])) {
        $totalPendiente += $monto;
        $countPendientes++;
        // Guardamos los que están en revisión en un array para usarlos en la tabla de abajo
        $pagosPendientes[] = $pago;
    }
}

// Obtener la cantidad de residentes registrados
$totalUsuarios = $db->usuarios->countDocuments(['role' => ['$ne' => 'admin']]);
if ($totalUsuarios == 0) {
    $totalUsuarios = $db->usuarios->countDocuments([]); // Fallback si no hay distincion de roles
}

?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - VoucherCheck</title>
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
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #0f172a; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
        .glow-emerald { box-shadow: 0 0 20px -5px rgba(16, 185, 129, 0.4); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen selection:bg-emerald-500 selection:text-black flex flex-col">

<!-- BARRA DE NAVEGACIÓN (ADMIN) -->
<nav class="border-b border-slate-800/80 bg-slate-900/50 backdrop-blur-md sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <img src="assets/img/logo_alianza_victoriosa_anime.svg" alt="Logo" class="w-10 h-10 glow-emerald rounded-xl bg-slate-950 p-1 border border-slate-800">
            <div>
                <span class="font-black text-lg tracking-tight text-white block leading-none">VOUCHER<span class="text-emerald-400">CHECK</span></span>
                <span class="text-[9px] font-bold text-cyan-400 tracking-widest uppercase block mt-0.5">Módulo Administrativo</span>
            </div>
        </div>

        <div class="flex items-center gap-3 sm:gap-4">
            <div class="hidden md:flex flex-col text-right mr-2">
                <span class="text-xs font-bold text-white"><?php echo htmlspecialchars($nombreAdmin); ?></span>
                <span class="text-[10px] text-emerald-400 font-bold uppercase">Administrador</span>
            </div>
            <a href="auth/logout.php" title="Cerrar Sesión" class="p-2 rounded-xl bg-slate-800/80 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 border border-slate-700/50 hover:border-rose-500/30 transition-all">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10 flex-1 w-full">

    <!-- ==========================================
         SECCIÓN 1: TARJETAS DE ESTADÍSTICAS
         ========================================== -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">

        <!-- Recaudación Validada -->
        <div class="bg-slate-900 border border-emerald-500/20 rounded-3xl p-6 relative overflow-hidden shadow-xl shadow-emerald-900/10">
            <h3 class="text-[11px] font-black uppercase tracking-wider text-emerald-400 mb-2">Recaudación Validada</h3>
            <div class="text-3xl font-black text-white mb-3">Bs. <?php echo number_format($totalValidado, 2, ',', '.'); ?></div>
            <span class="inline-block text-[10px] text-slate-400 bg-slate-950 px-3 py-1.5 rounded-lg border border-slate-800">Total en bóveda del edificio</span>
            <i data-lucide="trending-up" class="absolute -right-4 -bottom-4 w-28 h-28 text-emerald-500/5 rotate-[-15deg]"></i>
        </div>

        <!-- Por Auditar -->
        <div class="bg-slate-900 border border-amber-500/20 rounded-3xl p-6 relative overflow-hidden shadow-xl shadow-amber-900/10">
            <h3 class="text-[11px] font-black uppercase tracking-wider text-amber-400 mb-2">Por Auditar / En Revisión</h3>
            <div class="text-3xl font-black text-white mb-3">Bs. <?php echo number_format($totalPendiente, 2, ',', '.'); ?></div>
            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold text-amber-400 bg-amber-500/10 px-3 py-1.5 rounded-lg border border-amber-500/20">
                <i data-lucide="bell" class="w-3.5 h-3.5"></i> <?php echo $countPendientes; ?> Vouchers en cola
            </span>
            <i data-lucide="alert-circle" class="absolute -right-4 -bottom-4 w-28 h-28 text-amber-500/5 rotate-[-15deg]"></i>
        </div>

        <!-- Residentes -->
        <div class="bg-slate-900 border border-cyan-500/20 rounded-3xl p-6 relative overflow-hidden shadow-xl shadow-cyan-900/10">
            <h3 class="text-[11px] font-black uppercase tracking-wider text-cyan-400 mb-2">Residentes Registrados</h3>
            <div class="text-3xl font-black text-white mb-3"><?php echo $totalUsuarios; ?> <span class="text-lg font-bold text-slate-400">Aptos.</span></div>
            <span class="inline-block text-[10px] text-slate-400 bg-slate-950 px-3 py-1.5 rounded-lg border border-slate-800">Comunidad Activa</span>
            <i data-lucide="users" class="absolute -right-4 -bottom-4 w-28 h-28 text-cyan-500/5"></i>
        </div>
    </div>

    <!-- ==========================================
         SECCIÓN 2: BOTONES DE ACCIÓN RÁPIDA
         ========================================== -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">

        <a href="gestion_vecinos.php" class="bg-slate-900/80 border border-slate-800 hover:border-cyan-500/50 rounded-2xl p-4 flex items-center gap-4 transition-all group hover:bg-slate-900">
            <div class="w-12 h-12 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-center text-cyan-400 group-hover:bg-cyan-500/10 transition-colors">
                <i data-lucide="users" class="w-6 h-6"></i>
            </div>
            <div>
                <h4 class="font-bold text-white text-sm">Gestión de Vecinos</h4>
                <p class="text-[10px] text-slate-500">Crear, editar o suspender cuentas</p>
            </div>
        </a>

        <a href="reportes.php" class="bg-slate-900/80 border border-slate-800 hover:border-emerald-500/50 rounded-2xl p-4 flex items-center gap-4 transition-all group hover:bg-slate-900">
            <div class="w-12 h-12 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-center text-emerald-400 group-hover:bg-emerald-500/10 transition-colors">
                <i data-lucide="bar-chart-2" class="w-6 h-6"></i>
            </div>
            <div>
                <h4 class="font-bold text-white text-sm">Reportes y Cierres</h4>
                <p class="text-[10px] text-slate-500">Generar PDF y métricas mensuales</p>
            </div>
        </a>

        <a href="admin_ajustes.php" class="bg-slate-900/80 border border-slate-800 hover:border-blue-500/50 rounded-2xl p-4 flex items-center gap-4 transition-all group hover:bg-slate-900">
            <div class="w-12 h-12 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-center text-blue-400 group-hover:bg-blue-500/10 transition-colors">
                <i data-lucide="settings" class="w-6 h-6"></i>
            </div>
            <div>
                <h4 class="font-bold text-white text-sm">Ajustes de Portal</h4>
                <p class="text-[10px] text-slate-500">Configurar cuotas, bancos y roles</p>
            </div>
        </a>

    </div>

    <!-- ==========================================
         SECCIÓN 3: TABLA DE PAGOS PENDIENTES
         ========================================== -->
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm mb-8">
        <div class="p-6 border-b border-slate-800/80 flex items-center justify-between bg-slate-900/90">
            <div>
                <h2 class="text-base font-extrabold text-white flex items-center gap-2">
                    <i data-lucide="clipboard-list" class="w-5 h-5 text-emerald-400"></i> Auditoría de Comprobantes
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Revisa el soporte digital y aprueba o rechaza las transacciones.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                <tr class="border-b border-slate-800/80 bg-slate-950 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                    <th class="py-4 px-4">N° Rastreo / Fecha</th>
                    <th class="py-4 px-4">Residente</th>
                    <th class="py-4 px-4">Método y Ref.</th>
                    <th class="py-4 px-4">Monto</th>
                    <th class="py-4 px-4 text-center">Soporte</th>
                    <th class="py-4 px-4 text-right">Acción</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-xs text-slate-300 font-medium">
                <?php if (empty($pagosPendientes)): ?>
                    <tr>
                        <td colspan="6" class="py-12 text-center text-slate-500">
                            <i data-lucide="check-circle-2" class="w-12 h-12 mx-auto mb-3 text-emerald-500/50"></i>
                            <p class="font-bold text-sm text-white">¡Todo al día!</p>
                            <p class="text-xs mt-1">No hay pagos pendientes de auditoría en este momento.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pagosPendientes as $pago): ?>
                        <?php
                        $idPagoStr = (string)$pago['_id'];
                        $rastreo = $pago['numero_rastreo'] ?? 'S/N';

                        $fecha = "N/A";
                        if (!empty($pago['fecha_pago'])) {
                            $fechaObj = date_create($pago['fecha_pago']);
                            $fecha = $fechaObj ? date_format($fechaObj, 'd/m/Y') : $pago['fecha_pago'];
                        } elseif (!empty($pago['fecha_declaracion']) && $pago['fecha_declaracion'] instanceof MongoDB\BSON\UTCDateTime) {
                            $fecha = $pago['fecha_declaracion']->toDateTime()->format('d/m/Y');
                        }

                        $montoVal = floatval((string)($pago['monto'] ?? 0));
                        $metodo = $pago['metodo_pago'] ?? 'Digital';
                        $monedaStr = (in_array(strtolower($metodo), ['zelle', 'paypal'])) ? '$' : 'Bs.';

                        // Residente info (Intentamos obtenerlo del pago, sino fallback a "Residente")
                        $residenteName = $pago['nombre_residente'] ?? $pago['nombre'] ?? 'Residente';
                        $residenteApto = $pago['apto'] ?? $pago['departamento'] ?? 'S/A';
                        $urlSoporte = $pago['soporte_url'] ?? $pago['archivo'] ?? null;
                        ?>
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="py-4 px-4">
                                <span class="font-mono font-black text-cyan-400 text-xs bg-cyan-500/10 px-2 py-1 rounded-md border border-cyan-500/20 block w-max mb-1">
                                    <?php echo htmlspecialchars($rastreo); ?>
                                </span>
                                <span class="text-[10px] text-slate-500"><i data-lucide="calendar" class="w-3 h-3 inline"></i> <?php echo htmlspecialchars($fecha); ?></span>
                            </td>
                            <td class="py-4 px-4">
                                <span class="block font-bold text-white"><?php echo htmlspecialchars($residenteName); ?></span>
                                <span class="text-[10px] text-slate-400">Apto: <strong class="text-cyan-400"><?php echo htmlspecialchars($residenteApto); ?></strong></span>
                            </td>
                            <td class="py-4 px-4">
                                <span class="block font-mono font-bold text-white"><?php echo htmlspecialchars($pago['referencia_bancaria'] ?? $pago['referencia'] ?? 'S/R'); ?></span>
                                <span class="text-[10px] text-slate-500 uppercase">
                                    <?php echo htmlspecialchars(str_replace('_', ' ', $metodo)); ?>
                                    <?php if(!empty($pago['banco_origen'])) echo " - " . htmlspecialchars($pago['banco_origen']); ?>
                                </span>
                            </td>
                            <td class="py-4 px-4 font-black text-white text-sm">
                                <?php echo $monedaStr; ?> <?php echo number_format($montoVal, 2, ',', '.'); ?>
                            </td>
                            <td class="py-4 px-4 text-center">
                                <?php if ($urlSoporte): ?>
                                    <a href="<?php echo htmlspecialchars($urlSoporte); ?>" target="_blank" class="inline-flex items-center justify-center p-2 bg-slate-800 hover:bg-slate-700 text-cyan-400 rounded-lg border border-slate-700 transition-all" title="Ver Comprobante">
                                        <i data-lucide="image" class="w-4 h-4"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-[10px] text-rose-400 bg-rose-500/10 px-2 py-1 rounded border border-rose-500/20">Sin Soporte</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- BOTÓN APROBAR -->
                                    <button type="button" onclick="aprobarPago('<?php echo $idPagoStr; ?>', '<?php echo $rastreo; ?>')" title="Aprobar Pago" class="p-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500 text-emerald-400 hover:text-slate-950 transition-all border border-emerald-500/30 font-bold flex items-center gap-1 text-[11px] uppercase tracking-wider">
                                        <i data-lucide="check" class="w-4 h-4"></i> Validar
                                    </button>

                                    <!-- BOTÓN RECHAZAR -->
                                    <button type="button" onclick="abrirModalRechazoAdmin('<?php echo $idPagoStr; ?>', '<?php echo $rastreo; ?>')" title="Rechazar Pago" class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white transition-all border border-rose-500/30">
                                        <i data-lucide="x" class="w-4 h-4"></i>
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

<!-- ==========================================
     MODAL DEL ADMIN PARA RECHAZAR EL PAGO
========================================== -->
<div id="modalRechazarAdmin" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-slate-900 border border-rose-500/30 rounded-3xl max-w-md w-full overflow-hidden shadow-2xl relative modal-enter">
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-orange-500 to-rose-500 z-10"></div>

        <div class="p-5 border-b border-slate-800/80 flex items-center justify-between bg-slate-900 z-10">
            <div>
                <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-400"></i> Rechazar Pago
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">Rastreo: <strong id="txtRastreoRechazo" class="text-white"></strong></p>
            </div>
            <button type="button" onclick="cerrarModalRechazoAdmin()" class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="formRechazarPagoAdmin" class="flex flex-col">
            <input type="hidden" name="pago_id" id="inputRechazoPagoId">

            <div class="p-6 space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Motivo del Rechazo <span class="text-rose-500">*</span></label>
                    <textarea name="motivo_rechazo" required rows="3" placeholder="Ej: La transferencia no se reflejó en la cuenta del condominio o el monto es incorrecto." class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-rose-500 resize-none"></textarea>
                    <p class="text-[10px] text-slate-500 mt-1">Este mensaje se enviará por correo al residente.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Adjuntar Evidencia (Opcional)</label>
                    <div class="relative border-2 border-dashed border-slate-700 hover:border-rose-500/50 rounded-xl p-4 text-center transition-all bg-slate-950 text-slate-400 hover:text-rose-400">
                        <input type="file" name="imagen_evidencia" accept="image/jpeg, image/png, image/webp" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="document.getElementById('fileNameEvidencia').innerText = this.files[0].name;">
                        <div class="flex flex-col items-center justify-center gap-1 pointer-events-none">
                            <i data-lucide="image-plus" class="w-5 h-5 mb-1"></i>
                            <span id="fileNameEvidencia" class="text-xs font-semibold">Subir captura de pantalla (JPG/PNG)</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 bg-slate-950 border-t border-slate-800 flex gap-3">
                <button type="button" onclick="cerrarModalRechazoAdmin()" class="w-1/3 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition-all">Cancelar</button>
                <button type="submit" id="btnProcesarRechazo" class="w-2/3 py-3 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-extrabold text-xs shadow-lg transition-all flex items-center justify-center gap-2">
                    <i data-lucide="send" class="w-4 h-4"></i> Procesar y Notificar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    lucide.createIcons();

    // ===============================================
    // LÓGICA DE APROBACIÓN RÁPIDA (Fetch)
    // ===============================================
    async function aprobarPago(idPago, rastreo) {
        if (!confirm(`¿Estás seguro de que deseas VALIDAR el pago N° ${rastreo}?`)) return;

        try {
            const response = await fetch('api_aprobar_pago.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ 'pago_id': idPago })
            });
            const data = await response.json();
            if (data.status === 'success') {
                alert('✅ Pago validado correctamente.');
                window.location.reload();
            } else {
                alert('❌ Error: ' + (data.message || 'No se pudo aprobar.'));
            }
        } catch (error) {
            alert('❌ Error de conexión al intentar aprobar.');
        }
    }

    // ===============================================
    // LÓGICA DE RECHAZO CON MODAL
    // ===============================================
    const modalRechazarAdmin = document.getElementById('modalRechazarAdmin');

    function abrirModalRechazoAdmin(idPago, rastreo) {
        document.getElementById('inputRechazoPagoId').value = idPago;
        document.getElementById('txtRastreoRechazo').innerText = rastreo;
        modalRechazarAdmin.classList.remove('hidden');
        modalRechazarAdmin.classList.add('flex');
    }

    function cerrarModalRechazoAdmin() {
        modalRechazarAdmin.classList.add('hidden');
        modalRechazarAdmin.classList.remove('flex');
        document.getElementById('formRechazarPagoAdmin').reset();
        document.getElementById('fileNameEvidencia').innerText = 'Subir captura de pantalla (JPG/PNG)';
    }

    // Enviar el formulario de rechazo
    document.getElementById('formRechazarPagoAdmin').addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('btnProcesarRechazo');
        const txtOriginal = btn.innerHTML;
        btn.innerHTML = `<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Procesando...`;
        btn.disabled = true;

        const formData = new FormData(this);

        try {
            const response = await fetch('api_rechazar_pago.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.status === 'success') {
                alert('✅ ' + data.message);
                window.location.reload();
            } else {
                alert('❌ Error: ' + (data.message || 'Fallo interno.'));
                btn.innerHTML = txtOriginal; btn.disabled = false;
            }
        } catch (error) {
            alert('❌ Error de red al procesar.');
            btn.innerHTML = txtOriginal; btn.disabled = false;
        }
    });

</script>
</body>
</html>