<?php
session_start();
require __DIR__ . '/config/db.php';

// Protección
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superuser')) {
    header("Location: auth/login.html");
    exit();
}

// Procesar guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_ajustes'])) {
    $updateData = [
        'pagomovil' => $_POST['pagomovil'],
        'transferencia' => $_POST['transferencia'],
        'efectivo' => $_POST['efectivo'],
        'electronico' => $_POST['electronico']
    ];

    try {
        // Actualizar o crear (upsert) la configuración
        $db->configuracion->updateOne(
            ['tipo' => 'datos_pago'],
            ['$set' => $updateData],
            ['upsert' => true]
        );
        $mensaje = "✅ Configuraciones guardadas con éxito.";
    } catch (Exception $e) {
        $error = "❌ Error al guardar configuraciones.";
    }
}

// Obtener datos actuales
$config = $db->configuracion->findOne(['tipo' => 'datos_pago']);
$pagomovil = $config['pagomovil'] ?? "Banco: Banesco (0134)\nCI: V-12345678\nTeléfono: 0414-1234567";
$transferencia = $config['transferencia'] ?? "Banco de Venezuela\nCuenta Corriente\n0102-0000-00-0000000000";
$efectivo = $config['efectivo'] ?? "Entregue el efectivo a la tesorera entre 8:00 AM y 6:00 PM.";
$electronico = $config['electronico'] ?? "Zelle: tesoreria@lanceros.com\nPayPal: paypal.me/lanceros";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustes - Lanceros</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

<nav class="bg-slate-900 text-white p-4 shadow-xl sticky top-0 z-40">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
        <div class="flex items-center gap-3">
            <a href="dashboard.php" class="bg-slate-700 p-2 rounded-lg font-black text-white text-xl flex items-center justify-center w-10 h-10">⚙️</a>
            <span class="font-extrabold tracking-tighter text-lg uppercase">Ajustes Generales</span>
        </div>
        <div class="flex items-center gap-6 text-[10px] font-black uppercase tracking-widest">
            <a href="dashboard.php" class="hover:text-blue-400 transition-colors">Volver al Inicio</a>
        </div>
    </div>
</nav>

<div class="max-w-4xl mx-auto p-6 md:p-10">

    <header class="mb-10 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight">Instrucciones de Pago</h1>
            <p class="text-slate-400 text-xs font-bold uppercase mt-1">Lo que ven los vecinos al reportar un pago</p>
        </div>
        <?php if(isset($mensaje)) echo "<span class='text-xs font-bold text-emerald-500 bg-emerald-50 px-4 py-2 rounded-xl animate-pulse'>$mensaje</span>"; ?>
    </header>

    <form action="" method="POST" class="bg-white rounded-[2.5rem] shadow-xl p-8 border border-slate-100 animate__animated animate__fadeInUp space-y-6">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-slate-50 p-6 rounded-3xl border border-slate-100">
                <label class="flex items-center gap-2 text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">
                    <span class="text-xl">📱</span> Datos Pago Móvil
                </label>
                <textarea name="pagomovil" rows="4" required class="w-full bg-white border border-slate-200 rounded-xl p-4 text-sm text-slate-700 focus:ring-4 focus:ring-blue-100 outline-none resize-none"><?php echo htmlspecialchars($pagomovil); ?></textarea>
            </div>

            <div class="bg-slate-50 p-6 rounded-3xl border border-slate-100">
                <label class="flex items-center gap-2 text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">
                    <span class="text-xl">🏦</span> Cuentas Bancarias
                </label>
                <textarea name="transferencia" rows="4" required class="w-full bg-white border border-slate-200 rounded-xl p-4 text-sm text-slate-700 focus:ring-4 focus:ring-blue-100 outline-none resize-none"><?php echo htmlspecialchars($transferencia); ?></textarea>
            </div>

            <div class="bg-slate-50 p-6 rounded-3xl border border-slate-100">
                <label class="flex items-center gap-2 text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">
                    <span class="text-xl">💻</span> Zelle / PayPal / Binance
                </label>
                <textarea name="electronico" rows="4" required class="w-full bg-white border border-slate-200 rounded-xl p-4 text-sm text-slate-700 focus:ring-4 focus:ring-blue-100 outline-none resize-none"><?php echo htmlspecialchars($electronico); ?></textarea>
            </div>

            <div class="bg-slate-50 p-6 rounded-3xl border border-slate-100">
                <label class="flex items-center gap-2 text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">
                    <span class="text-xl">💵</span> Instrucciones Efectivo
                </label>
                <textarea name="efectivo" rows="4" required class="w-full bg-white border border-slate-200 rounded-xl p-4 text-sm text-slate-700 focus:ring-4 focus:ring-blue-100 outline-none resize-none"><?php echo htmlspecialchars($efectivo); ?></textarea>
            </div>
        </div>

        <div class="pt-6 border-t border-slate-100 flex justify-end">
            <button type="submit" name="guardar_ajustes" class="bg-slate-900 text-white px-8 py-4 rounded-2xl text-xs font-black uppercase hover:bg-blue-600 transition-all shadow-lg transform hover:-translate-y-1">
                Guardar Configuraciones
            </button>
        </div>
    </form>

</div>
</body>
</html>