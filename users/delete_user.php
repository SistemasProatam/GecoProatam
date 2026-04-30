<?php
include_once __DIR__ . "/../conexion.php";

// Headers para JSON
header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    try {
        // Verificar dependencias antes de eliminar
        $dependencias = [];

        // 1. Verificar si tiene requisiciones en estado pendiente
        $sql_requisiciones_pendientes = "SELECT COUNT(*) as count FROM requisiciones WHERE solicitante_id = ? AND estado = 'Pendiente'";
        $stmt_requisiciones = $conn->prepare($sql_requisiciones_pendientes);
        if (!$stmt_requisiciones) {
            throw new Exception("Error preparando consulta de requisiciones: " . $conn->error);
        }
        $stmt_requisiciones->bind_param("i", $id);
        $stmt_requisiciones->execute();
        $result_requisiciones = $stmt_requisiciones->get_result()->fetch_assoc();
        if ($result_requisiciones['count'] > 0) {
            $dependencias[] = "tiene " . $result_requisiciones['count'] . " requisición(es) pendiente(s)";
        }
        $stmt_requisiciones->close();

        // 2. Verificar si tiene órdenes de compra en estado pendiente
        $sql_check_columns = "SHOW COLUMNS FROM ordenes_compra";
        $result_columns = $conn->query($sql_check_columns);
        $columnas_ordenes = [];
        while ($col = $result_columns->fetch_assoc()) {
            $columnas_ordenes[] = $col['Field'];
        }

        $sql_ordenes = "";
        // Construir la consulta según las columnas que existan
        if (in_array('solicitante_id', $columnas_ordenes)) {
            $sql_ordenes = "SELECT COUNT(*) as count FROM ordenes_compra WHERE solicitante_id = ? AND estado = 'Pendiente'";
        } elseif (in_array('usuario_id', $columnas_ordenes)) {
            $sql_ordenes = "SELECT COUNT(*) as count FROM ordenes_compra WHERE usuario_id = ? AND estado = 'Pendiente'";
        } elseif (in_array('creado_por', $columnas_ordenes)) {
            $sql_ordenes = "SELECT COUNT(*) as count FROM ordenes_compra WHERE creado_por = ? AND estado = 'Pendiente'";
        }

        if (!empty($sql_ordenes)) {
            $stmt_ordenes = $conn->prepare($sql_ordenes);
            if ($stmt_ordenes) {
                $stmt_ordenes->bind_param("i", $id);
                $stmt_ordenes->execute();
                $result_ordenes = $stmt_ordenes->get_result()->fetch_assoc();
                if ($result_ordenes['count'] > 0) {
                    $dependencias[] = "tiene " . $result_ordenes['count'] . " orden(es) de compra pendiente(s)";
                }
                $stmt_ordenes->close();
            }
        }

        // Si hay dependencias pendientes, retornar error con detalles
        if (!empty($dependencias)) {
            echo json_encode([
                'status' => 'error',
                'message' => "No se puede eliminar el usuario porque " . implode(", ", $dependencias) . "."
            ]);
            exit;
        }

        // Si no hay dependencias, proceder con la eliminación
        try {
            // Iniciar transacción para asegurar integridad
            $conn->begin_transaction();

            // 1. Eliminar contratos asociados al usuario
            $sql_delete_contratos = "DELETE FROM contratos_usuario WHERE usuario_id = ?";
            $stmt_delete_contratos = $conn->prepare($sql_delete_contratos);
            if (!$stmt_delete_contratos) {
                throw new Exception("Error preparando consulta de eliminación de contratos: " . $conn->error);
            }
            $stmt_delete_contratos->bind_param("i", $id);
            if (!$stmt_delete_contratos->execute()) {
                throw new Exception("Error eliminando contratos: " . $stmt_delete_contratos->error);
            }
            $stmt_delete_contratos->close();

            // 2. Eliminar el usuario
            $sql_delete_usuario = "DELETE FROM usuarios WHERE id = ?";
            $stmt_delete_usuario = $conn->prepare($sql_delete_usuario);
            if (!$stmt_delete_usuario) {
                throw new Exception("Error preparando consulta de eliminación de usuario: " . $conn->error);
            }
            $stmt_delete_usuario->bind_param("i", $id);
            if (!$stmt_delete_usuario->execute()) {
                throw new Exception("Error eliminando usuario: " . $stmt_delete_usuario->error);
            }
            $stmt_delete_usuario->close();

            // Confirmar transacción
            $conn->commit();

            echo json_encode([
                'status' => 'success',
                'message' => 'Usuario eliminado correctamente'
            ]);
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $conn->rollback();
            throw $e;
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'ID no proporcionado'
    ]);
}

$conn->close();
