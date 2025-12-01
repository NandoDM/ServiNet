<?php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al actualizar el perfil profesional.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// 1. VERIFICACIÓN DE SESIÓN Y ROL
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['rol'] ?? '') !== 'profesional') {
    $response['message'] = 'Acceso denegado. Se requiere ser Profesional autenticado.';
    echo json_encode($response);
    exit;
}

$id_cuenta = $_SESSION['id_cuenta'] ?? 0; 
if (!is_numeric($id_cuenta) || $id_cuenta <= 0) {
    $response['message'] = 'Error de sesión: ID de cuenta inválido.';
    echo json_encode($response);
    exit;
}

// 2. OBTENER Y SANITIZAR DATOS DE ENTRADA

// Leer los campos de nombre/ubicación directamente de POST (UTF-8 puro)
$nombre = $_POST['nombre'] ?? '';
$apellido_paterno = $_POST['apellido_paterno'] ?? '';
$apellido_materno = $_POST['apellido_materno'] ?? '';
$municipio = $_POST['municipio'] ?? ''; 

// Mantenemos la sanitización solo para teléfono
$telefono = filter_input(INPUT_POST, 'telefono', FILTER_SANITIZE_NUMBER_INT) ?? ''; 

// 🔑 CORRECCIÓN CLAVE: Leer los campos de perfil directamente de POST (UTF-8 puro)
$especialidades = $_POST['especialidades'] ?? '';
$experiencia = filter_input(INPUT_POST, 'experiencia', FILTER_SANITIZE_NUMBER_INT) ?? '';
$descripcion = $_POST['descripcion'] ?? '';


// 3. VALIDACIONES
if (empty($nombre) || empty($apellido_paterno) || empty($telefono) || empty($municipio)) {
    $response['message'] = 'Faltan datos personales obligatorios.';
    echo json_encode($response);
    exit;
}

// VALIDACIÓN DE FORMATO: Permite acentos y ñ (bandera /u)
if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u', $nombre) || 
    !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u', $apellido_paterno) ||
    !preg_match('/^\d{10}$/', $telefono)) {
    $response['message'] = 'Error de formato en nombre, apellido o teléfono (10 dígitos).';
    echo json_encode($response);
    exit;
}
if (empty($especialidades) || empty($experiencia) || empty($descripcion)) {
     $response['message'] = 'Faltan detalles profesionales obligatorios (Especialidad, Experiencia, Descripción).';
     echo json_encode($response);
     exit;
}


try {
    // 4. INICIAR TRANSACCIÓN
    $conn->begin_transaction();

    // A. ACTUALIZAR TABLA Cuenta
    $sql_cuenta = "
        UPDATE Cuenta 
        SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, telefono = ?, municipio = ?
        WHERE id_cuenta = ?
    ";
    $stmt_cuenta = $conn->prepare($sql_cuenta);
    $stmt_cuenta->bind_param("sssssi", 
        $nombre, 
        $apellido_paterno, 
        $apellido_materno, 
        $telefono, 
        $municipio, 
        $id_cuenta 
    );
    if (!$stmt_cuenta->execute()) {
        throw new Exception("Error al actualizar la Cuenta: " . $stmt_cuenta->error);
    }
    $stmt_cuenta->close();
    
    // B. ACTUALIZAR TABLA PerfilProfesional
    $sql_perfil = "
        UPDATE PerfilProfesional 
        SET especialidades = ?, experiencia = ?, descripcion = ?
        WHERE id_cuenta = ?
    ";
    $stmt_perfil = $conn->prepare($sql_perfil);
    
    // Los datos UTF-8 puros se guardan correctamente aquí
    $stmt_perfil->bind_param("sssi", 
        $especialidades, 
        $experiencia, 
        $descripcion,
        $id_cuenta 
    );

    if (!$stmt_perfil->execute()) {
        throw new Exception("Error al actualizar el Perfil Profesional: " . $stmt_perfil->error);
    }
    $stmt_perfil->close();

    // 5. FINALIZAR TRANSACCIÓN
    $conn->commit();
    
    // 6. ACTUALIZAR SESIÓN (Solo el nombre para mostrar)
    $_SESSION['nombre'] = $nombre; 
    
    $response['success'] = true;
    $response['message'] = 'Perfil profesional y datos personales actualizados exitosamente.';

} catch (Exception $e) {
    // Revertir la transacción si algo falló
    $conn->rollback();
    error_log("Error de actualización de perfil profesional: " . $e->getMessage());
    $response['message'] = 'Fallo en la actualización: ' . $e->getMessage();
} finally {
    $conn->close();
}

echo json_encode($response);