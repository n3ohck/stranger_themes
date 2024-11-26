<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $sucursales = Sucursal::query()
            ->select(['id','razon_social as nombre'])
            ->orderBy('nombre')
            ->get();
        return Inertia::render('Dashboard/index', [
            'sucursales' => $sucursales,
            'esadmin' => backpack_user()->hasRole('Administrador')
        ]);
    }
}
