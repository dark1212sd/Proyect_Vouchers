<?php
session_start();
// Si no hay sesión o el rol no es admin, redirigir al login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Maestro - Lanceros de la Victoria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

<nav class="bg-slate-900 text-white p-4 shadow-2xl">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
        <div class="flex items-center gap-3">
            <div class="bg-blue-600 p-2 rounded-lg font-black text-white italic text-xl">L</div>
            <span class="font-extrabold tracking-tighter text-lg">LANCEROS <span class="text-blue-500">ADMIN</span></span>
        </div>
        <div class="flex items-center gap-6 text-[10px] font-black uppercase tracking-widest">
            <a href="index.html" class="hover:text-blue-400 transition-colors">Portal Vecino</a>
            <a href="auth/logout.php" class="text-red-400 hover:text-red-300 transition-colors">Cerrar Sesión</a>
            <span class="bg-slate-800 px-3 py-1 rounded-full border border-slate-700 text-emerald-400">En línea</span>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto p-6 md:p-10">

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-10">
        <div class="lg:col-span-4 mb-2">
            <h2 class="text-3xl font-black text-slate-800 tracking-tight">¡Hola, Administrador!</h2>
            <p class="text-slate-400 text-xs font-bold uppercase mt-1">Este es el estado actual de la comunidad hoy.</p>
        </div>

        <div class="bg-white p-6 rounded-[2rem] shadow-xl shadow-slate-200/50 border border-slate-100 flex items-center gap-4">
            <div class="bg-emerald-100 text-emerald-600 p-4 rounded-2xl text-2xl">💰</div>
            <div>
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Recaudado</p>
                <span class="text-xl font-black text-slate-800" id="statRecaudado">0.00</span>
            </div>
        </div>

        <div class="bg-white p-6 rounded-[2rem] shadow-xl shadow-slate-200/50 border border-slate-100 flex items-center gap-4">
            <div class="bg-amber-100 text-amber-600 p-4 rounded-2xl text-2xl">⏳</div>
            <div>
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Por Validar</p>
                <span class="text-xl font-black text-slate-800" id="statPendiente">0.00</span>
            </div>
        </div>

        <div class="bg-white p-6 rounded-[2rem] shadow-xl shadow-slate-200/50 border border-slate-100 flex items-center gap-4">
            <div class="bg-blue-100 text-blue-600 p-4 rounded-2xl text-2xl">📊</div>
            <div>
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Registros</p>
                <span class="text-xl font-black text-slate-800" id="statTotal">0</span>
            </div>
        </div>

        <div class="bg-blue-600 p-6 rounded-[2rem] shadow-xl shadow-blue-200/50 flex flex-col justify-center items-center text-white text-center">
            <p class="text-[9px] font-black uppercase tracking-widest mb-1 opacity-80">Meta Mensual</p>
            <span class="text-xl font-black">75%</span>
            <div class="w-full bg-blue-400 h-1.5 rounded-full mt-2 overflow-hidden">
                <div class="bg-white h-full w-[75%]"></div>
            </div>
        </div>
    </div>

    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-6">Herramientas del Sistema</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-12">
        <a href="index.html" class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all group text-center">
            <div class="text-3xl mb-3 group-hover:scale-110 transition-transform">📝</div>
            <p class="text-[10px] font-black text-slate-700 uppercase">Nuevo Pago</p>
        </a>
        <a href="#" class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all group text-center opacity-50 cursor-not-allowed">
            <div class="text-3xl mb-3 group-hover:scale-110 transition-transform">👥</div>
            <p class="text-[10px] font-black text-slate-700 uppercase">Vecinos</p>
        </a>
        <a href="#" class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all group text-center opacity-50 cursor-not-allowed">
            <div class="text-3xl mb-3 group-hover:scale-110 transition-transform">📁</div>
            <p class="text-[10px] font-black text-slate-700 uppercase">Reportes</p>
        </a>
        <a href="#" class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all group text-center opacity-50 cursor-not-allowed">
            <div class="text-3xl mb-3 group-hover:scale-110 transition-transform">⚙️</div>
            <p class="text-[10px] font-black text-slate-700 uppercase">Ajustes</p>
        </a>
    </div>

    <div class="flex justify-between items-end mb-6">
        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]">Últimas Declaraciones Recibidas</h3>
        <div class="w-64">
            <input type="text" id="busquedaCedula" placeholder="Filtrar..." class="w-full bg-white border border-slate-200 px-4 py-2 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-blue-500">
        </div>
    </div>

    <div class="bg-white rounded-[2.5rem] shadow-xl overflow-hidden border border-slate-100">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-100">
            <tr>
                <th class="p-6 text-[9px] font-black text-slate-400 uppercase">Cédula</th>
                <th class="p-6 text-[9px] font-black text-slate-400 uppercase">Referencia</th>
                <th class="p-6 text-[9px] font-black text-slate-400 uppercase text-center">Monto</th>
                <th class="p-6 text-[9px] font-black text-slate-400 uppercase text-center">Estado</th>
                <th class="p-6 text-[9px] font-black text-slate-400 uppercase text-center">Acciones</th>
            </tr>
            </thead>
            <tbody id="tablaPagos" class="divide-y divide-slate-50 text-sm"></tbody>
        </table>
    </div>
</div>

<script src="js/dashboard.js"></script>
</body>
</html>