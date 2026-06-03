<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . "/../conexion.php";

// Incluir PHPMailer
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . "/../EmailHandler.php";

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

try {
    // Datos básicos
    $nombres = $_POST['nombres'] ?? '';
    $apellidos = $_POST['apellidos'] ?? '';
    $correo_corporativo = $_POST['correo_corporativo'] ?? '';
    $correo_personal = $_POST['correo_personal'] ?? '';
    $telefono_personal = $_POST['telefono_personal'] ?? '';
    $departamento_id = $_POST['departamento_id'] ?? null;
    $funciones_actividades = $_POST['funciones_actividades'] ?? '';
    $fecha_ingreso = $_POST['fecha_ingreso'] ?? '';
    $contacto_emergencia_nombre = $_POST['contacto_emergencia_nombre'] ?? '';
    $contacto_emergencia_parentesco = $_POST['contacto_emergencia_parentesco'] ?? '';
    $contacto_emergencia_telefono = $_POST['contacto_emergencia_telefono'] ?? '';

    // Validaciones básicas
    if (!$nombres || !$apellidos || !$correo_corporativo || !$departamento_id) {
        echo json_encode(['status' => 'error', 'message' => 'Faltan campos obligatorios.']);
        exit;
    }

    if (!preg_match('/@proatam\.com$/i', $correo_corporativo)) {
        echo json_encode(['status' => 'error', 'message' => 'Solo se permiten correos corporativos @proatam.com.']);
        exit;
    }



    /* Verificar si el correo ya existe
    $check = $conn->prepare("SELECT id FROM usuarios WHERE correo_corporativo = ?");
    $check->bind_param("s", $correo_corporativo);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'El correo corporativo ya está registrado.']);
        exit;
    }
    */

    // Generar contraseña temporal
    $contraseña_temporal = bin2hex(random_bytes(4));
    $password_hash = password_hash($contraseña_temporal, PASSWORD_DEFAULT);

    // Iniciar transacción para asegurar consistencia
    $conn->begin_transaction();

    try {
        // Insertar usuario 
        $stmt = $conn->prepare("INSERT INTO usuarios 
            (nombres, apellidos, correo_corporativo, correo_personal, telefono_personal, 
            password, password_temporal, departamento_id, funciones_actividades, fecha_ingreso,
            contacto_emergencia_nombre, contacto_emergencia_parentesco, contacto_emergencia_telefono) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            throw new Exception("Error al preparar inserción de usuario: " . $conn->error);
        }

        $password_temporal = 1;
        $stmt->bind_param(
            "ssssssissssss",
            $nombres,
            $apellidos,
            $correo_corporativo,
            $correo_personal,
            $telefono_personal,
            $password_hash,
            $password_temporal,
            $departamento_id,
            $funciones_actividades,
            $fecha_ingreso,
            $contacto_emergencia_nombre,
            $contacto_emergencia_parentesco,
            $contacto_emergencia_telefono
        );

        if (!$stmt->execute()) {
            throw new Exception("Error al insertar usuario: " . $stmt->error);
        }

        $idUsuario = $stmt->insert_id;
        $archivosSubidos = [];

        // Subir archivos
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

        // Preparar update para archivos
        $updateFields = [];
        $updateParams = [];
        $updateTypes = '';

        foreach ($camposArchivos as $campo) {
            if (isset($_FILES[$campo]) && !empty($_FILES[$campo]['name'])) {
                $nombreArchivo = subirArchivo($_FILES[$campo], $campo, $idUsuario);
                if ($nombreArchivo) {
                    $updateFields[] = "$campo = ?";
                    $updateParams[] = $nombreArchivo;
                    $updateTypes .= 's';
                    $archivosSubidos[] = $campo;
                }
            }
        }

        // Si hay archivos para actualizar
        if (!empty($updateFields)) {
            $updateSQL = "UPDATE usuarios SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $updateParams[] = $idUsuario;
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

        // Manejar contratos (después de subir los otros archivos)
        if (isset($_FILES['contratos']) && !empty($_FILES['contratos']['name'][0])) {
            $tipos_contrato = $_POST['tipos_contrato'] ?? [];

            for ($i = 0; $i < count($_FILES['contratos']['name']); $i++) {
                if (!empty($_FILES['contratos']['name'][$i])) {
                    $archivoContrato = [
                        'name' => $_FILES['contratos']['name'][$i],
                        'type' => $_FILES['contratos']['type'][$i] ?? '',
                        'tmp_name' => $_FILES['contratos']['tmp_name'][$i],
                        'error' => $_FILES['contratos']['error'][$i] ?? 0,
                        'size' => $_FILES['contratos']['size'][$i] ?? 0
                    ];

                    $nombreArchivo = subirArchivo($archivoContrato, 'contrato', $idUsuario);
                    if ($nombreArchivo) {
                        $tipoContrato = $tipos_contrato[$i] ?? 'Otro';

                        // Insertar en tabla contratos_usuario
                        $stmtContrato = $conn->prepare("INSERT INTO contratos_usuario 
                    (usuario_id, nombre_archivo, ruta_archivo, tipo_contrato) 
                    VALUES (?, ?, ?, ?)");

                        if (!$stmtContrato) {
                            throw new Exception("Error al preparar inserción de contrato: " . $conn->error);
                        }

                        $rutaCompleta = '/uploads/usuarios/' . $nombreArchivo;
                        $stmtContrato->bind_param("isss", $idUsuario, $nombreArchivo, $rutaCompleta, $tipoContrato);

                        if (!$stmtContrato->execute()) {
                            throw new Exception("Error al insertar contrato: " . $stmtContrato->error);
                        }
                        $stmtContrato->close();

                        $archivosSubidos[] = 'contrato_' . ($i + 1);
                    }
                }
            }
        }

        // Confirmar transacción
        $conn->commit();

        // Enviar correo con la contraseña temporal
        $emailHandler = new EmailHandler();
        $correo_enviado = $emailHandler->enviarCorreoBienvenida($correo_corporativo, $nombres, $apellidos, $contraseña_temporal);

        if ($correo_enviado) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Usuario creado exitosamente. La contraseña temporal ha sido enviada al correo del usuario.',
                'archivos_subidos' => $archivosSubidos,
                'correo_enviado' => true
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'message' => 'Usuario creado, pero no se pudo enviar el correo automático.',
                'contraseña' => $contraseña_temporal,
                'archivos_subidos' => $archivosSubidos,
                'correo_enviado' => false
            ]);
        }
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollback();
        throw $e;
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Excepción: ' . $e->getMessage()]);
}
