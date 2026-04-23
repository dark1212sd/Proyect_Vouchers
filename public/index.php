<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: auth/login.html");
    exit();
}

require __DIR__ . '/config/db.php';

// Obtener datos del usuario
$cedula_usuario = '';
try {
    $usuario = $db->usuarios->findOne(['username' => $_SESSION['username']]);
    if ($usuario) {
        $cedula_usuario = $usuario['cedula'] ?? '';
    }

    // Obtenemos la configuración de los pagos (si no existe, usamos datos de prueba por ahora)
    // Más adelante programaremos el panel para editar esto
    $config = $db->configuracion->findOne(['tipo' => 'datos_pago']);
    $info_pagomovil = $config['pagomovil'] ?? "Banco: Banesco (0134)\nCI: V-12345678\nTeléfono: 0414-1234567";
    $info_transferencia = $config['transferencia'] ?? "Banco de Venezuela\nCuenta Corriente\n0102-0000-00-0000000000\nA nombre de: Junta Comunal Lanceros";
    $info_efectivo = $config['efectivo'] ?? "Entregue el efectivo en un sobre cerrado con su nombre y número de casa a la tesorera (Sra. María - Casa #45) entre 8:00 AM y 6:00 PM.";
    $info_electronico = $config['electronico'] ?? "Zelle: tesoreria@lanceros.com\nPayPal: paypal.me/lanceros";

} catch (Exception $e) {
    // Manejo de errores
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportar Pago - Lanceros de la Victoria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .step-active { border-color: #10b981; color: #10b981; } /* Emerald 500 */
        .step-inactive { border-color: #e2e8f0; color: #94a3b8; }
        .line-active { background-color: #10b981; }
        .line-inactive { background-color: #e2e8f0; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col items-center py-10 px-4">

<div class="w-full max-w-2xl mb-6 animate__animated animate__fadeInDown flex justify-between">
    <a href="user_panel.php" class="text-xs font-bold text-slate-500 hover:text-blue-600 flex items-center gap-2 transition-colors">
        ← VOLVER A MI PANEL
    </a>
</div>

<div class="animate__animated animate__fadeInUp bg-white p-8 md:p-12 rounded-[2.5rem] shadow-2xl w-full max-w-2xl border border-slate-100">

    <div class="flex items-center justify-between mb-10 relative">
        <div class="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1 bg-slate-200 -z-10 rounded-full"></div>
        <div id="progress-line" class="absolute left-0 top-1/2 transform -translate-y-1/2 h-1 bg-emerald-500 -z-10 rounded-full transition-all duration-500" style="width: 0%;"></div>

        <div class="flex flex-col items-center bg-white px-2">
            <div id="icon-step-1" class="w-10 h-10 rounded-full border-4 flex items-center justify-center font-bold bg-white transition-colors step-active">1</div>
            <span class="text-[10px] font-black uppercase mt-2 text-slate-700">Método</span>
        </div>
        <div class="flex flex-col items-center bg-white px-2">
            <div id="icon-step-2" class="w-10 h-10 rounded-full border-4 flex items-center justify-center font-bold bg-white transition-colors step-inactive">2</div>
            <span class="text-[10px] font-black uppercase mt-2 text-slate-400">Detalles</span>
        </div>
        <div class="flex flex-col items-center bg-white px-2">
            <div id="icon-step-3" class="w-10 h-10 rounded-full border-4 flex items-center justify-center font-bold bg-white transition-colors step-inactive">3</div>
            <span class="text-[10px] font-black uppercase mt-2 text-slate-400">Soporte</span>
        </div>
    </div>

    <form id="paymentForm" class="relative">
        <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($cedula_usuario); ?>">

        <div id="step-1" class="space-y-6">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">¿Cómo realizaste el pago?</h2>
                <p class="text-slate-400 text-xs font-semibold mt-1">Selecciona la opción correspondiente</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="cursor-pointer">
                    <input type="radio" name="metodo_pago" value="pagomovil" class="peer sr-only" required>
                    <div class="p-5 rounded-2xl border-2 border-slate-100 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50 transition-all">
                        <div class="text-2xl mb-2">📱</div>
                        <h3 class="font-bold text-slate-700">Pago Móvil</h3>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="metodo_pago" value="transferencia" class="peer sr-only">
                    <div class="p-5 rounded-2xl border-2 border-slate-100 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50 transition-all">
                        <div class="text-2xl mb-2">🏦</div>
                        <h3 class="font-bold text-slate-700">Transferencia Bancaria</h3>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="metodo_pago" value="efectivo" class="peer sr-only">
                    <div class="p-5 rounded-2xl border-2 border-slate-100 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50 transition-all">
                        <div class="text-2xl mb-2">💵</div>
                        <h3 class="font-bold text-slate-700">Efectivo</h3>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="metodo_pago" value="electronico" class="peer sr-only">
                    <div class="p-5 rounded-2xl border-2 border-slate-100 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-slate-50 transition-all">
                        <div class="text-2xl mb-2">💻</div>
                        <h3 class="font-bold text-slate-700">Pago Electrónico</h3>
                    </div>
                </label>
            </div>

            <button type="button" onclick="nextStep(2)" class="w-full mt-6 bg-slate-900 text-white font-bold py-4 rounded-2xl hover:bg-emerald-500 transition-all">
                CONTINUAR
            </button>
        </div>

        <div id="step-2" class="hidden space-y-6">
            <button type="button" onclick="prevStep(1)" class="text-xs font-bold text-slate-400 hover:text-slate-800 mb-4">← Atrás</button>

            <div class="bg-blue-50 border border-blue-100 p-5 rounded-2xl mb-6">
                <h4 class="text-[10px] font-black text-blue-600 uppercase tracking-widest mb-2">Datos para el pago</h4>
                <pre id="info-instrucciones" class="text-xs text-slate-600 font-sans whitespace-pre-wrap"></pre>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div class="md:col-span-2">
                    <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-2">Monto Declarado</label>
                    <input type="number" step="0.01" name="monto" id="monto" placeholder="0.00" class="w-full px-5 py-4 rounded-2xl bg-slate-50 focus:ring-4 focus:ring-emerald-100 outline-none font-bold text-lg">
                </div>

                <div id="caja-divisa" class="hidden md:col-span-2">
                    <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-2">Moneda Entregada</label>
                    <select name="divisa" class="w-full px-5 py-4 rounded-2xl bg-slate-50 focus:ring-4 focus:ring-emerald-100 outline-none font-bold text-slate-700 appearance-none">
                        <option value="bs">Bolívares (Bs)</option>
                        <option value="usd">Dólares (USD)</option>
                        <option value="eur">Euros (EUR)</option>
                    </select>
                </div>

                <div id="caja-plataforma" class="hidden md:col-span-2">
                    <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-2">Plataforma Utilizada</label>
                    <input type="text" name="plataforma" placeholder="Ej: PayPal, Zelle, Binance Pay" class="w-full px-5 py-4 rounded-2xl bg-slate-50 focus:ring-4 focus:ring-emerald-100 outline-none font-bold text-slate-700">
                </div>

                <div id="caja-referencia" class="md:col-span-2">
                    <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-2">N° de Referencia / Recibo</label>
                    <input type="text" name="referencia" id="referencia" placeholder="Últimos dígitos de la transacción" class="w-full px-5 py-4 rounded-2xl bg-slate-50 focus:ring-4 focus:ring-emerald-100 outline-none font-bold text-slate-700">
                </div>
            </div>

            <button type="button" onclick="nextStep(3)" class="w-full mt-6 bg-slate-900 text-white font-bold py-4 rounded-2xl hover:bg-emerald-500 transition-all">
                CONTINUAR
            </button>
        </div>

        <div id="step-3" class="hidden space-y-6">
            <button type="button" onclick="prevStep(2)" class="text-xs font-bold text-slate-400 hover:text-slate-800 mb-4">← Atrás</button>

            <div class="text-center mb-6">
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Casi listo</h2>
                <p id="texto-soporte" class="text-slate-400 text-xs font-semibold mt-1">Adjunta el comprobante (Opcional para efectivo)</p>
            </div>

            <div class="bg-emerald-50/50 p-6 rounded-3xl border-2 border-dashed border-emerald-200 hover:border-emerald-500 transition-all relative">
                <label class="block text-[10px] font-extrabold text-emerald-600 uppercase mb-3 text-center cursor-pointer">
                    Toca para subir imagen
                </label>
                <input type="file" id="fileInput" name="comprobante" accept="image/*"
                       class="block w-full text-[10px] text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-bold file:bg-emerald-500 file:text-white cursor-pointer">
            </div>

            <div id="previewContainer" class="hidden mt-4">
                <img id="imagePreview" src="#" class="w-full h-48 object-contain rounded-xl border border-slate-200 bg-slate-50 p-2">
            </div>

            <button type="submit" id="btnEnviar" class="w-full mt-6 bg-emerald-500 text-white font-black py-5 rounded-2xl hover:bg-emerald-600 transform hover:-translate-y-1 transition-all shadow-xl shadow-emerald-200">
                <span id="btnText">FINALIZAR Y ENVIAR</span>
            </button>
        </div>
    </form>
</div>

<script>
    // Textos inyectados desde PHP
    const datosPago = {
        'pagomovil': `<?php echo addslashes($info_pagomovil); ?>`,
        'transferencia': `<?php echo addslashes($info_transferencia); ?>`,
        'efectivo': `<?php echo addslashes($info_efectivo); ?>`,
        'electronico': `<?php echo addslashes($info_electronico); ?>`
    };

    function nextStep(step) {
        // Validar paso 1
        if (step === 2) {
            const metodo = document.querySelector('input[name="metodo_pago"]:checked');
            if (!metodo) { alert("Selecciona un método de pago primero."); return; }

            // Llenar info y configurar campos según método
            document.getElementById('info-instrucciones').innerText = datosPago[metodo.value];

            // Lógica de visualización
            const cajaDivisa = document.getElementById('caja-divisa');
            const cajaPlataforma = document.getElementById('caja-plataforma');
            const cajaReferencia = document.getElementById('caja-referencia');
            const fileInput = document.getElementById('fileInput');

            // Resetear
            cajaDivisa.classList.add('hidden');
            cajaPlataforma.classList.add('hidden');
            cajaReferencia.classList.remove('hidden');
            fileInput.required = true;
            document.getElementById('texto-soporte').innerText = "Adjunta la captura de pantalla de la transacción";

            if (metodo.value === 'efectivo') {
                cajaDivisa.classList.remove('hidden');
                cajaReferencia.classList.add('hidden');
                fileInput.required = false; // En efectivo la foto es opcional
                document.getElementById('texto-soporte').innerText = "Si tiene una foto del recibo o sobre, puede subirla (Opcional)";
            } else if (metodo.value === 'electronico') {
                cajaPlataforma.classList.remove('hidden');
            }
        }

        // Validar paso 2
        if (step === 3) {
            const monto = document.getElementById('monto').value;
            const metodo = document.querySelector('input[name="metodo_pago"]:checked').value;
            const referencia = document.getElementById('referencia').value;

            if (!monto) { alert("Ingresa el monto."); return; }
            if (metodo !== 'efectivo' && !referencia) { alert("Ingresa la referencia."); return; }
        }

        // Animación de cambio de pasos
        document.querySelectorAll('[id^="step-"]').forEach(el => el.classList.add('hidden'));
        const nextEl = document.getElementById(`step-${step}`);
        nextEl.classList.remove('hidden');
        nextEl.classList.add('animate__animated', 'animate__fadeInRight');

        // Actualizar barra verde (Stepper UI)
        document.getElementById('progress-line').style.width = step === 1 ? '0%' : step === 2 ? '50%' : '100%';

        for (let i = 1; i <= 3; i++) {
            const icon = document.getElementById(`icon-step-${i}`);
            const text = icon.nextElementSibling;
            if (i <= step) {
                icon.className = "w-10 h-10 rounded-full border-4 flex items-center justify-center font-bold transition-colors border-emerald-500 bg-emerald-500 text-white";
                text.classList.add('text-emerald-600'); text.classList.remove('text-slate-400', 'text-slate-700');
            } else {
                icon.className = "w-10 h-10 rounded-full border-4 flex items-center justify-center font-bold bg-white transition-colors border-slate-200 text-slate-400";
                text.classList.remove('text-emerald-600', 'text-slate-700'); text.classList.add('text-slate-400');
            }
        }
    }

    function prevStep(step) {
        document.querySelectorAll('[id^="step-"]').forEach(el => el.classList.add('hidden'));
        const prevEl = document.getElementById(`step-${step}`);
        prevEl.classList.remove('hidden');
        prevEl.classList.add('animate__animated', 'animate__fadeInLeft');

        // Simular que el nextStep arregla la barra retrocediendo
        nextStep(step);
    }

    // Previsualización de la imagen
    document.getElementById('fileInput').addEventListener('change', function(e) {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                document.getElementById('imagePreview').src = e.target.result;
                document.getElementById('previewContainer').classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        }
    });

    // Envío del formulario asíncrono
    document.getElementById('paymentForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btnEnviar');
        const form = e.target;

        btn.disabled = true;
        btn.innerHTML = "PROCESANDO...";

        try {
            const response = await fetch('procesar_pago.php', {
                method: 'POST',
                body: new FormData(form)
            });
            const result = await response.json();

            if (result.status === 'success') {
                alert(`¡Éxito! ${result.message}`);
                window.location.href = "user_panel.php";
            } else {
                alert(`Error: ${result.message}`);
                btn.disabled = false;
                btn.innerHTML = "FINALIZAR Y ENVIAR";
            }
        } catch (error) {
            alert("Ocurrió un error en el servidor.");
            btn.disabled = false;
            btn.innerHTML = "FINALIZAR Y ENVIAR";
        }
    });
</script>
</body>
</html>