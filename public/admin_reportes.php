<?php
// public/admin_reportes.php
session_start();

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

// Verificación estricta de seguridad
$rol = $_SESSION['role'] ?? $_SESSION['rol'] ?? 'user';
if (!isset($_SESSION['user_id']) || !in_array($rol, ['admin', 'superuser'])) {
    header('Location: auth/login.php');
    exit();
}

$nombreAdmin = $_SESSION['nombre'] ?? 'Administrador';

// LISTA COMPLETA DE BANCOS DE VENEZUELA CON PREFIJOS
$lista_bancos = [
        "0102 - Banco de Venezuela (BDV)",
        "0104 - Banco Venezolano de Crédito",
        "0105 - Banco Mercantil",
        "0108 - Banco Provincial",
        "0114 - Bancaribe",
        "0115 - Banco Exterior",
        "0128 - Banco Caroní",
        "0134 - Banesco",
        "0138 - Banco Plaza",
        "0151 - BFC Banco Fondo Común",
        "0156 - 100% Banco",
        "0157 - Banco del Sur",
        "0163 - Banco del Tesoro",
        "0166 - Banco Agrícola de Venezuela",
        "0168 - Bancrecer",
        "0169 - Mi Banco",
        "0171 - Banco Activo",
        "0172 - Bancamiga",
        "0174 - Banplus",
        "0175 - Banco Bicentenario",
        "0177 - BANFANB",
        "0191 - Banco Nacional de Crédito (BNC)"
];
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Financieros - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }

        /* ESTILOS ESPECÍFICOS PARA IMPRESIÓN PDF */
        @media print {
            body { background: white !important; color: black !important; }
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            .dark\:text-white { color: black !important; }
            .dark\:bg-slate-950 { background: white !important; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 11px; }
            th, td { border: 1px solid #ddd !important; padding: 8px !important; text-align: left !important; color: black !important; }
            th { background-color: #f3f4f6 !important; font-weight: bold !important; -webkit-print-color-adjust: exact; }
            .shadow-2xl, .rounded-3xl, .border-slate-800\/80 { border: none !important; box-shadow: none !important; }
            @page { margin: 1cm; }
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen selection:bg-emerald-500 selection:text-black flex flex-col">

<!-- HEADER IMPRIMIBLE (Oculto en pantalla, visible en PDF) -->
<div class="hidden print-only text-center pb-6 border-b-2 border-black mb-6">
    <h1 class="text-2xl font-bold uppercase tracking-widest">Condominio Alianza Victoriosa</h1>
    <h2 class="text-lg text-gray-700">Reporte de Auditoría Financiera</h2>
    <p class="text-sm text-gray-500" id="printFechas"></p>
    <p class="text-xs text-gray-400 mt-2">Generado por: <?php echo htmlspecialchars($nombreAdmin); ?> el <?php echo date('d/m/Y H:i'); ?></p>
</div>

<!-- BARRA DE NAVEGACIÓN -->
<header class="bg-slate-900/90 border-b border-slate-800/80 sticky top-0 z-40 backdrop-blur-md no-print">
    <div class="max-w-7xl mx-auto px-4 h-20 flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a href="dashboard.php" class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-emerald-400 transition-colors" title="Volver al Dashboard">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <span class="text-lg font-black text-white tracking-tight">REPORTES<span class="text-emerald-400">FINANCIEROS</span></span>
                <span class="text-[10px] font-bold text-slate-500 tracking-widest uppercase block mt-0.5">Analítica Avanzada</span>
            </div>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-grow">

    <!-- PANEL DE FILTROS DINÁMICOS -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 mb-8 shadow-xl no-print">
        <form id="formFiltros" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Fecha Desde</label>
                <input type="date" name="fecha_inicio" id="fecha_inicio" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Fecha Hasta</label>
                <input type="date" name="fecha_fin" id="fecha_fin" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Estatus</label>
                <select name="estado" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-emerald-500 outline-none appearance-none">
                    <option value="">Todos los Estatus</option>
                    <option value="aprobado">Aprobado / Validado</option>
                    <option value="en revisión">En Revisión / Pendiente</option>
                    <option value="rechazado">Rechazado</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Método de Pago</label>
                <select name="metodo_pago" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-emerald-500 outline-none appearance-none">
                    <option value="">Todos los métodos</option>
                    <option value="pago_movil">Pago Móvil</option>
                    <option value="transferencia">Transferencia Bancaria</option>
                    <option value="zelle">Zelle (USD)</option>
                    <option value="paypal">PayPal (USD)</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Banco Origen</label>
                <select name="banco" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-emerald-500 outline-none appearance-none">
                    <option value="">Todos los Bancos</option>
                    <?php foreach($lista_bancos as $b) echo "<option value=\"$b\">$b</option>"; ?>
                </select>
            </div>

            <div class="lg:col-span-5 flex justify-end gap-3 mt-2 border-t border-slate-800 pt-4">
                <button type="submit" id="btnFiltrar" class="px-5 py-2.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500 text-emerald-400 hover:text-slate-950 border border-emerald-500/30 transition-all text-xs font-bold flex items-center gap-2">
                    <i data-lucide="filter" class="w-4 h-4"></i> Aplicar Filtros
                </button>
            </div>
        </form>
    </div>

    <!-- TARJETAS DE RESULTADOS (RESUMEN) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8" id="contenedorTarjetas">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-lg text-center">
            <h3 class="text-[10px] font-bold uppercase text-slate-500 mb-1">Total Ingresos Bs.</h3>
            <div class="text-xl font-black text-emerald-400" id="statBs">Bs. 0,00</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-lg text-center">
            <h3 class="text-[10px] font-bold uppercase text-slate-500 mb-1">Total Ingresos USD</h3>
            <div class="text-xl font-black text-emerald-400" id="statUsd">$ 0.00</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-lg text-center">
            <h3 class="text-[10px] font-bold uppercase text-slate-500 mb-1">Transacciones Aprobadas</h3>
            <div class="text-xl font-black text-white" id="statAprobados">0</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-lg text-center">
            <h3 class="text-[10px] font-bold uppercase text-slate-500 mb-1">Pagos en Cola / Rechazados</h3>
            <div class="text-xl font-black text-amber-400"><span id="statPendientes">0</span> <span class="text-slate-600">/</span> <span class="text-rose-400" id="statRechazados">0</span></div>
        </div>
    </div>

    <!-- BOTONES DE EXPORTACIÓN -->
    <div class="flex flex-col sm:flex-row justify-between items-center mb-4 no-print gap-4">
        <h2 class="text-sm font-bold text-white flex items-center gap-2"><i data-lucide="table" class="w-4 h-4"></i> Datos del Reporte</h2>
        <div class="flex gap-2">
            <button onclick="exportarCSV()" class="px-4 py-2 rounded-lg bg-blue-500/10 hover:bg-blue-500 text-blue-400 hover:text-white transition-all text-[11px] font-bold border border-blue-500/30 flex items-center gap-1.5">
                <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5"></i> Exportar a Excel
            </button>
            <button onclick="window.print()" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-white transition-all text-[11px] font-bold border border-slate-600 flex items-center gap-1.5">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i> Imprimir / PDF
            </button>
        </div>
    </div>

    <!-- TABLA DE RESULTADOS -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left" id="tablaReportes">
                <thead>
                <tr class="border-b border-slate-800 bg-slate-950 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <th class="py-3 px-4">Fecha</th>
                    <th class="py-3 px-4">Apto / Residente</th>
                    <th class="py-3 px-4">Método / Banco</th>
                    <th class="py-3 px-4">Referencia</th>
                    <th class="py-3 px-4">Monto</th>
                    <th class="py-3 px-4">Estatus</th>
                </tr>
                </thead>
                <tbody id="tbodyReportes" class="divide-y divide-slate-800/50 text-xs text-slate-300 font-medium">
                <!-- Filas dinámicas -->
                <tr>
                    <td colspan="6" class="py-12 text-center text-slate-500">
                        Haz clic en "Aplicar Filtros" para cargar los datos.
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
    lucide.createIcons();

    // Variable global para almacenar la data actual generada y poder exportarla
    let reporteDataActual = [];

    // Pre-Cargar al iniciar
    document.addEventListener('DOMContentLoaded', () => {
        // Establecer fechas del mes actual por defecto
        const date = new Date();
        const primerDia = new Date(date.getFullYear(), date.getMonth(), 1).toISOString().split('T')[0];
        const ultimoDia = new Date(date.getFullYear(), date.getMonth() + 1, 0).toISOString().split('T')[0];

        document.getElementById('fecha_inicio').value = primerDia;
        document.getElementById('fecha_fin').value = ultimoDia;

        cargarReporte(); // Cargar data inicial
    });

    document.getElementById('formFiltros').addEventListener('submit', function(e) {
        e.preventDefault();
        cargarReporte();
    });

    async function cargarReporte() {
        const form = document.getElementById('formFiltros');
        const formData = new FormData(form);
        const btn = document.getElementById('btnFiltrar');
        const tbody = document.getElementById('tbodyReportes');

        btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Cargando...`;
        btn.disabled = true;

        // Actualizar header de impresión
        const fInicio = document.getElementById('fecha_inicio').value;
        const fFin = document.getElementById('fecha_fin').value;
        document.getElementById('printFechas').innerText = `Período auditado: ${fInicio || 'Histórico'} hasta ${fFin || 'Actualidad'}`;

        try {
            const response = await fetch('api_reportes.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.status === 'success') {
                reporteDataActual = result.data; // Guardar para CSV

                // Actualizar Tarjetas
                document.getElementById('statBs').innerText = 'Bs. ' + result.stats.total_monto_bs.toLocaleString('es-VE', {minimumFractionDigits: 2});
                document.getElementById('statUsd').innerText = '$ ' + result.stats.total_monto_usd.toLocaleString('en-US', {minimumFractionDigits: 2});
                document.getElementById('statAprobados').innerText = result.stats.aprobados;
                document.getElementById('statPendientes').innerText = result.stats.pendientes;
                document.getElementById('statRechazados').innerText = result.stats.rechazados;

                // Renderizar Tabla
                tbody.innerHTML = '';
                if(result.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-slate-500">No se encontraron pagos con estos filtros.</td></tr>`;
                } else {
                    result.data.forEach(p => {
                        let colorEstado = 'text-slate-400';
                        if(p.estado === 'APROBADO' || p.estado === 'VALIDADO') colorEstado = 'text-emerald-400 font-bold';
                        if(p.estado === 'RECHAZADO') colorEstado = 'text-rose-400 font-bold';
                        if(p.estado === 'EN REVISIÓN') colorEstado = 'text-amber-400 font-bold';

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="py-3 px-4">${p.fecha}</td>
                            <td class="py-3 px-4"><span class="text-cyan-400 font-bold">[${p.apto}]</span> ${p.residente}</td>
                            <td class="py-3 px-4"><span class="block">${p.metodo}</span><span class="text-[9px] text-slate-500">${p.banco}</span></td>
                            <td class="py-3 px-4 font-mono text-[10px]">${p.referencia}<br><span class="text-slate-600">ID: ${p.rastreo}</span></td>
                            <td class="py-3 px-4 font-bold text-white">${p.moneda === 'USD' ? '$' : 'Bs.'} ${p.monto.toLocaleString(p.moneda==='USD'?'en-US':'es-VE', {minimumFractionDigits: 2})}</td>
                            <td class="py-3 px-4 ${colorEstado}">${p.estado}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            } else {
                alert('Error al cargar reporte: ' + result.message);
            }
        } catch (error) {
            console.error(error);
            alert('Error de conexión al servidor.');
        } finally {
            btn.innerHTML = `<i data-lucide="filter" class="w-4 h-4"></i> Aplicar Filtros`;
            btn.disabled = false;
            lucide.createIcons();
        }
    }

    // EXPORTADOR A CSV (Excel)
    function exportarCSV() {
        if(reporteDataActual.length === 0) {
            alert('No hay datos para exportar.'); return;
        }

        // Cabeceras del CSV
        let csvContent = "Fecha,Apartamento,Residente,Metodo Pago,Banco,Referencia,Rastreo,Moneda,Monto,Estatus\n";

        reporteDataActual.forEach(p => {
            // Limpiar comas internas para no romper el CSV
            let row = [
                `"${p.fecha}"`,
                `"${p.apto}"`,
                `"${p.residente}"`,
                `"${p.metodo}"`,
                `"${p.banco}"`,
                `'${p.referencia}'`, // Comilla simple para forzar formato texto en Excel
                `'${p.rastreo}'`,
                `"${p.moneda}"`,
                p.monto,
                `"${p.estado}"`
            ];
            csvContent += row.join(",") + "\n";
        });

        // Crear blob y descargar
        const blob = new Blob(["\ufeff", csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", `Reporte_Condominio_${new Date().getTime()}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
</body>
</html>