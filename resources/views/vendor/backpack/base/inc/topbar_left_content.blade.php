{{--
    Selector de sucursal.

    Solo aparece para quien supervisa varias (administración y gerencia); a un
    cajero o a consulta no se les muestra porque su sucursal es fija. Al cambiarla
    se recarga la página: el scope global de sucursal se resuelve del lado del
    servidor, así que todo el panel y los reportes tienen que volver a pedirse.
--}}
@php
    $sucursalesDisponibles = \App\Support\SucursalActiva::disponibles();
    $sucursalActivaId = \App\Support\SucursalActiva::id();
@endphp

@if ($sucursalesDisponibles->count() > 1)
    <li class="nav-item d-flex align-items-center px-2">
        <form action="{{ route('sucursal-activa.cambiar') }}" method="POST" class="d-flex align-items-center mb-0">
            @csrf
            <label for="selector-sucursal" class="mb-0 mr-2 text-muted d-none d-md-inline" style="font-size: .8rem;">
                <i class="la la-store"></i> Sucursal
            </label>
            <select
                id="selector-sucursal"
                name="sucursal_id"
                class="form-control form-control-sm"
                style="min-width: 190px;"
                onchange="this.form.submit()"
            >
                @foreach ($sucursalesDisponibles as $sucursal)
                    <option value="{{ $sucursal->id }}" @if ($sucursal->id === $sucursalActivaId) selected @endif>
                        {{ $sucursal->razon_social }}
                    </option>
                @endforeach
            </select>
        </form>
    </li>
@endif
