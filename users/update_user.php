<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
include(__DIR__ . "/../conexion.php");
header('Content-Type: application/json');

// Función para subir archivos
function subirArchivo($file, $campo, $idUsuario)
{
    if (!isset($file['name']) || empty($file['name'])) {
        return null;
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $nombreArchivo = $campo . '_' . $idUsuario . '_' . time() . '.' . $extension;
    $rutaDestino = __DIR__ . '/../uploads/usuarios/' . $nombreArchivo;

    // Crear directorio si no existe
    if (!is_dir(dirname($rutaDestino))) {
        mkdir(dirname($rutaDestino), 0777, true);
    }

    if (move_uploaded_file($file['tmp_name'], $rutaDestino)) {
        return $nombreArchivo;
    }

    return null;
}

// Recibir datos
$id = intval($_POST['id']);
$nombres = trim($_POST['nombres'] ?? '');
$apellidos = trim($_POST['apellidos'] ?? '');
$correo_corporativo = trim($_POST['correo_corporativo'] ?? '');
$correo_personal = trim($_POST['correo_personal'] ?? '');
$telefono_personal = trim($_POST['telefono_personal'] ?? '');
$departamento_id = $_POST['departamento_id'] ?? null;
$funciones_actividades = trim($_POST['funciones_actividades'] ?? '');
$fehca_ingreso = trim($_POST['fecha_ingreso'] ?? '');
$contacto_emergencia_nombre = trim($_POST['contacto_emergencia_nombre'] ?? '');
$contacto_emergencia_parentesco = trim($_POST['contacto_emergencia_parentesco'] ?? '');
$contacto_emergencia_telefono = trim($_POST['contacto_emergencia_telefono'] ?? '');

// Validación obligatoria
if (!$departamento_id || $departamento_id === "") {
    echo json_encode(["status" => "error", "message" => "Debes seleccionar un departamento."]);
    exit;
}

if (!preg_match('/@proatam\.com$/i', $correo_corporativo)) {
    echo json_encode(["status" => "error", "message" => "Solo se permiten correos corporativos @proatam.com."]);
    exit;
}

// Verificar si el correo ya existe (excluyendo el usuario actual)
$check = $conn->prepare("SELECT id FROM usuarios WHERE correo_corporativo = ? AND id != ?");
$check->bind_param("si", $correo_corporativo, $id);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "El correo corporativo ya está registrado por otro usuario."]);
    exit;
}

$departamento_id = intval($departamento_id);

try {
    // Iniciar transacción para asegurar consistencia
    $conn->begin_transaction();

    // Preparar actualización de datos básicos
    $sql = "UPDATE usuarios SET 
                nombres = ?, 
                apellidos = ?, 
                correo_corporativo = ?, 
                correo_personal = ?, 
                telefono_personal = ?, 
                departamento_id = ?,
                funciones_actividades = ?,
                fecha_ingreso = ?,
                contacto_emergencia_nombre = ?,
                contacto_emergencia_parentesco = ?,
                contacto_emergencia_telefono = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param(
        "sssssisssssi",
        $nombres,
        $apellidos,
        $correo_corporativo,
        $correo_personal,
        $telefono_personal,
        $departamento_id,
        $funciones_actividades,
        $fehca_ingreso,
        $contacto_emergencia_nombre,
        $contacto_emergencia_parentesco,
        $contacto_emergencia_telefono,
        $id
    );

    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar datos básicos: " . $stmt->error);
    }

    // Manejar subida de archivos
    $camposArchivos = [
        'curriculum_pdf',
        'identificacion_pdf',
        'acta_nacimiento_pdf',
        'curp_pdf',
        'situacion_fiscal_pdf',
        'nss_pdf',
        'comprobante_domicilio_pdf',
        'foto_jpg',
        'comprobante_estudios_pdf',
        'credencial_pdf',
        'acuerdo_confidencialidad_pdf'
    ];

    $updateFields = [];
    $updateParams = [];
    $updateTypes = '';

    foreach ($camposArchivos as $campo) {
        if (isset($_FILES[$campo]) && !empty($_FILES[$campo]['name'])) {
            $nombreArchivo = subirArchivo($_FILES[$campo], $campo, $id);
            if ($nombreArchivo) {
                $updateFields[] = "$campo = ?";
                $updateParams[] = $nombreArchivo;
                $updateTypes .= 's';

                // Eliminar archivo anterior si existe
                $sqlSelect = "SELECT $campo FROM usuarios WHERE id = ?";
                $stmtSelect = $conn->prepare($sqlSelect);
                $stmtSelect->bind_param("i", $id);
                $stmtSelect->execute();
                $stmtSelect->bind_result($archivoAnterior);
                $stmtSelect->fetch();
                $stmtSelect->close();

                if ($archivoAnterior) {
                    $rutaAnterior = __DIR__ . '/../uploads/usuarios/' . $archivoAnterior;
                    if (file_exists($rutaAnterior)) {
                        unlink($rutaAnterior);
                    }
                }
            }
        }
    }

    // Si hay archivos para actualizar
    if (!empty($updateFields)) {
        $updateSQL = "UPDATE usuarios SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $updateParams[] = $id;
        $updateTypes .= 'i';

        $stmtUpdate = $conn->prepare($updateSQL);
        if (!$stmtUpdate) {
            throw new Exception("Error al preparar actualización de archivos: " . $conn->error);
        }

        $stmtUpdate->bind_param($updateTypes, ...$updateParams);
        if (!$stmtUpdate->execute()) {
            throw new Exception("Error al actualizar archivos: " . $stmtUpdate->error);
        }
        $stmtUpdate->close();
    }

    // ===== MANEJO DE CONTRATOS =====
    if (isset($_POST['contrato_id'])) {
        foreach ($_POST['contrato_id'] as $index => $contrato_id) {
            $contrato_id = intval($contrato_id);
            $tipo_contrato = $_POST['tipos_contrato_existentes'][$index] ?? '';

            // Verificar si se marcó para eliminar
            $eliminar = isset($_POST['eliminar_contrato']) && in_array($contrato_id, $_POST['eliminar_contrato']);

            if ($eliminar) {
                // Eliminar contrato
                $stmtDelete = $conn->prepare("DELETE FROM contratos_usuario WHERE id = ?");
                $stmtDelete->bind_param("i", $contrato_id);
                $stmtDelete->execute();

                // También eliminar archivo físico si existe
                $sqlSelect = "SELECT nombre_archivo FROM contratos_usuario WHERE id = ?";
                $stmtSelect = $conn->prepare($sqlSelect);
                $stmtSelect->bind_param("i", $contrato_id);
                $stmtSelect->execute();
                $stmtSelect->bind_result($nombreArchivo);
                $stmtSelect->fetch();
                $stmtSelect->close();

                if ($nombreArchivo) {
                    $rutaArchivo = __DIR__ . '/../uploads/usuarios/' . $nombreArchivo;
                    if (file_exists($rutaArchivo)) {
                        unlink($rutaArchivo);
                    }
                }

                $stmtDelete->close();
            } else {
                // Actualizar contrato existente
                $updateContratoSQL = "UPDATE contratos_usuario SET  
                                tipo_contrato = ?
                                WHERE id = ?";
                $stmtUpdateContrato = $conn->prepare($updateContratoSQL);
                $stmtUpdateContrato->bind_param("si", $tipo_contrato, $contrato_id);
                $stmtUpdateContrato->execute();
                $stmtUpdateContrato->close();

                // Verificar si se subió un nuevo archivo para este contrato
                $fileKey = 'contratos_existentes_' . $contrato_id;
                if (isset($_FILES[$fileKey]) && !empty($_FILES[$fileKey]['name'])) {
                    $archivoContrato = [
                        'name' => $_FILES[$fileKey]['name'],
                        'type' => $_FILES[$fileKey]['type'],
                        'tmp_name' => $_FILES[$fileKey]['tmp_name'],
                        'error' => $_FILES[$fileKey]['error'],
                        'size' => $_FILES[$fileKey]['size']
                    ];

                    $nombreArchivo = subirArchivo($archivoContrato, 'contrato_' . $contrato_id, $id);
                    if ($nombreArchivo) {
                        // Actualizar nombre del archivo en la BD
                        $updateArchivoSQL = "UPDATE contratos_usuario SET 
                                        nombre_archivo = ?, 
                                        ruta_archivo = ?
                                        WHERE id = ?";
                        $rutaCompleta = '/PROATAM/uploads/usuarios/' . $nombreArchivo;
                        $stmtUpdateArchivo = $conn->prepare($updateArchivoSQL);
                        $stmtUpdateArchivo->bind_param("ssi", $nombreArchivo, $rutaCompleta, $contrato_id);
                        $stmtUpdateArchivo->execute();
                        $stmtUpdateArchivo->close();
                    }
                }
            }
        }
    }

    // ===== MANEJO DE NUEVOS CONTRATOS =====
    if (isset($_FILES['nuevos_contratos']) && isset($_POST['nuevos_tipos_contrato'])) {
        for ($i = 0; $i < count($_FILES['nuevos_contratos']['name']); $i++) {
            if (!empty($_FILES['nuevos_contratos']['name'][$i])) {
                $archivoContrato = [
                    'name' => $_FILES['nuevos_contratos']['name'][$i],
                    'type' => $_FILES['nuevos_contratos']['type'][$i],
                    'tmp_name' => $_FILES['nuevos_contratos']['tmp_name'][$i],
                    'error' => $_FILES['nuevos_contratos']['error'][$i],
                    'size' => $_FILES['nuevos_contratos']['size'][$i]
                ];

                $nombreArchivo = subirArchivo($archivoContrato, 'nuevo_contrato', $id);
                if ($nombreArchivo) {
                    $tipoContrato = $_POST['nuevos_tipos_contrato'][$i] ?? 'OTRO';

                    // Insertar nuevo contrato
                    $stmtNuevoContrato = $conn->prepare("INSERT INTO contratos_usuario 
                    (usuario_id, nombre_archivo, ruta_archivo, tipo_contrato) 
                    VALUES (?, ?, ?, ?)");
                    $rutaCompleta = '/uploads/usuarios/' . $nombreArchivo;
                    $stmtNuevoContrato->bind_param("isss", $id, $nombreArchivo, $rutaCompleta, $tipoContrato);
                    $stmtNuevoContrato->execute();
                    $stmtNuevoContrato->close();
                }
            }
        }
    }

    // Confirmar transacción
    $conn->commit();

    $contratosActualizados = isset($_POST['contrato_id']) ? count($_POST['contrato_id']) : 0;
    $nuevosContratos = isset($_FILES['nuevos_contratos']) ? count($_FILES['nuevos_contratos']['name']) : 0;

    echo json_encode([
        "status" => "success",
        "message" => "Usuario actualizado correctamente",
        "archivos_actualizados" => count($updateFields)
    ]);
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$stmt->close();
$conn->close();
exit;


