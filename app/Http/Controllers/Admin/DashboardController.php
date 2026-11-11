<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogNotificacion;
use App\Support\SucursalActiva;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $firstDayOfMonth = $now->copy()->startOfMonth();
        $lastDayOfMonth = $now->copy()->endOfMonth();

        $disputas = LogNotificacion::query()
            ->where('sucursal_id', SucursalActiva::id())
            ->whereBetween('created_at', [$firstDayOfMonth, $lastDayOfMonth])
            ->where('motivo', 'disputa')
            ->count();

        return Inertia::render('Dashboard/index', [
            'esadmin' => backpack_user()->hasRole(\App\Support\Roles::ADMINISTRADOR),
            'disputas' => $disputas,
        ]);
    }
}
