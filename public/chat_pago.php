<?php
session_start();
require __DIR__ . '/config/db.php';

if (!isset($_SESSION['role'])) {
    header("Location: auth/login.html");
    exit();
}

if (!isset($_GET['id'])) die("ID de pago no especificado.");
$voucher_id = new MongoDB\BSON\ObjectId($_GET['id']);
$pago = $db->vouchers->findOne(['_id' => $voucher_id]);
if (!$pago) die("El pago no existe.");

$link_volver = ($_SESSION['role'] === 'vecino') ? 'user_panel.php' : 'admin_validaciones.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soporte del Pago - Lanceros</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        #caja-chat::-webkit-scrollbar { width: 6px; }
        #caja-chat::-webkit-scrollbar-track { background: transparent; }
        #caja-chat::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-3xl bg-white rounded-[2.5rem] shadow-2xl border border-slate-100 overflow-hidden flex flex-col h-[85vh]">

    <div class="bg-slate-900 p-6 flex justify-between items-center text-white shrink-0">
        <div class="flex items-center gap-4">
            <a href="<?php echo $link_volver; ?>" class="text-slate-400 hover:text-white transition-colors">← Volver</a>
            <div>
                <h2 class="font-black text-lg flex items-center gap-2">
                    Ticket de Soporte
                    <span id="estado-conexion" class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse" title="Conectado en tiempo real"></span>
                </h2>
                <p class="text-[10px] text-slate-400 font-mono">Ref: <?php echo htmlspecialchars($pago['referencia_bancaria']); ?></p>
            </div>
        </div>
        <div class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest <?php echo ($pago['estatus'] === 'validado') ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'; ?>">
            <?php echo htmlspecialchars($pago['estatus']); ?>
        </div>
    </div>

    <div id="caja-chat" class="flex-1 overflow-y-auto p-6 bg-slate-50 flex flex-col gap-4">
        <div class="flex-1 flex flex-col items-center justify-center opacity-50">
            <span class="text-2xl animate-spin">⏳</span>
            <p class="text-xs font-bold text-slate-500 mt-2">Sincronizando chat...</p>
        </div>
    </div>

    <div class="p-4 bg-white border-t border-slate-100 shrink-0">
        <form id="formChat" class="flex gap-2">
            <input type="hidden" name="voucher_id" value="<?php echo (string)$pago['_id']; ?>">
            <input type="text" name="mensaje" id="inputMensaje" required autocomplete="off" placeholder="Escribe un mensaje aquí..."
                   class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
            <button type="submit" id="btnEnviar" class="bg-blue-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-blue-700 transition-all shadow-md flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                </svg>
            </button>
        </form>
    </div>
</div>

<script>
    const idPago = '<?php echo (string)$pago['_id']; ?>';
    const cajaChat = document.getElementById('caja-chat');
    let cantidadMensajesActual = -1; // Para saber si llegaron nuevos

    // Función que pide los mensajes al servidor
    async function cargarMensajes() {
        try {
            const req = await fetch('api_mensajes.php?id=' + idPago);
            const data = await req.json();

            if (data.status === 'success') {
                // Solo redibuja el chat si hay mensajes nuevos
                if (data.mensajes.length !== cantidadMensajesActual) {
                    dibujarChat(data.mensajes, data.usuario_actual);
                    cantidadMensajesActual = data.mensajes.length;
                }
            }
        } catch (e) {
            console.error("Error sincronizando chat.");
        }
    }

    // Función que inyecta HTML
    function dibujarChat(mensajes, usuario_actual) {
        cajaChat.innerHTML = `
            <div class="flex justify-center mb-4">
                <span class="bg-slate-200 text-slate-500 text-[10px] font-bold px-4 py-1.5 rounded-full">
                    Conexión segura. Chat encriptado.
                </span>
            </div>
        `;

        if (mensajes.length === 0) {
            cajaChat.innerHTML += `
                <div class="flex-1 flex flex-col items-center justify-center opacity-50">
                    <span class="text-4xl mb-2">💬</span>
                    <p class="text-xs font-bold text-slate-500">Aún no hay mensajes. Escribe algo abajo.</p>
                </div>
            `;
            return;
        }

        mensajes.forEach(msg => {
            const soyYo = (msg.remitente === usuario_actual);
            const esAdmin = (msg.rol === 'admin' || msg.rol === 'superuser');
            const titulo = soyYo ? 'Tú' : (esAdmin ? '🛡️ Administración' : '👤 Vecino');

            if (soyYo) {
                cajaChat.innerHTML += `
                    <div class="flex flex-col items-end animate__animated animate__fadeInUp animate__faster">
                        <span class="text-[9px] text-slate-400 font-bold mb-1">${titulo} • ${msg.fecha}</span>
                        <div class="bg-blue-600 text-white p-4 rounded-2xl rounded-tr-sm max-w-[80%] shadow-md">
                            <p class="text-sm font-medium">${msg.texto}</p>
                        </div>
                    </div>
                `;
            } else {
                cajaChat.innerHTML += `
                    <div class="flex flex-col items-start animate__animated animate__fadeInUp animate__faster">
                        <span class="text-[9px] text-slate-400 font-bold mb-1">${titulo} • ${msg.fecha}</span>
                        <div class="bg-white border border-slate-200 text-slate-700 p-4 rounded-2xl rounded-tl-sm max-w-[80%] shadow-sm">
                            <p class="text-sm font-medium">${msg.texto}</p>
                        </div>
                    </div>
                `;
            }
        });

        // Bajar el scroll automáticamente al último mensaje
        cajaChat.scrollTop = cajaChat.scrollHeight;
    }

    // Enviar mensaje sin recargar
    document.getElementById('formChat').addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = document.getElementById('inputMensaje');
        const btn = document.getElementById('btnEnviar');
        const formData = new FormData(e.target);

        input.value = ''; // Limpiar de inmediato
        btn.disabled = true;

        try {
            const req = await fetch('enviar_mensaje.php', { method: 'POST', body: formData });
            await req.json();
            // Aceleramos la carga del nuevo mensaje
            cargarMensajes();
        } finally {
            btn.disabled = false;
            input.focus();
        }
    });

    // ¡LA MAGIA DEL TIEMPO REAL (SONDEO)!
    // Llama a la API cada 2.5 segundos buscando mensajes nuevos
    setInterval(cargarMensajes, 2500);

    // Carga inicial
    cargarMensajes();
</script>
</body>
</html>