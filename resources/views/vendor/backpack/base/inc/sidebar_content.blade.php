@can('sliders')
    <li class='nav-item'><a class='nav-link' href='{{ backpack_url('slider') }}'><i class='nav-icon la la-sliders'></i>
            Sliders</a></li>
@endcan
@can('formatos_descargables')
    <li class="nav-item nav-dropdown">
        <a class="nav-link nav-dropdown-toggle" href="#"><i class="nav-icon la la-download"></i> Formatos
            descargables</a>
        <ul class="nav-dropdown-items">
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('formato-descargable-categoria') }}'><i
                        class='nav-icon la la-list'></i> Categorias</a></li>
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('formato-descargable') }}'><i
                        class='nav-icon la la-list'></i> Formatos</a></li>
        </ul>
    </li>
@endcan
@can('capacitaciones')
    <li class="nav-item nav-dropdown">
        <a class="nav-link nav-dropdown-toggle" href="#"><i class="nav-icon la la-download"></i> Capacitaciones</a>
        <ul class="nav-dropdown-items">
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('capacitacion-tipo') }}'><i
                        class='nav-icon la la-list'></i> Tipos</a></li>
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('capacitacion-tipo-subtipo') }}'><i class='nav-icon la la-list'></i> Subtipos</a></li>
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('capacitacion') }}'><i
                        class='nav-icon la la-list'></i> Capacitaciones</a></li>
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('capacitacion-file') }}'><i
                        class='nav-icon la la-list'></i> Capacitaciones Archivos Descargables</a></li>
        </ul>
    </li>
@endcan
@can('calendario')
    <li class="nav-item nav-dropdown">
        <a class="nav-link nav-dropdown-toggle" href="#"><i class="nav-icon la la-calendar"></i> Calendario</a>
        <ul class="nav-dropdown-items">
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('calendario-categoria') }}'><i
                        class='nav-icon la la-list'></i> Categorias</a></li>
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('calendario') }}'><i
                        class='nav-icon la la-calendar-alt'></i> Calendario</a></li>
           <!-- <li class='nav-item'><a class='nav-link' href='{{ backpack_url('calendario-mensual') }}'><i class='nav-icon la la-list'></i> Calendario mensual archivos</a></li> -->
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('calendario-anual') }}'><i class='nav-icon la la-list'></i> Calendario anual</a></li>
        </ul>
    </li>
@endcan
@can('directorio')
    <li class="nav-item nav-dropdown">
        <a class="nav-link nav-dropdown-toggle" href="#"><i class="nav-icon la la-list"></i> Directorio</a>
        <ul class="nav-dropdown-items">
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('directorio-departamento') }}'><i
                        class='nav-icon la la-list'></i> Departamentos</a></li>
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('directorio-puesto') }}'><i
                        class='nav-icon la la-list'></i> Puestos</a></li>
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('directorio-proyecto') }}'><i
                        class='nav-icon la la-list'></i> Proyectos</a></li>
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('directorio-categorias') }}'><i
                        class='nav-icon la la-list'></i> Categorias</a></li>
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('directorio-personas') }}'><i
                        class='nav-icon la la-list'></i> Personas</a></li>
        </ul>
    </li>
@endcan
@can('proyectos')
    <li class="nav-item nav-dropdown">
        <a class="nav-link nav-dropdown-toggle" href="#"><i class="nav-icon la la-list"></i> Proyectos</a>
        <ul class="nav-dropdown-items">
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('ubicacion-proyecto') }}'><i
                        class='nav-icon la la-list'></i> Listado</a></li>
        </ul>
    </li>
@endcan
@can('convenios')
    <li class="nav-item nav-dropdown">
        <a class="nav-link nav-dropdown-toggle" href="#"><i class="nav-icon la la-list"></i> Convenios</a>
        <ul class="nav-dropdown-items">
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('tipo-convenio') }}'><i
                        class='nav-icon la la-list'></i> Tipos</a></li>
            <li class='nav-item'><a class='nav-link' href='{{ backpack_url('convenio') }}'><i
                        class='nav-icon la la-list'></i> Listado</a></li>
        </ul>
    </li>
@endcan
@can('revistas')
    <li class='nav-item'><a class='nav-link' href='{{ backpack_url('revista') }}'><i
                class='nav-icon la la-book-open'></i> Revistas</a></li>
@endcan
@can('quejas_sugerencias')
    <li class='nav-item'><a class='nav-link' href='{{ backpack_url('queja-sugerencia') }}'><i
                class='nav-icon la la-list'></i> Contacto RH</a></li>
    <li class='nav-item'><a class='nav-link' href='{{ backpack_url('contacto-comunicacion') }}'><i
                class='nav-icon la la-list'></i> Contacto Comunicacion</a></li>
@endcan
@can('configuracion')
    <li class='nav-item'><a class='nav-link' href='{{ backpack_url('titulo') }}'><i class='nav-icon la la-list'></i>
            Titulos</a></li>
    <li class="nav-item nav-dropdown">
        <a class="nav-link nav-dropdown-toggle" href="#"><i class="nav-icon la la-users"></i> Gestor de usuarios</a>
        <ul class="nav-dropdown-items">
            <li class="nav-item"><a class="nav-link" href="{{ backpack_url('user') }}"><i
                        class="nav-icon la la-user"></i> <span>Usuarios</span></a></li>
            <li class="nav-item"><a class="nav-link" href="{{ backpack_url('role') }}"><i
                        class="nav-icon la la-id-badge"></i> <span>Roles</span></a></li>
            <li class="nav-item"><a class="nav-link" href="{{ backpack_url('permission') }}"><i
                        class="nav-icon la la-key"></i> <span>Permisos</span></a></li>
        </ul>
    </li>
@endcan
