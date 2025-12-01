<?php
// nandodm/servinet/ServiNet-35fb085a9f887b792b3d4ac23d22f72cd494ceec/api/auth/recuperacion.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST");

require_once '../db_config.php'; 

$response = ['success' => false, 'message' => 'Error al solicitar recuperación.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

$correo = $_POST['correo'] ?? '';

if (empty($correo) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Por favor, ingresa un correo electrónico válido.';
    echo json_encode($response);
    exit;
}

try {
    // 1. Verificar que el correo existe y la cuenta está activa
    $sql_check = "SELECT id_cuenta, nombre FROM Cuenta WHERE correo = ? AND activo = 1";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $correo);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $user = $result->fetch_assoc();
    $stmt_check->close();

    if (!$user) {
        // Mensaje genérico por seguridad (no revela si el correo existe o no)
        $response['success'] = true; 
        $response['message'] = 'Si la dirección de correo electrónico está registrada, recibirás un enlace para restablecer tu contraseña.';
        echo json_encode($response);
        exit;
    }

    $id_cuenta = $user['id_cuenta'];
    $token = bin2hex(random_bytes(32)); // Generar un token seguro de 64 caracteres
    $fecha_expiracion = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expira en 1 hora

    // 2. Insertar el token en la tabla Recuperacion
    $sql_insert = "INSERT INTO Recuperacion (id_cuenta, token, fecha_expiracion) VALUES (?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("iss", $id_cuenta, $token, $fecha_expiracion);
    
    if (!$stmt_insert->execute()) {
        throw new Exception("Error al guardar el token de recuperación.");
    }
    $stmt_insert->close();

    // 3. Simular el envío de email (En producción, se implementa aquí la función real de envío)
    $response['success'] = true;
    $response['message'] = 'Se ha enviado un enlace de recuperación a tu correo electrónico. (Token simulado: ' . $token . ')';

} catch (Exception $e) {
    error_log("Error de recuperación de contraseña: " . $e->getMessage());
    $response['message'] = 'Fallo en el servidor: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>