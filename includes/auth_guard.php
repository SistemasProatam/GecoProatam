<?php

if (!defined('ROUTE_PERMISSIONS_LOADED')) {
    define('ROUTE_PERMISSIONS_LOADED', true);

    $ROUTE_PERMISSIONS = [
        // Usuarios
        'list_users.php'         => 'usuarios',

        // Proyectos
        'list_project.php'       => 'proyectos',
        'add_project.php'        => 'proyectos',
        'plan_obra.php'          => 'plan_obra',

        // Órdenes
        'list_oc.php'            => 'ordenes',
        'new_order.php'          => 'crear_orden',

        // Requisiciones
        'list_requis.php'        => 'requisiciones',

        // Cotizaciones
        'lista_cotizaciones.php' => 'cotizaciones',

        // Catálogo
        'list_catalog.php'       => 'catalog',

        // Activos
        'list_activos.php'       => 'activos',
        'add_activo.php'         => 'activos',
        'edit_activo.php'        => 'activos',

        // Dashboard
        'dashboard.php'          => 'dashboard',
    ];

    function checkRoutePermission(): void
    {
        global $ROUTE_PERMISSIONS;

        $current = basename($_SERVER['PHP_SELF']);

        if (!isset($ROUTE_PERMISSIONS[$current])) {
            return;
        }

        if (!hasPermission($ROUTE_PERMISSIONS[$current])) {
            require_once __DIR__ . '/../unauthorized.php';
            exit;
        }
    }
}
