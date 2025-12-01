<?php
// nandodm/servinet/ServiNet-e728ea1c53d2fa493dc4a4edd362433270b9cb26/api/send_message.php
session_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al enviar el mensaje.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// 1. OBTENER IDS DE LA SESIÓN Y DEL POST
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $response['message'] = 'Acceso denegado. Se requiere autenticación.';
    echo json_encode($response);
    exit;
}

$id_emisor = $_SESSION['id_cuenta'] ?? 0;
$id_receptor = $_POST['target_id'] ?? 0; // ID del contacto (profesional/cliente)
$contenido = $_POST['contenido'] ?? '';

// 2. VALIDACIONES
if (empty($id_emisor) || empty($id_receptor) || empty($contenido)) {
    $response['message'] = 'Faltan datos (emisor, receptor o contenido).';
    echo json_encode($response);
    exit;
}
// Asegurar que los IDs son números enteros válidos
$id_emisor = (int) $id_emisor;
$id_receptor = (int) $id_receptor;


if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    $response['message'] = 'Error de conexión a la base de datos.';
    echo json_encode($response);
    exit;
}

try {
    // 3. VERIFICACIÓN DE INTEGRIDAD (CRÍTICO: Asegura que ambos IDs existan en Cuenta)
    $sql_check = "SELECT id_cuenta FROM Cuenta WHERE id_cuenta = ? OR id_cuenta = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $id_emisor, $id_receptor);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $stmt_check->close();
    
    // Si recuperamos menos de 2 filas, uno de los IDs es inválido o no existe.
    if ($result_check->num_rows < 2) {
        throw new Exception("Error de integridad: El ID del emisor o del receptor no existe en la tabla Cuenta. (Target ID enviado: {$id_receptor})");
    }

    // 4. INSERTAR EL MENSAJE
    $sql_insert = "INSERT INTO Mensaje (id_emisor, id_receptor, contenido) VALUES (?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    
    // Tipos: iis
    $stmt_insert->bind_param("iis", $id_emisor, $id_receptor, $contenido);
    
    if (!$stmt_insert->execute()) {
        throw new Exception("Error al ejecutar la inserción: " . $stmt_insert->error);
    }
    $stmt_insert->close();

    $response['success'] = true;
    $response['message'] = 'Mensaje enviado correctamente.';

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en send_message.php: " . $e->getMessage());
    // Devolvemos el mensaje de la excepción para que el usuario pueda ver el ID inválido.
    $response['message'] = 'Error de servidor: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>