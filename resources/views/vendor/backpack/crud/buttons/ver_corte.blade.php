{{-- Abre el corte con el mismo formato que el ticket impreso al cerrar caja. --}}
<a href="{{ url($crud->route . '/' . $entry->getKey() . '/ticket') }}" class="btn btn-sm btn-link" title="Ver el corte como en el ticket">
    <i class="la la-receipt"></i> Ver corte
</a>
