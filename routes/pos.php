<?php

use App\Http\Controllers\Pos\AuthController;
use App\Http\Controllers\Pos\CajaController;
use App\Http\Controllers\Pos\CatalogoController;
use App\Http\Controllers\Pos\MovimientoController;
use App\Http\Controllers\Pos\ReservaController;
use App\Http\Controllers\Pos\SesionController;
use App\Http\Controllers\Pos\VentaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API del punto de venta
|--------------------------------------------------------------------------
|
| Prefijo /pos-api, aislado del panel de Backpack y del API anterior.
| Autenticación por JWT; jwt.verify además deja al usuario disponible en el
| guard por defecto, que es de lo que dependen el scope de sucursal y los
| controladores.
|
*/

Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('login');

Route::middleware(['jwt.verify', 'pos.user'])->group(function () {
    Route::get('session', [SesionController::class, 'show'])->name('session');
    Route::get('catalogo', [CatalogoController::class, 'index'])->name('catalogo');

    // Caja
    Route::post('caja/apertura', [CajaController::class, 'abrir'])->name('caja.abrir');
    Route::get('caja/precorte', [CajaController::class, 'precorte'])->name('caja.precorte');
    Route::post('caja/corte', [CajaController::class, 'cerrar'])->name('caja.cerrar');
    Route::get('caja/corte/{corte}/ticket', [CajaController::class, 'ticketCorte'])->name('caja.corte.ticket');

    // Ventas
    Route::get('ventas/turno', [VentaController::class, 'delTurno'])->name('ventas.turno');
    Route::post('ventas', [VentaController::class, 'store'])->name('ventas.store');
    Route::post('ventas/{venta}/cancelar', [VentaController::class, 'cancelar'])->name('ventas.cancelar');
    Route::get('ventas/{venta}/ticket', [VentaController::class, 'ticket'])->name('ventas.ticket');

    // Reservaciones
    Route::get('reservas', [ReservaController::class, 'index'])->name('reservas');

    // Salidas de dinero del turno
    Route::get('movimientos', [MovimientoController::class, 'index'])->name('movimientos');
    Route::get('empleados', [MovimientoController::class, 'empleados'])->name('empleados');
    Route::post('egresos', [MovimientoController::class, 'registrarEgreso'])->name('egresos.store');
    Route::post('empleados/pagos', [MovimientoController::class, 'registrarPagoEmpleado'])->name('empleados.pagos.store');
});
