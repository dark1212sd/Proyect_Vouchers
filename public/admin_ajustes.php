<?php
// public/admin_ajustes.php
session_start();

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

// Protección de sesión: Solo administradores
$rol = $_SESSION['role'] ?? $_SESSION['rol'] ?? 'user';
if (!isset($_SESSION['user_id']) || !in_array($rol, ['admin', 'superuser'])) {
    header("Location: auth/login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

// Cargar la configuración actual desde MongoDB
try {
    // Configuraciones Generales
    $confGlobal = $db->configuracion->findOne(['tipo' => 'global']) ?? [];
    $cuota_actual = $confGlobal['cuota_base'] ?? 35.00;
    $dia_corte = $confGlobal['dia_corte'] ?? "5";

    // Configuraciones de Cuentas de Recaudación
    $confCuentas = $db->configuracion->findOne(['tipo' => 'cuentas_recaudacion']) ?? [];
    $zelle_email = $confCuentas['zelle_email'] ?? '';
    $paypal_email = $confCuentas['paypal_email'] ?? '';
    $pm_banco = $confCuentas['pm_banco'] ?? '';

    // Extraer prefijo y número de teléfono si existe
    $pm_telefono = $confCuentas['pm_telefono'] ?? '';
    $partes_tel = explode('-', $pm_telefono);
    $prefijo_actual = count($partes_tel) > 1 ? $partes_tel[0] : '0412';
    $numero_actual = count($partes_tel) > 1 ? $partes_tel[1] : $pm_telefono;

    $pm_cedula = $confCuentas['pm_cedula'] ?? '';
    $transfer_banco = $confCuentas['transfer_banco'] ?? '';
    $transfer_cuenta = $confCuentas['transfer_cuenta'] ?? '';
    $transfer_nombre = $confCuentas['transfer_nombre'] ?? '';
    $transfer_rif = $confCuentas['transfer_rif'] ?? '';
    $qr_actual = $confCuentas['qr_url'] ?? null;

} catch (Exception $e) {
    // Fallback por defecto si no hay conexión
    $cuota_actual = 35.00; $dia_corte = "5";
}

// Lista estándar de bancos de Venezuela para los selects
$lista_bancos = [
        "0156 - 100% Banco",
        "0172 - Bancamiga",
        "0114 - Bancaribe",
        "0171 - Banco Activo",
        "0166 - Banco Agrícola de Venezuela",
        "0175 - Banco Bicentenario",
        "0128 - Banco Caroní",
        "0102 - Banco de Venezuela (BDV)",
        "0163 - Banco del Tesoro",
        "0115 - Banco Exterior",
        "0105 - Banco Mercantil",
        "0191 - Banco Nacional de Crédito (BNC)",
        "0138 - Banco Plaza",
        "0108 - Banco Provincial",
        "0104 - Banco Venezolano de Crédito",
        "0168 - Bancrecer",
        "0134 - Banesco",
        "0177 - Banfanb",
        "0146 - Bangente",
        "0169 - Banplus",
        "0151 - BFC Banco Fondo Común",
        "0157 - DELSUR",
        "0169 - Mi Banco",
        "0137 - Sofitasa"
];
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustes de Portal - Admin</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        neon: { cyan: '#00f2fe', blue: '#4facfe', emerald: '#10b981' }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glow-blue { box-shadow: 0 0 25px -5px rgba(79, 172, 254, 0.4); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex flex-col selection:bg-blue-500 selection:text-slate-950">

<form id="formAjustes" enctype="multipart/form-data" class="flex flex-col min-h-screen">

    <!-- HEADER ADMIN -->
    <header class="bg-slate-900/90 border-b border-slate-800/80 sticky top-0 z-40 backdrop-blur-md">
        <div class="max-w-4xl mx-auto px-4 h-20 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="dashboard.php" class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-blue-400 transition-colors" title="Volver al Dashboard">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <span class="text-lg font-black text-white tracking-tight">PORTAL<span class="text-blue-400">AJUSTES</span></span>
                    <span class="text-[10px] font-bold text-slate-500 tracking-widest uppercase block mt-0.5">Configuración Global</span>
                </div>
            </div>

            <button type="submit" id="btnGuardar" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-blue-500 to-cyan-400 hover:opacity-90 text-slate-950 font-black text-xs transition-all flex items-center gap-2 glow-blue">
                <i data-lucide="save" class="w-4 h-4 stroke-[2.5]"></i>
                <span id="txtGuardar">Guardar Cambios</span>
            </button>
        </div>
    </header>

    <!-- CONTENIDO DE AJUSTES -->
    <main class="max-w-4xl mx-auto px-4 py-8 w-full space-y-8 flex-grow">

        <div id="alertBox" class="hidden p-4 rounded-2xl border text-xs font-bold flex items-center gap-3 animate-pulse transition-all">
            <i id="alertIcon" data-lucide="" class="w-5 h-5 shrink-0"></i>
            <span id="alertMsg">Mensaje aquí</span>
        </div>

        <!-- ==========================================
             1. CONFIGURACIÓN FINANCIERA
             ========================================== -->
        <section class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1 bg-blue-500"></div>
            <h2 class="text-sm font-black text-white flex items-center gap-2 mb-6 pb-3 border-b border-slate-800">
                <i data-lucide="calculator" class="w-5 h-5 text-blue-400"></i> Parámetros Financieros
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Cuota Base (USD)</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500 font-bold">$</div>
                        <input type="number" name="cuota_base" step="0.01" min="1" max="10000" value="<?php echo htmlspecialchars((string)$cuota_actual); ?>" required class="w-full pl-9 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono text-lg focus:border-blue-400 focus:outline-none transition-colors">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Día de Corte Mensual</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500"><i data-lucide="calendar" class="w-4 h-4"></i></div>
                        <select name="dia_corte" class="w-full pl-10 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white font-bold focus:border-blue-400 focus:outline-none transition-colors appearance-none cursor-pointer">
                            <option value="5" <?php echo ($dia_corte == '5') ? 'selected' : ''; ?>>Día 5 de cada mes</option>
                            <option value="10" <?php echo ($dia_corte == '10') ? 'selected' : ''; ?>>Día 10 de cada mes</option>
                            <option value="15" <?php echo ($dia_corte == '15') ? 'selected' : ''; ?>>Día 15 de cada mes</option>
                            <option value="30" <?php echo ($dia_corte == '30') ? 'selected' : ''; ?>>Fin de mes (Día 30)</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-500"><i data-lucide="chevron-down" class="w-4 h-4"></i></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==========================================
             2. CUENTAS RECAUDADORAS
             ========================================== -->
        <section class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl relative overflow-hidden mb-12">
            <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
            <h2 class="text-sm font-black text-white flex items-center gap-2 mb-6 pb-3 border-b border-slate-800">
                <i data-lucide="landmark" class="w-5 h-5 text-amber-400"></i> Cuentas Recaudadoras Oficiales
            </h2>

            <div class="space-y-6">
                <!-- ZELLE / PAYPAL -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-amber-400 uppercase tracking-wider mb-2">Correo Zelle</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500"><i data-lucide="dollar-sign" class="w-4 h-4"></i></div>
                            <input type="email" name="zelle_email" value="<?php echo htmlspecialchars($zelle_email); ?>" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" title="Debe contener un @ y un dominio válido" placeholder="pagos@condominio.com" class="w-full pl-10 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono focus:border-amber-400 focus:outline-none transition-colors">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-blue-400 uppercase tracking-wider mb-2">Correo PayPal</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500"><i data-lucide="credit-card" class="w-4 h-4"></i></div>
                            <input type="email" name="paypal_email" value="<?php echo htmlspecialchars($paypal_email); ?>" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" title="Debe contener un @ y un dominio válido" placeholder="paypal@condominio.com" class="w-full pl-10 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono focus:border-blue-400 focus:outline-none transition-colors">
                        </div>
                    </div>
                </div>

                <!-- PAGO MÓVIL Y QR -->
                <div class="p-5 bg-slate-950 rounded-xl border border-slate-800">
                    <h4 class="text-xs font-bold text-cyan-400 uppercase mb-4 flex items-center gap-1.5"><i data-lucide="smartphone" class="w-4 h-4"></i> Datos de Pago Móvil Oficial</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Banco</label>
                            <div class="relative">
                                <select name="pm_banco" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-500 appearance-none">
                                    <option value="">Seleccione...</option>
                                    <?php foreach($lista_bancos as $banco): ?>
                                        <option value="<?php echo $banco; ?>" <?php echo ($pm_banco == $banco) ? 'selected' : ''; ?>><?php echo $banco; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-500"><i data-lucide="chevron-down" class="w-4 h-4"></i></div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Teléfono Receptivo</label>
                            <div class="flex">
                                <select id="pm_prefijo" class="bg-slate-900 border border-r-0 border-slate-700 rounded-l-lg px-2 py-2.5 text-white text-sm focus:outline-none focus:border-cyan-500 font-bold appearance-none">
                                    <option value="0412" <?php echo ($prefijo_actual == '0412') ? 'selected' : ''; ?>>0412</option>
                                    <option value="0414" <?php echo ($prefijo_actual == '0414') ? 'selected' : ''; ?>>0414</option>
                                    <option value="0424" <?php echo ($prefijo_actual == '0424') ? 'selected' : ''; ?>>0424</option>
                                    <option value="0416" <?php echo ($prefijo_actual == '0416') ? 'selected' : ''; ?>>0416</option>
                                    <option value="0426" <?php echo ($prefijo_actual == '0426') ? 'selected' : ''; ?>>0426</option>
                                </select>
                                <input type="text" id="pm_numero" value="<?php echo htmlspecialchars($numero_actual); ?>" placeholder="1234567" maxlength="7" pattern="\d{7}" title="Debe contener exactamente 7 números" oninput="this.value = this.value.replace(/\D/g, '')" class="w-full bg-slate-900 border border-slate-700 rounded-r-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-500 font-mono font-bold">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">RIF / Cédula</label>
                            <input type="text" name="pm_cedula" value="<?php echo htmlspecialchars($pm_cedula); ?>" placeholder="Ej: J-12345678-9" pattern="^[VEJPGvejpg]-?\d{7,9}$" title="Formato válido: J-12345678" oninput="this.value = this.value.toUpperCase().replace(/[^VEJPG0-9-]/g, '')" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-500 font-mono">
                        </div>
                    </div>

                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Código QR Escaneable (Opcional)</label>
                    <div class="flex items-center gap-4">
                        <?php if($qr_actual): ?>
                            <div class="w-16 h-16 shrink-0 bg-white rounded-lg p-1 border border-cyan-500">
                                <img src="<?php echo htmlspecialchars($qr_actual); ?>" class="w-full h-full object-contain">
                            </div>
                        <?php endif; ?>
                        <div class="relative w-full border-2 border-dashed border-slate-700 hover:border-cyan-500/50 rounded-xl p-3 text-center transition-all bg-slate-900">
                            <input type="file" name="qr_image" id="qrInput" accept="image/jpeg, image/png, image/webp" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="mostrarNombreQR(this)">
                            <div class="flex flex-col items-center justify-center gap-1 pointer-events-none">
                                <span id="fileNameQR" class="text-xs text-slate-400 font-semibold">Subir nueva imagen QR (Sustituye la actual)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TRANSFERENCIA BANCARIA -->
                <div class="p-5 bg-slate-950 rounded-xl border border-slate-800">
                    <h4 class="text-xs font-bold text-blue-400 uppercase mb-4 flex items-center gap-1.5"><i data-lucide="building-2" class="w-4 h-4"></i> Transferencia Bancaria Nacional</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Banco Destino</label>
                            <div class="relative">
                                <select name="transfer_banco" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-blue-500 appearance-none">
                                    <option value="">Seleccione...</option>
                                    <?php foreach($lista_bancos as $banco): ?>
                                        <option value="<?php echo $banco; ?>" <?php echo ($transfer_banco == $banco) ? 'selected' : ''; ?>><?php echo $banco; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-500"><i data-lucide="chevron-down" class="w-4 h-4"></i></div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">RIF de la Cuenta</label>
                            <input type="text" name="transfer_rif" value="<?php echo htmlspecialchars($transfer_rif); ?>" placeholder="Ej: J-00000000-0" pattern="^[VEJPGvejpg]-?\d{7,9}$" oninput="this.value = this.value.toUpperCase().replace(/[^VEJPG0-9-]/g, '')" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-blue-500 font-mono">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Número de Cuenta <span class="text-rose-400">(Debe tener exactamente 20 dígitos)</span></label>
                            <input type="text" name="transfer_cuenta" value="<?php echo htmlspecialchars($transfer_cuenta); ?>" placeholder="01050000000000000000" maxlength="20" pattern="\d{20}" title="Debe contener exactamente 20 números sin espacios ni guiones" oninput="this.value = this.value.replace(/\D/g, '')" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-blue-500 font-mono tracking-widest">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Nombre a mostrar (Beneficiario)</label>
                            <input type="text" name="transfer_nombre" value="<?php echo htmlspecialchars($transfer_nombre); ?>" placeholder="Ej: Condominio Alianza Victoriosa" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-blue-500">
                        </div>
                    </div>
                </div>

            </div>
        </section>

    </main>
</form>

<script>
    lucide.createIcons();

    function mostrarNombreQR(input) {
        const span = document.getElementById('fileNameQR');
        if (input.files && input.files.length > 0) {
            span.innerText = "Seleccionado: " + input.files[0].name;
            span.classList.add('text-cyan-400');
        }
    }

    // ENVÍO DE DATOS REAL A api_ajustes.php
    document.getElementById('formAjustes').addEventListener('submit', async function(e) {
        e.preventDefault();

        // Validar el formulario nativamente antes de procesar el JS
        if (!this.reportValidity()) return;

        const btnGuardar = document.getElementById('btnGuardar');
        const txtGuardar = document.getElementById('txtGuardar');
        const alertBox = document.getElementById('alertBox');
        const alertIcon = document.getElementById('alertIcon');
        const alertMsg = document.getElementById('alertMsg');

        // Estado cargando
        btnGuardar.disabled = true;
        btnGuardar.classList.add('opacity-75', 'cursor-wait');
        txtGuardar.innerHTML = 'Guardando en NoSQL...';

        const formData = new FormData(this);

        // Unir el prefijo telefónico y el número para enviarlo empaquetado a PHP
        const prefijo = document.getElementById('pm_prefijo').value;
        const numero = document.getElementById('pm_numero').value;
        if(numero.trim() !== '') {
            formData.append('pm_telefono', prefijo + '-' + numero);
        }

        try {
            const response = await fetch('api_ajustes.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            alertBox.classList.remove('hidden', 'bg-emerald-500/10', 'border-emerald-500/30', 'text-emerald-400', 'bg-rose-500/10', 'border-rose-500/30', 'text-rose-400');

            if (data.status === 'success') {
                alertBox.classList.add('bg-emerald-500/10', 'border-emerald-500/30', 'text-emerald-400');
                alertIcon.setAttribute('data-lucide', 'check-circle-2');
                alertMsg.innerText = data.message;
            } else {
                alertBox.classList.add('bg-rose-500/10', 'border-rose-500/30', 'text-rose-400');
                alertIcon.setAttribute('data-lucide', 'alert-circle');
                alertMsg.innerText = 'Error: ' + data.message;
            }

            lucide.createIcons();

            // Volver arriba para ver alerta
            window.scrollTo({ top: 0, behavior: 'smooth' });

        } catch (error) {
            console.error(error);
            alertBox.classList.remove('hidden');
            alertBox.classList.add('bg-rose-500/10', 'border-rose-500/30', 'text-rose-400');
            alertIcon.setAttribute('data-lucide', 'alert-circle');
            alertMsg.innerText = 'Error crítico al contactar con el servidor MongoDB.';
            lucide.createIcons();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } finally {
            // Restaurar botón
            btnGuardar.disabled = false;
            btnGuardar.classList.remove('opacity-75', 'cursor-wait');
            txtGuardar.innerHTML = 'Guardar Cambios';

            // Ocultar alerta después de 4 segundos
            setTimeout(() => {
                alertBox.classList.add('hidden');
            }, 4000);
        }
    });
</script>
</body>
</html>