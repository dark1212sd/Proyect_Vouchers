<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Vecino - Lanceros de la Victoria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col items-center justify-center p-4">

    <div id="formContainer" class="animate__animated animate__fadeInUp bg-white p-8 rounded-3xl shadow-2xl w-full max-w-md border-b-8 border-blue-600">

        <div class="text-center mb-10">
            <div class="inline-block p-3 bg-blue-100 rounded-2xl mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tighter uppercase">Portal Vecinal</h1>
            <p class="text-slate-500 text-sm font-medium">Declaración Digital de Váuchers</p>
        </div>

        <form id="paymentForm" action="../src/procesar_pago.php" method="POST" enctype="multipart/form-data" class="space-y-6">

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Cédula del Propietario</label>
                <input type="text" name="cedula" placeholder="V-00000000" required
                       class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-blue-100 focus:border-blue-600 outline-none transition-all placeholder:opacity-50">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">N° Referencia</label>
                    <input type="text" name="referencia" placeholder="8 dígitos" required
                           class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-blue-100 focus:border-blue-600 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Monto (Bs.)</label>
                    <input type="number" step="0.01" name="monto" placeholder="0.00" required
                           class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-blue-100 focus:border-blue-600 outline-none transition-all font-bold text-blue-600">
                </div>
            </div>

            <div class="bg-slate-50 p-5 rounded-2xl border-2 border-dashed border-slate-200 hover:border-blue-400 transition-colors group">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-3 text-center group-hover:text-blue-600">Adjuntar Soporte (Imagen)</label>
                <input type="file" name="comprobante" accept="image/*" required
                       class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-slate-800 file:text-white hover:file:bg-blue-600 file:cursor-pointer cursor-pointer">
            </div>

            <div class="pt-2">
                <button type="submit" id="btnEnviar" class="w-full bg-blue-600 text-white font-black py-4 rounded-xl hover:bg-slate-900 transform hover:-translate-y-1 transition-all shadow-xl shadow-blue-200 active:scale-95 flex items-center justify-center">
                    <span id="btnText">ENVIAR DECLARACIÓN</span>

                    <svg id="spinner" class="hidden animate-spin h-5 w-5 text-white ml-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </div>
        </form>
    </div>

    <div class="mt-8 text-center">
        <p class="text-slate-400 text-[10px] font-bold uppercase tracking-[0.2em]">Junta Comunal Lanceros de la Victoria</p>
        <p class="text-slate-300 text-[9px] mt-1">Soberanía Tecnológica • Grupo N°16 UNETI</p>
    </div>

    <script>
        const form = document.getElementById('paymentForm');
        const btn = document.getElementById('btnEnviar');
        const spinner = document.getElementById('spinner');
        const btnText = document.getElementById('btnText');
        const container = document.getElementById('formContainer');

        form.addEventListener('submit', function() {
            // Deshabilitar UI para evitar múltiples envíos
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');

            // Activar estado de carga
            btnText.innerText = "PROCESANDO PAGO...";
            spinner.classList.remove('hidden');

            // Efecto visual de salida
            container.classList.remove('animate__fadeInUp');
            container.classList.add('animate__pulse');
        });
    </script>
</body>
</html>