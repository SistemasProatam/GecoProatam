<?php
if (!defined('ROLE_PERMISSIONS')) {

    define('ROLE_PERMISSIONS', [
        'Director General'          => ['*'], // acceso total
        'Subdirector General'       => ['*'],
        'Tecnico de Sistemas'       => ['*'],

        'Gerente de Operaciones'    => ['activos', 'catalog', 'proyectos', 'obras', 'plan_obra', 'ordenes', 'crear_orden', 'requisiciones', 'dashboard', 'cotizaciones'],
        'Supervisor de Proyecto'    => ['catalog', 'proyectos', 'obras', 'ordenes', 'crear_orden', 'requisiciones'],
        'Procura'                   => ['activos', 'catalog', 'proyectos', 'obras', 'plan_obra', 'ordenes', 'crear_orden', 'requisiciones', 'cotizaciones'],
        'Gerente de Recursos Humanos' => ['ordenes', 'usuarios'],
        'Coordinador de Control de Documentos y Facturación' => ['activos', 'requisiciones'],
        'Gerente de Seguridad Salud y Medio Ambiente'        => ['activos', 'requisiciones'],
        'Supervisor del sistema de Administración' => ['requisiciones'],
        'Supervisor de Calidad'     => ['requisiciones'],
        'Residente de Obra'         => ['requisiciones'],
    ]);
}

if (!function_exists('hasPermission')) {
    function hasPermission(string $resource): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $rol = $_SESSION['departamento'] ?? '';
        $permisos = ROLE_PERMISSIONS[$rol] ?? [];

        return in_array('*', $permisos) || in_array($resource, $permisos);
    }
}
