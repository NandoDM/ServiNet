<?php
// nandodm/servinet/ServiNet-e728ea1c53d2fa493dc4a4edd362433270b9cb26/api/get_chat_data.php
session_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al obtener datos del chat.', 'target' => null, 'messages' => []];

// 1. VERIFICACIÓN DE SESIÓN (Se asume que el usuario está logueado)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $response['message'] = 'Acceso denegado. Se requiere autenticación.';
    echo json_encode($response);
    exit;
}

$current_user_id = $_SESSION['id_cuenta'] ?? 0;
// El target_id viene del parámetro 'prof' o 'client' en la URL, que se pasa al JS.
$target_id = $_GET['target_id'] ?? 0; 

if (!is_numeric($target_id) || $target_id <= 0) {
    $response['message'] = 'ID de contacto no proporcionado o inválido.';
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
    // 2. OBTENER DATOS DEL USUARIO CONTACTADO (TARGET)
    $sql_target = "
        SELECT 
            nombre, apellido_paterno, apellido_materno
        FROM Cuenta 
        WHERE id_cuenta = ?
    ";
    $stmt_target = $conn->prepare($sql_target);
    $stmt_target->bind_param("i", $target_id);
    $stmt_target->execute();
    $result_target = $stmt_target->get_result();

    if ($result_target->num_rows === 0) {
        throw new Exception("Contacto no encontrado.");
    }
    
    $target_data = $result_target->fetch_assoc();
    $target_full_name = $target_data['nombre'] . ' ' . $target_data['apellido_paterno'];
    $target_initials = $target_data['nombre'][0] . $target_data['apellido_paterno'][0];
    $stmt_target->close();

    // 3. OBTENER MENSAJES ENTRE LOS DOS USUARIOS
    $sql_messages = "
        SELECT 
            id_emisor, contenido, fecha_envio
        FROM Mensaje
        WHERE (id_emisor = ? AND id_receptor = ?) 
           OR (id_emisor = ? AND id_receptor = ?)
        ORDER BY fecha_envio ASC
    ";
    $stmt_messages = $conn->prepare($sql_messages);
    // Asumimos que $current_user_id (el logueado) y $target_id son los IDs de cuenta.
    $stmt_messages->bind_param("iiii", $current_user_id, $target_id, $target_id, $current_user_id);
    $stmt_messages->execute();
    $result_messages = $stmt_messages->get_result();

    $messages = [];
    while ($row = $result_messages->fetch_assoc()) {
        $messages[] = [
            'is_emisor' => ($row['id_emisor'] == $current_user_id),
            'contenido' => $row['contenido'],
            'fecha_envio' => date('H:i A', strtotime($row['fecha_envio']))
        ];
    }
    $stmt_messages->close();


    $response['success'] = true;
    $response['message'] = 'Datos del chat cargados.';
    $response['target'] = [
        'id' => $target_id,
        'full_name' => $target_full_name,
        'initials' => strtoupper($target_initials),
    ];
    $response['messages'] = $messages;

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_chat_data.php: " . $e->getMessage());
    $response['message'] = 'Error de servidor: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>