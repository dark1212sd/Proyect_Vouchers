var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
/**
 * dashboard.ts - Lógica del Centro de Mando Maestro
 * Sistema: Lanceros de la Victoria
 */
let todosLosPagos = [];
/**
 * Carga inicial de datos y estadísticas
 */
function cargarPagos() {
    return __awaiter(this, void 0, void 0, function* () {
        const tabla = document.getElementById('tablaPagos');
        tabla.innerHTML = '<tr><td colspan="5" class="p-10 text-center text-slate-400 animate-pulse font-bold uppercase text-[9px]">Sincronizando con base de datos...</td></tr>';
        try {
            const response = yield fetch('listar_pagos.php');
            if (!response.ok)
                throw new Error('Error en la API');
            todosLosPagos = yield response.json();
            renderizarTabla(todosLosPagos);
        }
        catch (error) {
            console.error(error);
            tabla.innerHTML = '<tr><td colspan="5" class="p-10 text-center text-red-500 font-bold">Error al conectar con MongoDB</td></tr>';
        }
    });
}
/**
 * Calcula y actualiza las métricas financieras superiores
 */
function actualizarMetricas(pagos) {
    const statRecaudado = document.getElementById('statRecaudado');
    const statPendiente = document.getElementById('statPendiente');
    const statTotal = document.getElementById('statTotal');
    const recaudado = pagos
        .filter(p => p.estatus === 'validado')
        .reduce((sum, p) => sum + parseFloat(p.monto), 0);
    const pendiente = pagos
        .filter(p => p.estatus === 'pendiente')
        .reduce((sum, p) => sum + parseFloat(p.monto), 0);
    statRecaudado.innerText = recaudado.toLocaleString('es-VE', { minimumFractionDigits: 2 });
    statPendiente.innerText = pendiente.toLocaleString('es-VE', { minimumFractionDigits: 2 });
    statTotal.innerText = pagos.length.toString();
}
/**
 * Renderiza la tabla de declaraciones recientes
 */
function renderizarTabla(pagosParaMostrar) {
    const tabla = document.getElementById('tablaPagos');
    tabla.innerHTML = '';
    actualizarMetricas(pagosParaMostrar);
    if (pagosParaMostrar.length === 0) {
        tabla.innerHTML = '<tr><td colspan="5" class="p-10 text-center text-slate-400 font-bold uppercase text-[9px]">No hay resultados</td></tr>';
        return;
    }
    pagosParaMostrar.forEach(pago => {
        const row = document.createElement('tr');
        row.className = "hover:bg-slate-50 transition-all animate__animated animate__fadeIn";
        const esPendiente = pago.estatus === 'pendiente';
        row.innerHTML = `
            <td class="p-6 font-bold text-slate-700">${pago.cedula}</td>
            <td class="p-6 text-slate-500 font-mono text-xs uppercase">${pago.referencia}</td>
            <td class="p-6 text-center">
                <span class="font-black text-blue-600">Bs. ${parseFloat(pago.monto).toLocaleString('es-VE', { minimumFractionDigits: 2 })}</span>
            </td>
            <td class="p-6 text-center">
                <span class="px-3 py-1.5 rounded-full text-[9px] font-black uppercase tracking-widest ${esPendiente ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600'}">
                    ${pago.estatus}
                </span>
            </td>
            <td class="p-6 flex justify-center gap-2">
                <a href="uploads/vouchers/${pago.soporte}" target="_blank" class="bg-slate-100 text-slate-500 px-3 py-2 rounded-xl hover:bg-slate-800 hover:text-white transition-all text-[9px] font-black uppercase">VER</a>
                ${esPendiente ? `<button onclick="validarPago('${pago.id}')" class="bg-emerald-500 text-white px-3 py-2 rounded-xl hover:bg-emerald-600 transition-all text-[9px] font-black uppercase">APROBAR</button>` : ''}
            </td>
        `;
        tabla.appendChild(row);
    });
}
/**
 * Función para validar pagos
 */
function validarPago(id) {
    return __awaiter(this, void 0, void 0, function* () {
        if (!confirm('¿Confirmas que este pago es legítimo?'))
            return;
        try {
            const response = yield fetch('aprobar_pago.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const result = yield response.json();
            if (result.status === 'success')
                yield cargarPagos();
        }
        catch (error) {
            alert("Error de red.");
        }
    });
}
// Evento del Buscador
const inputBusqueda = document.getElementById('busquedaCedula');
inputBusqueda === null || inputBusqueda === void 0 ? void 0 : inputBusqueda.addEventListener('input', (e) => {
    const termino = e.target.value.toUpperCase();
    const resultados = todosLosPagos.filter(p => p.cedula.toUpperCase().includes(termino) || p.referencia.toUpperCase().includes(termino));
    renderizarTabla(resultados);
});
// Exposición global
window.validarPago = validarPago;
window.cargarPagos = cargarPagos;
document.addEventListener('DOMContentLoaded', cargarPagos);
