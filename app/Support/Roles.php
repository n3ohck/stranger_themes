<?php

namespace App\Support;

/**
 * Nombres de rol tal como están dados de alta en la base.
 *
 * Spatie compara los nombres de rol con sensibilidad a mayúsculas, y el sistema
 * ya se quemó con eso: SucursalFilterScope preguntaba por 'administrador' cuando
 * el rol se llama 'Administrador', así que la exención de administrador nunca se
 * aplicó. Con una sola sucursal el error era invisible; con dos habría dejado a
 * los administradores viendo solo su propia sucursal.
 *
 * Cualquier comprobación de rol debe pasar por estas constantes.
 */
class Roles
{
    public const ADMINISTRADOR = 'Administrador';
    public const GERENCIA = 'Gerencia';
    public const APP_USER = 'APP USER';
    public const CONSULTA = 'CONSULTA';

    /** Roles que pueden ver y elegir cualquier sucursal. */
    public const SUPERVISION = [self::ADMINISTRADOR, self::GERENCIA];

    /** Roles que pueden operar el punto de venta. */
    public const PUNTO_DE_VENTA = [self::APP_USER, self::ADMINISTRADOR];

    /**
     * ¿El usuario supervisa varias sucursales?
     */
    public static function supervisaSucursales($user): bool
    {
        return $user && $user->hasAnyRole(self::SUPERVISION);
    }
}
