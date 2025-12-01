<?php
// nandodm/servinet/ServiNet-35fb085a9f887b792b3d4ac23d22f72cd494ceec/api/get_client_profile.php
session_start();

// Configuración de cabeceras
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// Incluir la configuración de la base de datos
require_once 'db_config.php';

$response = ['success' => false, 'message' => 'Error al obtener datos del perfil.', 'data' => null];

// ----------------------------------------------------
// 1. OBTENER CORREO DE LA SESIÓN Y VERIFICAR ROL
// ----------------------------------------------------
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['rol'] ?? '') !== 'cliente') {
    $response['message'] = 'Acceso denegado. Se requiere ser Cliente autenticado.';
    echo json_encode($response);
    exit;
}

$correo = $_SESSION['correo']; 
$id_cuenta = $_SESSION['id_cuenta'];

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    $response['message'] = 'Error de conexión a la base de datos.';
    echo json_encode($response);
    exit;
}

try {
    // 2. OBTENER TODOS LOS DATOS DE LA CUENTA (incluyendo la nueva foto_perfil_url)
    $sql = "
        SELECT 
            id_cuenta, correo, nombre, apellido_paterno, apellido_materno, telefono, municipio,
            foto_perfil_url /* 🚨 Campo de foto de perfil agregado 🚨 */
        FROM Cuenta 
        WHERE correo = ? AND id_cuenta = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $correo, $id_cuenta);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Perfil de cliente no encontrado, a pesar de la sesión activa.");
    }
    
    $profile_data = $result->fetch_assoc();
    $stmt->close();

    $response['success'] = true;
    $response['message'] = 'Datos del perfil cargados automáticamente.';
    $response['data'] = $profile_data;

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_client_profile.php: " . $e->getMessage());
    $response['message'] = 'Error de servidor interno: ' . $e->getMessage();
}

if (isset($conn)) $conn->close();

echo json_encode($response);
?>