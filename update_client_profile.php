<?php
session_start(); // Iniciar sesión

// Configuración de cabeceras
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Incluir la configuración de la base de datos
require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al actualizar el perfil.'];

// Asegurarse de que el método sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// ----------------------------------------------------
// 1. OBTENER ID DE SESIÓN Y VERIFICACIÓN DE ROL
// ----------------------------------------------------
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['rol'] ?? '') !== 'cliente') {
    $response['message'] = 'Acceso denegado. Se requiere ser Cliente autenticado.';
    echo json_encode($response);
    exit;
}

// OBTENER ID DE LA SESIÓN DE FORMA SEGURA
$id_cuenta = $_SESSION['id_cuenta'] ?? 0; 

// ----------------------------------------------------
// 2. OBTENER, SANITIZAR Y VALIDAR DATOS DEL FORMULARIO
// ----------------------------------------------------

// Leer los campos de nombre directamente de POST para preservar acentos y ñ (requerido por el regex)
$nombre = $_POST['nombre'] ?? '';
$apellido_paterno = $_POST['apellido_paterno'] ?? '';
$apellido_materno = $_POST['apellido_materno'] ?? '';

// Mantenemos la sanitización solo para teléfono
$telefono = filter_input(INPUT_POST, 'telefono', FILTER_SANITIZE_NUMBER_INT) ?? ''; 
// 🔑 CORRECCIÓN CLAVE: Leemos el municipio directamente de POST para preservar UTF-8
$municipio = $_POST['municipio'] ?? ''; 


// --- VALIDACIONES DE INTEGRIDAD DE DATOS ---

// Validación de campos obligatorios
if (empty($id_cuenta) || empty($nombre) || empty($apellido_paterno) || empty($telefono) || empty($municipio)) {
    $response['message'] = 'Faltan campos obligatorios para la actualización (nombre, apellido, teléfono o municipio).';
    echo json_encode($response);
    exit;
}

// Validación de ID de cuenta 
if (!is_numeric($id_cuenta) || $id_cuenta <= 0) {
    $response['message'] = 'Error de sesión: ID de cuenta inválido.';
    echo json_encode($response);
    exit;
}

// VALIDACIÓN ESTRICTA: Nombres y Apellidos (SOLO LETRAS, ESPACIOS, ACENTOS Y Ñ)
if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u', $nombre) || 
    !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u', $apellido_paterno) ||
    (!empty($apellido_materno) && !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]*$/u', $apellido_materno))) {
    
    $response['message'] = 'Error de formato: El nombre y apellidos solo pueden contener letras y espacios.';
    echo json_encode($response);
    exit;
}

// VALIDACIÓN ESTRICTA DE TELÉFONO (10 DÍGITOS)
if (!preg_match('/^\d{10}$/', $telefono)) {
    $response['message'] = 'Error de formato: El número de teléfono debe contener exactamente 10 dígitos.';
    echo json_encode($response);
    exit;
}
// --- FIN DE VALIDACIONES ---

try {
    // 3. PREPARAR LA CONSULTA DE ACTUALIZACIÓN
    // Nota: El uso de $conn->set_charset("utf8mb4") en db_config.php es crucial aquí.
    $sql = "
        UPDATE Cuenta 
        SET 
            nombre = ?, 
            apellido_paterno = ?, 
            apellido_materno = ?, 
            telefono = ?, 
            municipio = ?
        WHERE id_cuenta = ?
    ";
    $stmt = $conn->prepare($sql);
    
    // Tipos: sssssi (5 strings, 1 integer)
    // El binding seguro guardará los valores UTF-8 puros.
    $stmt->bind_param("sssssi", 
        $nombre, 
        $apellido_paterno, 
        $apellido_materno, 
        $telefono, 
        $municipio,
        $id_cuenta 
    );

    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar la actualización: " . $stmt->error);
    }
    
    // 4. VERIFICAR RESULTADO Y ACTUALIZAR SESIÓN
    if ($stmt->affected_rows > 0) {
        // Actualizar el nombre en la sesión de PHP inmediatamente
        $_SESSION['nombre'] = $nombre; 
        
        $response['success'] = true;
        $response['message'] = 'Perfil actualizado exitosamente.';
    } else {
        $response['success'] = true;
        $response['message'] = 'No se realizaron cambios en el perfil (los datos son idénticos o la cuenta no existe).';
    }

} catch (Exception $e) {
    // Manejo de errores
    error_log("Error de actualización de perfil de cliente: " . $e->getMessage());
    $response['message'] = 'Fallo en la actualización: ' . $e->getMessage();
} finally {
    if (isset($stmt)) $stmt->close();
    $conn->close();
}

echo json_encode($response);