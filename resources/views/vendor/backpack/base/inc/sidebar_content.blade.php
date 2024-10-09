<li class='nav-item'><a class='nav-link' href='{{ backpack_url('producto') }}'><i class='nav-icon la la-list'></i> Productos</a></li>
<li class='nav-item'><a class='nav-link' href='{{ backpack_url('descuento') }}'><i class='nav-icon la la-list'></i> Descuentos</a></li>
<li class='nav-item'><a class='nav-link' href='{{ backpack_url('venta') }}'><i class='nav-icon la la-list'></i> Ventas</a></li>
<li class='nav-item'><a class='nav-link' href='{{ backpack_url('reserva') }}'><i class='nav-icon la la-calendar'></i> Reservas</a></li>
<li class='nav-item'><a class='nav-link' href='{{ backpack_url('pago-carta') }}'><i class='nav-icon la la-envelope'></i> Cartas de pago</a></li>
<li class='nav-item'><a class='nav-link' href='{{ backpack_url('egreso') }}'><i class='nav-icon la la-exchange'></i> Egresos</a></li>
<li class="nav-item nav-dropdown">
    <a class="nav-link nav-dropdown-toggle" href="#"><i class="nav-icon la la-gear"></i> Configuracion</a>
    <ul class="nav-dropdown-items">
        <li class="nav-item"><a class="nav-link" href="{{ backpack_url('sucursal') }}"><i
                    class="nav-icon la la-building"></i> <span>Sucursales</span></a></li>
        <li class='nav-item'><a class='nav-link' href='{{ backpack_url('configuracion') }}'><i class='nav-icon la la-gears'></i> Configuraciones</a></li>
        <li class='nav-item'><a class='nav-link' href='{{ backpack_url('pago-concepto') }}'><i class='nav-icon la la-list'></i> Conceptos de pago</a></li>
        <li class="nav-item nav-dropdown">
            <a class="nav-link nav-dropdown-toggle" href="#"><i class="nav-icon la la-gear"></i> Usuarios</a>
            <ul class="nav-dropdown-items">
                <li class="nav-item"><a class="nav-link" href="{{ backpack_url('user') }}"><i
                            class="nav-icon la la-user"></i> <span>Usuarios</span></a></li>
                <li class="nav-item"><a class="nav-link" href="{{ backpack_url('role') }}"><i
                            class="nav-icon la la-id-badge"></i> <span>Roles</span></a></li>
                <li class="nav-item"><a class="nav-link" href="{{ backpack_url('permission') }}"><i
                            class="nav-icon la la-key"></i> <span>Permisos</span></a></li>
            </ul>
        </li>
    </ul>
</li>

