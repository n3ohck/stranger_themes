<?php

namespace Tests\Feature;

use App\Actions\Tienda\ApartarCompraAction;
use App\Actions\Tienda\ConfirmarCompraAction;
use App\Models\Corte;
use App\Models\Descuento;
use App\Models\Egreso;
use App\Models\Empleado;
use App\Models\Producto;
use App\Models\Reserva;
use App\Models\Sucursal;
use App\Models\User;
use App\Scopes\SucursalFilterScope;
use App\Support\Roles;
use App\Support\SucursalActiva;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Operación completa de dos sucursales, punta a punta: POS, tienda en línea y
 * panel, con un usuario por sucursal.
 *
 * OJO: phpunit.xml no fija sqlite, así que esto corre contra la base de
 * desarrollo. DatabaseTransactions envuelve todo en una transacción que se
 * revierte al terminar: no queda nada escrito. Nunca cambies esto por
 * RefreshDatabase, que borraría la base.
 *
 * Las sucursales, productos, usuarios y empleados se crean aquí, así que los
 * números esperados son exactos y no dependen de lo que ya haya en la base.
 */
class DosSucursalesTest extends TestCase
{
    use DatabaseTransactions;

    private const DIA = '2026-12-03';
    private const DIA_SIGUIENTE = '2026-12-04';
    private const CLAVE = 'prueba-dos-sucursales';

    private Sucursal $a;
    private Sucursal $b;
    private Producto $tourA;
    private Producto $articuloA;
    private Producto $tourB;
    private User $cajeroA;
    private User $cajeroB;
    private User $admin;
    private User $consultaB;
    private Empleado $empleadoA;
    private Empleado $empleadoB;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Mail::fake();
        Storage::fake('pagos');

        $this->viajarA('09:00');
        $this->crearEscenario();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dos_sucursales_operan_y_cuadran_por_separado(): void
    {
        // ── 09:00 Compras en línea (antes de abrir caja) ──────────────────────
        $this->assertCatalogoDeLaTienda();

        $this->comprarEnLinea($this->a, $this->tourA, 2, '12:00', 'pi_test_a_1'); // cuenta en el corte de A
        $this->comprarEnLinea($this->a, $this->tourA, 1, '18:00', 'pi_test_a_2', 'DSCA10'); // después del corte; 200 − 10 % = 180
        $this->comprarEnLinea($this->b, $this->tourB, 3, '12:00', 'pi_test_b_1');
        $this->comprarEnLinea($this->b, $this->tourB, 2, '12:00', 'pi_test_b_2', null, self::DIA_SIGUIENTE); // al día siguiente

        // ── 10:00 Abren las dos cajas y venden ───────────────────────────────
        $this->viajarA('10:00');
        $tokenA = $this->login($this->cajeroA);
        $tokenB = $this->login($this->cajeroB);

        $this->pos($tokenA, 'POST', 'caja/apertura', ['monto_apertura' => 1000])->assertStatus(201);
        $this->pos($tokenB, 'POST', 'caja/apertura', ['monto_apertura' => 500])->assertStatus(201);

        // Sucursal A
        $this->vender($tokenA, $this->tourA, 3, [['tipo' => 'efectivo', 'monto' => 700]], '14:00');      // 600, cambio 100
        $this->vender($tokenA, $this->articuloA, 2, [['tipo' => 'tarjeta', 'monto' => 100]]);             // 100
        $this->vender($tokenA, $this->tourA, 2, [['tipo' => 'transferencia', 'monto' => 360]], '15:00', 'DSCA10'); // 400 − 10 %
        $cancelada = $this->vender($tokenA, $this->articuloA, 1, [['tipo' => 'efectivo', 'monto' => 50]]);
        $this->pos($tokenA, 'POST', "ventas/{$cancelada}/cancelar", ['motivo' => 'Prueba de cancelación'])->assertOk();

        $this->assertSame(8, (int) $this->articuloA->fresh()->existencia, 'La cancelación devuelve la existencia.');

        // Sucursal B
        $this->vender($tokenB, $this->tourB, 2, [['tipo' => 'transferencia', 'monto' => 300]], '14:00');  // 300
        $this->vender($tokenB, $this->tourB, 1, [['tipo' => 'efectivo', 'monto' => 200]], '16:00');       // 150, cambio 50

        // ── Aislamiento entre sucursales en el POS ───────────────────────────
        // El código de descuento de A no sirve en B.
        $this->pos($tokenB, 'POST', 'ventas', $this->cuerpoVenta($this->tourB, 1, [['tipo' => 'efectivo', 'monto' => 150]], '17:00', 'DSCA10'))
            ->assertStatus(422);
        // B no puede cancelar una venta de A.
        $ventaDeA = $this->ventasDe($this->a)->first();
        $estado = $this->pos($tokenB, 'POST', "ventas/{$ventaDeA->id}/cancelar", ['motivo' => 'No es mía'])->status();
        $this->assertContains($estado, [403, 404], 'B no debe poder cancelar una venta de A.');
        $this->assertSame('activo', $ventaDeA->fresh()->estatus);
        // B no ve los empleados de A ni les puede pagar.
        $this->assertSame([$this->empleadoB->id], collect($this->pos($tokenB, 'GET', 'empleados')->json('empleados'))->pluck('id')->all());
        $this->pos($tokenB, 'POST', 'empleados/pagos', ['empleado_id' => $this->empleadoA->id, 'monto' => 10])->assertStatus(422);
        // La agenda de B solo trae reservaciones de B.
        $agendaB = $this->pos($tokenB, 'GET', 'reservas/dia?fecha=' . self::DIA)->assertOk()->json();
        $this->assertStringNotContainsString('Tour A', json_encode($agendaB));

        // ── Egresos y pagos a empleados ──────────────────────────────────────
        $this->pos($tokenA, 'POST', 'egresos', ['monto' => 150, 'descripcion' => 'Garrafones'])->assertStatus(201);
        $this->pos($tokenB, 'POST', 'egresos', ['monto' => 80, 'descripcion' => 'Papelería'])->assertStatus(201);
        $this->pagarEmpleado($tokenA, $this->empleadoA, 500);
        $this->pagarEmpleado($tokenB, $this->empleadoB, 300);

        // ── /comprar ve los lugares de cada sucursal por separado ────────────
        $this->viajarA('13:00');
        $tokenA = $this->login($this->cajeroA);
        $tokenB = $this->login($this->cajeroB);
        $this->assertLugares($this->a, $this->tourA, '14:00', 10 - 3);
        $this->assertLugares($this->a, $this->tourA, '15:00', 10 - 2);
        $this->assertLugares($this->b, $this->tourB, '14:00', 6 - 2);
        $horasB = collect($this->disponibilidad($this->b, $this->tourB)['horarios'])->pluck('inicio');
        $this->assertNotContains('14:30', $horasB, 'B sale cada hora: no hay salida a las 14:30.');

        // ── 13:00 Precorte y corte de cada sucursal ──────────────────────────
        $precorteA = $this->pos($tokenA, 'GET', 'caja/precorte')->assertOk()->json('precorte');
        $this->assertPrecorte($precorteA, [
            'cantidad' => 3, 'canceladas' => 1, 'total' => 1060.0,
            'efectivo' => 600.0, 'tarjeta' => 100.0, 'transferencia' => 360.0, 'online' => 400.0,
            'egresos' => 150.0, 'pagos_empleados' => 500.0,
            'efectivo_esperado' => 950.0, 'ingresos' => 1460.0, 'ganancia' => 810.0,
        ]);

        $precorteB = $this->pos($tokenB, 'GET', 'caja/precorte')->assertOk()->json('precorte');
        $this->assertPrecorte($precorteB, [
            'cantidad' => 2, 'canceladas' => 0, 'total' => 450.0,
            'efectivo' => 150.0, 'tarjeta' => 0.0, 'transferencia' => 300.0, 'online' => 450.0,
            'egresos' => 80.0, 'pagos_empleados' => 300.0,
            'efectivo_esperado' => 270.0, 'ingresos' => 900.0, 'ganancia' => 520.0,
        ]);

        $this->pos($tokenA, 'POST', 'caja/corte', ['efectivo_contado' => 950])->assertStatus(201);
        $this->pos($tokenB, 'POST', 'caja/corte', ['efectivo_contado' => 270])->assertStatus(201);

        $corteA = Corte::withoutGlobalScope(SucursalFilterScope::class)->where('sucursal_id', $this->a->id)->sole();
        $corteB = Corte::withoutGlobalScope(SucursalFilterScope::class)->where('sucursal_id', $this->b->id)->sole();
        $this->assertEquals([1060.0, 400.0, 950.0], [(float) $corteA->total, (float) $corteA->total_online, (float) $corteA->total_caja]);
        $this->assertEquals([450.0, 450.0, 270.0], [(float) $corteB->total, (float) $corteB->total_online, (float) $corteB->total_caja]);

        // ── Panel: cada sucursal con sus números ─────────────────────────────
        $this->assertPanel($this->a, [
            'cantidad_ventas' => 6,               // 4 de mostrador (1 cancelada) + 2 en línea con visita ese día
            'cantidad_reservaciones' => 8,        // personas: 3 + 2 + 2 + 1
            'total_ventas' => '1,640.00',         // 600 + 100 + 360 + 400 + 180 (lo cobrado, con su descuento una sola vez)
            'total_egresos' => '150.00',
            'total_salarios' => '500.00',
            'utilidad_operativa' => '990.00',
        ], ['Tour A' => 8, 'Artículo A' => 2], 1);

        $this->assertPanel($this->b, [
            'cantidad_ventas' => 3,
            'cantidad_reservaciones' => 6,        // 2 + 1 + 3
            'total_ventas' => '900.00',
            'total_egresos' => '80.00',
            'total_salarios' => '300.00',
            'utilidad_operativa' => '520.00',
        ], ['Tour B' => 6], 1);

        // Un día con ventas en línea y ninguna de mostrador: antes respondía 500.
        $siguiente = $this->comoPanel($this->admin, $this->b)
            ->getJson('/webapi/ventas/resumen?' . http_build_query(['dates' => [self::DIA_SIGUIENTE, self::DIA_SIGUIENTE]]))
            ->assertOk();
        $this->assertEquals([1, '300.00', 2], [$siguiente->json('cantidad_ventas'), $siguiente->json('total_ventas'), $siguiente->json('cantidad_reservaciones')]);

        // Un usuario de consulta de B ve B aunque la sesión diga A: solo
        // administración y gerencia pueden cambiar de sucursal.
        $this->comoPanel($this->consultaB, $this->a)
            ->getJson('/webapi/ventas/resumen?' . $this->rangoDelDia())
            ->assertOk()
            ->assertJsonPath('cantidad_ventas', 3);

        // Lo que se captura en el panel va a la sucursal que se está viendo.
        $this->comoPanel($this->admin, $this->b)->get('/admin/dashboard')->assertOk();
        $egreso = Egreso::create([
            'user_id' => $this->admin->id,
            'monto' => 25,
            'descripcion' => 'Capturado en el panel viendo B',
            'estatus' => 'activo',
            'tipo_pago' => 'efectivo',
            'fecha_pago' => Carbon::now(),
        ]);
        $this->assertSame($this->b->id, (int) $egreso->sucursal_id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Escenario
    // ─────────────────────────────────────────────────────────────────────────

    private function crearEscenario(): void
    {
        $horarios = fn (int $intervalo) => collect(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'])
            ->map(fn ($dia) => ['dia' => $dia, 'hora_entrada' => '10:00', 'hora_salida' => '22:00', 'intervalo_minutos' => (string) $intervalo])
            ->all();

        $this->a = Sucursal::forceCreate(['razon_social' => 'Prueba Sucursal A', 'prefijo_folio' => 'PA', 'horarios' => $horarios(30)]);
        $this->b = Sucursal::forceCreate(['razon_social' => 'Prueba Sucursal B', 'prefijo_folio' => 'PB', 'horarios' => $horarios(60)]);

        $this->tourA = $this->producto($this->a, 'Tour A', 'tour', 200, ['capacidad' => 10, 'duracion_minutos' => 25, 'visible_en_tienda' => true]);
        $this->articuloA = $this->producto($this->a, 'Artículo A', 'articulo', 50, ['existencia' => 10]);
        $this->tourB = $this->producto($this->b, 'Tour B', 'tour', 150, ['capacidad' => 6, 'duracion_minutos' => 40, 'visible_en_tienda' => true]);

        Descuento::withoutGlobalScope(SucursalFilterScope::class)->forceCreate([
            'codigo' => 'DSCA10', 'porcentaje' => 10, 'sucursal_id' => $this->a->id, 'producto_tipo' => 'tour', 'estatus' => 'activo',
        ]);

        $this->cajeroA = $this->usuario('cajero.prueba.a', $this->a, Roles::APP_USER);
        $this->cajeroB = $this->usuario('cajero.prueba.b', $this->b, Roles::APP_USER);
        $this->consultaB = $this->usuario('consulta.prueba.b', $this->b, Roles::CONSULTA);
        $this->admin = $this->usuario('admin.prueba', $this->a, Roles::ADMINISTRADOR);
        $this->admin->givePermissionTo(Permission::all());

        $this->empleadoA = Empleado::forceCreate(['sucursal_id' => $this->a->id, 'nombres' => 'Ana', 'apellidos' => 'Prueba', 'estatus' => 'activo']);
        $this->empleadoB = Empleado::forceCreate(['sucursal_id' => $this->b->id, 'nombres' => 'Beto', 'apellidos' => 'Prueba', 'estatus' => 'activo']);
    }

    private function producto(Sucursal $sucursal, string $nombre, string $tipo, float $precio, array $extra = []): Producto
    {
        return Producto::withoutGlobalScope(SucursalFilterScope::class)->forceCreate($extra + [
            'sucursal_id' => $sucursal->id,
            'codigo' => self::CLAVE . '-' . $nombre,
            'descripcion' => $nombre,
            'tipo' => $tipo,
            'precio' => $precio,
            'existencia' => 0,
            'visible_en_tienda' => false,
        ]);
    }

    private function usuario(string $cuenta, Sucursal $sucursal, string $rol): User
    {
        $user = User::forceCreate([
            'user' => $cuenta,
            'email' => $cuenta . '@example.test',
            'name' => $cuenta,
            'password' => Hash::make('prueba-secreta'),
            'sucursal_id' => $sucursal->id,
        ]);
        $user->assignRole($rol);

        return $user;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ayudas
    // ─────────────────────────────────────────────────────────────────────────

    private function viajarA(string $hora): void
    {
        Carbon::setTestNow(Carbon::parse(self::DIA . ' ' . $hora));
    }

    /**
     * En una prueba la aplicación vive entre peticiones y el guard conserva al
     * último usuario: se limpia al cambiar de actor. La librería de JWT guarda su
     * propia referencia al guard, así que también se reinicia; si no, autentica
     * sobre una instancia vieja y el middleware del POS no ve al usuario. En
     * producción cada petición arranca de cero y esto no pasa.
     */
    private function olvidarSesion(): void
    {
        $this->app['auth']->forgetGuards();

        // 'crud' es el panel de Backpack: también es único por aplicación y acumularía
        // los filtros de un listado con los del siguiente.
        foreach (['auth.driver', 'tymon.jwt.provider.auth', 'tymon.jwt.auth', 'tymon.jwt', 'crud'] as $instancia) {
            $this->app->forgetInstance($instancia);
        }

        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
    }

    private function login(User $user): string
    {
        $this->olvidarSesion();

        return $this->postJson('/pos-api/login', ['account' => $user->user, 'password' => 'prueba-secreta'])
            ->assertOk()
            ->json('token');
    }

    private function pos(string $token, string $metodo, string $ruta, array $datos = [])
    {
        $this->olvidarSesion();

        return $this->withHeaders(['Authorization' => 'Bearer ' . $token])->json($metodo, '/pos-api/' . $ruta, $datos);
    }

    private function cuerpoVenta(Producto $producto, int $cantidad, array $pagos, ?string $hora = null, ?string $codigo = null): array
    {
        $item = ['producto_id' => $producto->id, 'cantidad' => $cantidad, 'cliente' => 'Cliente ' . $producto->descripcion, 'codigo_descuento' => $codigo];

        if ($hora) {
            $item['reservaciones'] = [[
                'producto_id' => $producto->id, 'fecha' => self::DIA, 'hora' => $hora, 'personas' => $cantidad, 'nombre' => 'Cliente',
            ]];
        }

        return ['items' => [$item], 'pagos' => $pagos];
    }

    private function vender(string $token, Producto $producto, int $cantidad, array $pagos, ?string $hora = null, ?string $codigo = null): int
    {
        return $this->pos($token, 'POST', 'ventas', $this->cuerpoVenta($producto, $cantidad, $pagos, $hora, $codigo))
            ->assertStatus(201)
            ->json('venta.id');
    }

    private function pagarEmpleado(string $token, Empleado $empleado, float $monto): void
    {
        $pago = $this->pos($token, 'POST', 'empleados/pagos', ['empleado_id' => $empleado->id, 'monto' => $monto])
            ->assertStatus(201)
            ->json('pago.id');

        $this->olvidarSesion();
        $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'])
            ->post("/pos-api/empleados/pagos/{$pago}/comprobante", ['imagen' => UploadedFile::fake()->image('ticket.jpg')])
            ->assertOk();
    }

    private function comprarEnLinea(Sucursal $sucursal, Producto $tour, int $personas, string $hora, string $paymentIntent, ?string $codigo = null, string $fecha = self::DIA): void
    {
        $this->olvidarSesion();

        $compra = app(ApartarCompraAction::class)->ejecutar([
            'sucursal_id' => $sucursal->id,
            'producto_id' => $tour->id,
            'personas' => $personas,
            'fecha' => $fecha,
            'hora' => $hora,
            'codigo_descuento' => $codigo,
            'nombre' => 'Cliente Web',
            'email' => 'cliente.web@example.test',
        ]);

        app(ConfirmarCompraAction::class)->ejecutar($compra, $paymentIntent);
    }

    private function disponibilidad(Sucursal $sucursal, Producto $producto, int $personas = 1): array
    {
        $this->olvidarSesion();

        return $this->getJson('/tienda-api/disponibilidad?' . http_build_query([
            'sucursal_id' => $sucursal->id, 'producto_id' => $producto->id, 'fecha' => self::DIA, 'personas' => $personas,
        ]))->assertOk()->json();
    }

    private function assertLugares(Sucursal $sucursal, Producto $producto, string $hora, int $esperados): void
    {
        $horario = collect($this->disponibilidad($sucursal, $producto)['horarios'])->firstWhere('inicio', $hora);
        $this->assertNotNull($horario, "{$producto->descripcion} debería ofrecer las {$hora}.");
        $this->assertSame($esperados, $horario['lugares'], "Lugares de {$producto->descripcion} a las {$hora}.");
    }

    private function assertCatalogoDeLaTienda(): void
    {
        $this->olvidarSesion();
        $productos = collect($this->getJson('/tienda-api/catalogo')->assertOk()->json('productos'));

        $this->assertSame($this->a->id, $productos->firstWhere('id', $this->tourA->id)['sucursal_id'] ?? null);
        $this->assertSame($this->b->id, $productos->firstWhere('id', $this->tourB->id)['sucursal_id'] ?? null);
        $this->assertNull($productos->firstWhere('id', $this->articuloA->id), 'Un artículo no se vende en línea.');

        $this->get('/comprar')->assertOk();
    }

    private function assertPrecorte(array $precorte, array $esperado): void
    {
        $real = [
            'cantidad' => $precorte['ventas']['cantidad'],
            'canceladas' => $precorte['ventas']['canceladas'],
            'total' => (float) $precorte['ventas']['total'],
            'efectivo' => (float) $precorte['ventas']['efectivo'],
            'tarjeta' => (float) $precorte['ventas']['tarjeta'],
            'transferencia' => (float) $precorte['ventas']['transferencia'],
            'online' => (float) $precorte['ventas']['online'],
            'egresos' => (float) $precorte['salidas']['egresos'],
            'pagos_empleados' => (float) $precorte['salidas']['pagos_empleados'],
            'efectivo_esperado' => (float) $precorte['caja']['efectivo_esperado'],
            'ingresos' => (float) $precorte['resultado']['ingresos'],
            'ganancia' => (float) $precorte['resultado']['ganancia'],
        ];

        $this->assertEquals($esperado, $real);
    }

    private function comoPanel(User $user, Sucursal $elegida)
    {
        $this->olvidarSesion();

        return $this->actingAs($user, 'web')->withSession([SucursalActiva::LLAVE_SESION => $elegida->id]);
    }

    private function rangoDelDia(): string
    {
        return http_build_query(['dates' => [self::DIA, self::DIA]]);
    }

    private function assertPanel(Sucursal $sucursal, array $resumen, array $productos, int $cortes): void
    {
        $respuesta = $this->comoPanel($this->admin, $sucursal)
            ->getJson('/webapi/ventas/resumen?' . $this->rangoDelDia())
            ->assertOk();

        foreach ($resumen as $llave => $valor) {
            $this->assertEquals($valor, $respuesta->json($llave), "{$sucursal->razon_social}: {$llave}");
        }
        $this->assertSame([$sucursal->razon_social], collect($respuesta->json('ventas'))->pluck('sucursal')->unique()->values()->all());

        $porProducto = collect($this->comoPanel($this->admin, $sucursal)
            ->getJson('/webapi/ventas/resumen/productos?' . $this->rangoDelDia())
            ->assertOk()
            ->json('productos'))
            ->mapWithKeys(fn ($p) => [$p['producto'] => (int) $p['cantidad']])
            ->all();
        $this->assertEquals($productos, $porProducto, "{$sucursal->razon_social}: resumen de productos");

        // Reporte de pagos: mismos egresos y pagos a empleados que el resumen.
        $pagos = $this->comoPanel($this->admin, $sucursal)
            ->getJson('/webapi/reports/payments?' . http_build_query(['dates' => [self::DIA, self::DIA], 'origins' => ['egreso', 'pagoEmpleado']]))
            ->assertOk()
            ->json('totals');
        $this->assertEquals(
            [(float) str_replace(',', '', $resumen['total_egresos']), (float) str_replace(',', '', $resumen['total_salarios'])],
            [(float) $pagos['egreso'], (float) $pagos['pagoEmpleado']],
            "{$sucursal->razon_social}: reporte de pagos"
        );

        // Listados del panel: solo los registros de la sucursal que se está viendo.
        foreach (['corte' => $cortes, 'egreso' => 1, 'empleado' => 1] as $ruta => $esperados) {
            $total = $this->comoPanel($this->admin, $sucursal)
                ->postJson("/admin/{$ruta}/search", ['draw' => 1, 'start' => 0, 'length' => 50])
                ->assertOk()
                ->json('recordsTotal');
            $this->assertEquals($esperados, $total, "{$sucursal->razon_social}: listado de {$ruta}");
        }
    }

    private function ventasDe(Sucursal $sucursal)
    {
        return \App\Models\Venta::withoutGlobalScope(SucursalFilterScope::class)->where('sucursal_id', $sucursal->id)->get();
    }
}
