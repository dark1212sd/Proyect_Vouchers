<?php
// Este archivo es público para los vecinos.
// No incluimos lógica de redirección aquí para que el formulario se muestre siempre.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Vecino - Lanceros de la Victoria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col items-center justify-center p-4">

<div id="formContainer" class="animate__animated animate__fadeInUp bg-white p-8 rounded-[2.5rem] shadow-2xl w-full max-w-md border border-slate-100">

    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-2xl mb-4 shadow-lg shadow-blue-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
            </svg>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Portal de Pagos</h1>
        <p class="text-slate-400 text-xs font-semibold uppercase tracking-widest mt-1">Junta Comunal - Lanceros</p>
    </div>

    <form id="paymentForm" class="space-y-5">
        <div>
            <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-2 ml-1">Cédula del Propietario</label>
            <input type="text" name="cedula" placeholder="V-00000000" required
                   class="w-full px-5 py-4 rounded-2xl bg-slate-50 border-none focus:ring-4 focus:ring-blue-100 focus:bg-white text-slate-700 font-semibold transition-all outline-none">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-2 ml-1">N° Referencia</label>
                <input type="text" name="referencia" placeholder="8 dígitos" required
                       class="w-full px-5 py-4 rounded-2xl bg-slate-50 border-none focus:ring-4 focus:ring-blue-100 focus:bg-white text-slate-700 font-semibold transition-all outline-none">
            </div>
            <div>
                <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-2 ml-1">Monto (Bs.)</label>
                <input type="number" step="0.01" name="monto" placeholder="0.00" required
                       class="w-full px-5 py-4 rounded-2xl bg-slate-50 border-none focus:ring-4 focus:ring-blue-100 focus:bg-white text-blue-600 font-bold transition-all outline-none">
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-blue-50/50 p-6 rounded-3xl border-2 border-dashed border-blue-100 hover:border-blue-400 transition-all group relative">
                <label class="block text-[10px] font-extrabold text-blue-600 uppercase mb-3 text-center group-hover:scale-105 transition-transform">
                    Adjuntar Soporte de Pago
                </label>
                <input type="file" id="fileInput" name="comprobante" accept="image/*" required
                       class="block w-full text-[10px] text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-bold file:bg-blue-600 file:text-white hover:file:bg-slate-900 file:cursor-pointer cursor-pointer">
            </div>

            <div id="previewContainer" class="hidden animate__animated animate__fadeIn">
                <div class="flex items-center justify-between mb-2 px-1">
                    <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-tighter">Vista previa del váucher</p>
                    <button type="button" id="removePreview" class="text-rose-500 hover:text-rose-700 text-[10px] font-bold flex items-center">
                        REMOVER
                    </button>
                </div>
                <div class="relative rounded-2xl overflow-hidden border-2 border-slate-100 bg-white shadow-inner">
                    <img id="imagePreview" src="#" alt="Vista previa" class="w-full h-44 object-contain p-2">
                </div>
            </div>
        </div>

        <div class="pt-4">
            <button type="submit" id="btnEnviar" class="group w-full bg-slate-900 text-white font-bold py-5 rounded-2xl hover:bg-blue-600 transform hover:-translate-y-1 shadow-2xl shadow-slate-200 transition-all active:scale-95 flex items-center justify-center">
                <span id="btnText">ENVIAR DECLARACIÓN</span>
            </button>
        </div>
    </form>
</div>

<p class="mt-8 text-slate-300 text-[9px] font-bold uppercase tracking-[0.3em]">Grupo N°16 • UNETI 2026</p>

<script src="js/app.js"></script>
</body>
</html>