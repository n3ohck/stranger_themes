{{--
    Selector de sucursal del panel. Es el ÚNICO filtro de sucursal: las pantallas
    no llevan uno propio (ver CLAUDE.md, multi-tenancy).

    Quien supervisa varias sucursales (administración y gerencia) la puede cambiar;
    a los demás se les muestra fija, para que siempre sepan qué están viendo. Al
    cambiarla se recarga la página: el scope global de sucursal se resuelve del lado
    del servidor, así que todo el panel y los reportes tienen que volver a pedirse.

    $variante: 'barra' (barra superior, escritorio) o 'lateral' (menú, móvil).
--}}
@php
    $sucursalesDisponibles = \App\Support\SucursalActiva::disponibles();
    $sucursalActivaId = \App\Support\SucursalActiva::id();
    $sucursalActiva = $sucursalesDisponibles->firstWhere('id', $sucursalActivaId);
    $puedeCambiar = $sucursalesDisponibles->count() > 1;
    $variante = $variante ?? 'barra';
@endphp

@once
    <style>
        .st-sucursal {
            --st-rojo: #b11724;
            --st-rojo-oscuro: #8e121d;
            --st-tinta: #1c1c21;
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: .65rem;
            min-width: 250px;
            max-width: 340px;
            padding: .4rem .85rem .4rem .45rem;
            border: 1px solid #f0c9cd;
            border-left: 4px solid var(--st-rojo);
            border-radius: .6rem;
            background: linear-gradient(180deg, #fff 0%, #fdf3f4 100%);
            box-shadow: 0 1px 2px rgba(28, 28, 33, .06);
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .st-sucursal--cambia:hover { border-color: var(--st-rojo); box-shadow: 0 2px 8px rgba(177, 23, 36, .15); }
        .st-sucursal--cambia:focus-within { border-color: var(--st-rojo); box-shadow: 0 0 0 3px rgba(177, 23, 36, .2); }
        .st-sucursal__icono {
            flex: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.1rem;
            height: 2.1rem;
            border-radius: .45rem;
            background: var(--st-rojo);
            color: #fff;
            font-size: 1.15rem;
        }
        .st-sucursal__texto { display: flex; flex-direction: column; min-width: 0; flex: 1; line-height: 1.15; }
        .st-sucursal__etiqueta {
            font-size: .62rem;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            color: var(--st-rojo);
        }
        .st-sucursal__nombre {
            font-size: .92rem;
            font-weight: 700;
            color: var(--st-tinta);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        /* El select cubre toda la tarjeta: se ve el diseño y el clic abre la lista nativa. */
        .st-sucursal__select {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .st-sucursal__select:disabled { cursor: progress; }
        .st-sucursal__flecha { flex: none; color: var(--st-rojo); font-size: 1rem; }
        .st-sucursal--cargando { opacity: .65; }
        .st-sucursal--lateral { display: flex; width: auto; min-width: 0; max-width: 100%; margin: .75rem .75rem .5rem; }
        .st-sucursal--lateral .st-sucursal__nombre { font-size: .85rem; white-space: normal; }
    </style>
@endonce

@if ($sucursalActiva)
    <form action="{{ route('sucursal-activa.cambiar') }}" method="POST" class="mb-0">
        @csrf
        <div
            class="st-sucursal st-sucursal--{{ $variante }} {{ $puedeCambiar ? 'st-sucursal--cambia' : '' }}"
            title="{{ $puedeCambiar ? 'Todo el panel muestra esta sucursal. Haz clic para cambiarla.' : 'Tu usuario trabaja en esta sucursal.' }}"
        >
            <span class="st-sucursal__icono" aria-hidden="true"><i class="la la-store"></i></span>
            <span class="st-sucursal__texto">
                <span class="st-sucursal__etiqueta">{{ $puedeCambiar ? ($variante === 'lateral' ? 'Viendo sucursal' : 'Estás viendo la sucursal') : 'Tu sucursal' }}</span>
                <span class="st-sucursal__nombre">{{ $sucursalActiva->razon_social }}</span>
            </span>

            @if ($puedeCambiar)
                <i class="la la-angle-down st-sucursal__flecha" aria-hidden="true"></i>
                <select
                    name="sucursal_id"
                    class="st-sucursal__select"
                    aria-label="Cambiar de sucursal"
                    onchange="this.closest('.st-sucursal').classList.add('st-sucursal--cargando'); this.form.submit(); this.disabled = true;"
                >
                    @foreach ($sucursalesDisponibles as $sucursal)
                        <option value="{{ $sucursal->id }}" @if ($sucursal->id === $sucursalActivaId) selected @endif>
                            {{ $sucursal->razon_social }}
                        </option>
                    @endforeach
                </select>
            @endif
        </div>
    </form>
@endif
