<?php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al obtener el perfil.', 'data' => null];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

// 🚨 CORRECCIÓN 1: Validar estrictamente el ID de cuenta 🚨
if (!is_numeric($id_cuenta) || $id_cuenta <= 0) {
    $response['message'] = 'Error al cargar datos: ID de cuenta no válido en sesión.'; 
    echo json_encode($response);
    exit;
}

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    $response['message'] = 'Error de conexión a la base de datos.';
    echo json_encode($response);
    exit;
}

try {
    // 2. CONSULTA COMBINADA (JOIN)
    // Se asegura de obtener la info del perfil, incluso si algunos campos PP son nulos.
    $sql = "
        SELECT 
            c.id_cuenta, c.nombre, c.apellido_paterno, c.apellido_materno, c.correo, c.telefono, c.municipio, c.foto_perfil_url,
            pp.id_profesional, pp.especialidades, pp.experiencia, pp.tarifa, pp.descripcion, pp.estado_verificacion
        FROM Cuenta c
        LEFT JOIN PerfilProfesional pp ON c.id_cuenta = pp.id_cuenta
        WHERE c.id_cuenta = ? AND c.activo = 1
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_cuenta);
    $stmt->execute();
    $result = $stmt->get_result();
    $profileData = $result->fetch_assoc();
    $stmt->close();

    // 3. Verificación y Respuesta
    if ($profileData) {
        
        // 🚨 CORRECCIÓN 2: Si el perfil profesional no existe para esta cuenta, forzar un mensaje de error o advertencia.
        if ($profileData['id_profesional'] === null) {
            $response['message'] = 'Advertencia: El perfil profesional está incompleto. Por favor, completa tu información.';
        } else {
             $response['message'] = 'Perfil obtenido exitosamente.';
        }
        
        $response['success'] = true;
        // Aseguramos que los campos null de la DB se conviertan a cadenas vacías para que el JS no falle
        $profileData['apellido_materno'] = $profileData['apellido_materno'] ?? '';
        $response['data'] = $profileData;
        
    } else {
         $response['message'] = 'Perfil no encontrado. Asegúrate de que tu cuenta esté activa.';
    }

} catch (Exception $e) {
    error_log("Error al obtener perfil de profesional: " . $e->getMessage());
    $response['message'] = 'Fallo en la consulta: ' . $e->getMessage();
} finally {
    if (isset($conn) && $conn) $conn->close();
}

echo json_encode($response);
?>