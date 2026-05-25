<?php
require_once __DIR__ . "/../conexion.php";

/**
 * Detecta proyectos cuya fecha_fin ya pasó y que aún no están en historial,
 * y los registra automáticamente con estado 'pendiente'.
 * Retorna el número de proyectos nuevos registrados.
 */
function registrarProyectosTerminados($conn): int
{
    $sql = "
        INSERT INTO historial_proyectos_cliente (proyecto_id, cliente_id, estado)
        SELECT p.id, p.cliente_id, 'pendiente'
        FROM proyectos p
        WHERE p.fecha_fin <= CURDATE()
          AND p.cliente_id IS NOT NULL
          AND NOT EXISTS (
              SELECT 1 FROM historial_proyectos_cliente h
              WHERE h.proyecto_id = p.id
          )
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->affected_rows;
}
