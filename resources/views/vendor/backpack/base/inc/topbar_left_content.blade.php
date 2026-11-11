{{-- Selector de sucursal en la barra superior (escritorio). En móvil va al menú lateral. --}}
<li class="nav-item d-flex align-items-center px-2">
    @include(backpack_view('inc.selector_sucursal'), ['variante' => 'barra'])
</li>
