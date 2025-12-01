<?php
session_start();

// Configuración de cabeceras
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al actualizar la contraseña.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// 1. VERIFICACIÓN DE SESIÓN (CRÍTICO)
// 🚨 CAMBIO CLAVE: Cambiar 'cliente' a 'profesional'
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['rol'] ?? '') !== 'profesional') {
    $response['message'] = 'Acceso denegado. Se requiere ser Profesional autenticado.';
    echo json_encode($response);
    exit;
}

$id_cuenta = $_SESSION['id_cuenta'] ?? 0;

// ... (Resto de la lógica es idéntica a update_client_password.php) ...

// 2. OBTENER Y VALIDAR DATOS DEL FORMULARIO
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    $response['message'] = 'Todos los campos son obligatorios.';
    echo json_encode($response);
    exit;
}

if ($new_password !== $confirm_password) {
    $response['message'] = 'La nueva contraseña y su confirmación no coinciden.';
    echo json_encode($response);
    exit;
}

if (strlen($new_password) < 8) {
    $response['message'] = 'La nueva contraseña debe tener al menos 8 caracteres.';
    echo json_encode($response);
    exit;
}

if ($current_password === $new_password) {
    $response['message'] = 'La nueva contraseña no puede ser idéntica a la contraseña actual.';
    echo json_encode($response);
    exit;
}

try {
    // 3. VERIFICAR CONTRASEÑA ACTUAL
    $sql = "SELECT contraseña FROM Cuenta WHERE id_cuenta = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_cuenta);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($current_password, $user['contraseña'])) {
        $response['message'] = 'La contraseña actual es incorrecta.';
        echo json_encode($response);
        exit;
    }

    // 4. ACTUALIZAR LA CONTRASEÑA
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $sql = "UPDATE Cuenta SET contraseña = ? WHERE id_cuenta = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $hashed_password, $id_cuenta);

    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar la actualización: " . $stmt->error);
    }
    
    $response['success'] = true;
    $response['message'] = 'Contraseña actualizada exitosamente. Por seguridad, por favor, vuelve a iniciar sesión.';
    
    // Opcional: Cerrar la sesión del usuario inmediatamente después del cambio de contraseña
    session_destroy();

} catch (Exception $e) {
    error_log("Error de actualización de contraseña: " . $e->getMessage());
    $response['message'] = 'Error de servidor: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>