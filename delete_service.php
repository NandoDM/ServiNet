<?php
// nandodm/servinet/ServiNet-e7a913c5251aedd806317b56fa60d612c9d7bd2f/api/delete_service.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al eliminar el servicio.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// 1. OBTENER ID DEL SERVICIO Y ID DE CUENTA DEL PROFESIONAL
$id_servicio = $_POST['service_id'] ?? 0;
// Obtenemos id_cuenta para una verificación de seguridad
$id_cuenta = $_POST['id_cuenta'] ?? 0; 

if (empty($id_servicio) || empty($id_cuenta)) {
    $response['message'] = 'Datos de servicio o cuenta incompletos.';
    echo json_encode($response);
    exit;
}

$conn->begin_transaction();

try {
    // 2. VERIFICACIÓN DE PROPIEDAD Y OBTENCIÓN DE ID_PROFESIONAL
    // Nos aseguramos que el usuario logueado (id_cuenta) es dueño del servicio
    $sql_check = "
        SELECT PP.id_profesional 
        FROM PerfilProfesional PP
        JOIN Servicio S ON PP.id_profesional = S.id_profesional
        WHERE S.id_servicio = ? AND PP.id_cuenta = ?
    ";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $id_servicio, $id_cuenta);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 0) {
        throw new Exception("Acceso denegado. No eres el propietario de este servicio o el servicio no existe.");
    }
    $stmt_check->close();

    // 3. ELIMINAR EL SERVICIO (ON DELETE CASCADE debe manejar las citas asociadas, pero si no está configurado,
    // esto podría fallar. Asumiremos que el CASCADE está configurado correctamente.)
    $sql_delete = "DELETE FROM Servicio WHERE id_servicio = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $id_servicio);

    if (!$stmt_delete->execute()) {
        throw new Exception("Error al ejecutar la eliminación: " . $stmt_delete->error);
    }
    $stmt_delete->close();
    
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = 'Servicio eliminado permanentemente.';

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error en delete_service.php: " . $e->getMessage());
    $response['message'] = 'Fallo en la eliminación: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);