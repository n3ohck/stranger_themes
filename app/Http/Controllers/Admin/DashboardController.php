<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogNotificacion;
use App\Models\Sucursal;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $sucursales = Sucursal::query()
            ->select(['id','razon_social as nombre'])
            ->orderBy('nombre')
            ->get();

        $now = Carbon::now();
        $firstDayOfMonth = $now->copy()->startOfMonth();
        $lastDayOfMonth = $now->copy()->endOfMonth();

        $disputas = LogNotificacion::query()
            ->where('sucursal_id', backpack_user()->sucursal_id)
            ->whereBetween('created_at', [$firstDayOfMonth, $lastDayOfMonth])
            ->where('motivo', 'disputa')
            ->count();

        return Inertia::render('Dashboard/index', [
            'sucursales' => $sucursales,
            'esadmin' => backpack_user()->hasRole(\App\Support\Roles::ADMINISTRADOR),
            'disputas' => $disputas,
        ]);
    }
}
