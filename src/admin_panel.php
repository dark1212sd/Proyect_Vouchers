<?php
require '../config/db.php';

// Consultamos solo los vouchers pendientes de validación
$vouchers_pendientes = $db->vouchers->find(['estatus' => 'pendiente']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Administrativo - Lanceros de la Victoria</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 p-8">
    <div class="max-w-6xl mx-auto">
        <header class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-black text-slate-800">PAGOS POR VALIDAR</h1>
            <span class="bg-blue-600 text-white px-4 py-1 rounded-full text-sm font-bold">Modo Administrador</span>
        </header>

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="p-4 text-xs font-bold text-slate-500 uppercase">Vecino (Cédula)</th>
                        <th class="p-4 text-xs font-bold text-slate-500 uppercase">Referencia</th>
                        <th class="p-4 text-xs font-bold text-slate-500 uppercase">Monto</th>
                        <th class="p-4 text-xs font-bold text-slate-500 uppercase">Soporte</th>
                        <th class="p-4 text-xs font-bold text-slate-500 uppercase text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($vouchers_pendientes as $pago): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="p-4 font-medium text-slate-700"><?php echo $pago['cedula_vecino']; ?></td>
                        <td class="p-4 text-slate-600"><?php echo $pago['referencia_bancaria']; ?></td>
                        <td class="p-4 text-green-600 font-bold">Bs. <?php echo $pago['monto']; ?></td>
                        <td class="p-4">
                            <a href="../uploads/vouchers/<?php echo $pago['soporte_url']; ?>" target="_blank" class="text-blue-500 underline text-sm">Ver Imagen</a>
                        </td>
                        <td class="p-4 flex justify-center gap-2">
                            <form action="validar_pago.php" method="POST">
                                <input type="hidden" name="id" value="<?php echo $pago['_id']; ?>">
                                <button name="accion" value="validado" class="bg-emerald-500 text-white px-3 py-1 rounded-lg text-xs font-bold hover:bg-emerald-600">APROBAR</button>
                                <button name="accion" value="rechazado" class="bg-rose-500 text-white px-3 py-1 rounded-lg text-xs font-bold hover:bg-rose-600">RECHAZAR</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>