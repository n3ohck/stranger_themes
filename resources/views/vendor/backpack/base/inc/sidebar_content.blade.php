@can('ventas.ver')
    <li class='nav-item'><a class='nav-link' href='{{ backpack_url('venta') }}'><i class='nav-icon la la-list'></i> Ventas</a></li>
@endcan
@can('productos.ver')
    <li class='nav-item'>
        <a class='nav-link' href='{{ backpack_url('producto') }}'>
            <i class='nav-icon la la-list'></i> Productos
        </a>
    </li>
@endcan
@can('descuentos.ver')
    <li class='nav-item'>
        <a class='nav-link' href='{{ backpack_url('descuento') }}'>
            <i class='nav-icon la la-list'></i> Descuentos
        </a>
    </li>
@endcan
@can('egresos.ver')
    <li class='nav-item'>
        <a class='nav-link' href='{{ backpack_url('egreso') }}'>
            <i class='nav-icon la la-exchange'></i> Egresos
        </a>
    </li>
@endcan
@can('cortes.ver')
    <li class='nav-item'>
        <a class='nav-link' href='{{ backpack_url('corte') }}'>
            <i class='nav-icon la la-list'></i> Cortes
        </a>
    </li>
@endcan
@can('empleados.ver')
    <li class='nav-item'>
        <a class='nav-link' href='{{ backpack_url('empleado') }}'>
            <i class='nav-icon la la-users'></i> Empleados
        </a>
    </li>
@endcan
@can('empleados_pagos.ver')
    <li class='nav-item'>
        <a class='nav-link' href='{{ backpack_url('empleado-pago') }}'>
            <i class='nav-icon la la-list'></i> Empleados Pagos
        </a>
    </li>
@endcan
@can('reporte.pagos.ver')
    <li class='nav-item'>
        <a class='nav-link' href='{{ backpack_url('payment-report') }}'>
            <i class='nav-icon la la-images'></i> Reporte pagos
        </a>
    </li>
@endcan
@can('log.envios.ver')
<li class='nav-item'><a class='nav-link' href='{{ backpack_url('log-notificacion') }}'><i class='nav-icon la la-envelope'></i> Log notificaciones</a></li>
@endcan
@can('configuraciones.ver')
<li class="nav-item nav-dropdown">
    <a class="nav-link nav-dropdown-toggle" href="#"><i class="nav-icon la la-gear"></i> Configuracion</a>
    <ul class="nav-dropdown-items">
        <li class="nav-item"><a class="nav-link" href="{{ backpack_url('sucursal') }}"><i
                    class="nav-icon la la-building"></i> <span>Sucursales</span></a></li>
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
@endcan
