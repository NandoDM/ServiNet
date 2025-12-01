<?php
// nandodm/servinet/ServiNet-e7a913c5251aedd806317b56fa60d612c9d7bd2f/api/update_disponibilidad.php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST, GET");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al actualizar la disponibilidad.'];

// 1. VERIFICACIÓN DE SESIÓN Y ROL
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['rol'] ?? '') !== 'profesional') {
    $response['message'] = 'Acceso denegado. Se requiere ser Profesional autenticado.';
    echo json_encode($response);
    exit;
}

$id_cuenta = $_SESSION['id_cuenta'] ?? 0;

try {
    // 2. BUSCAR id_profesional (Necesario para acceder a PerfilProfesional)
    $sql_prof = "SELECT id_profesional, disponibilidad FROM PerfilProfesional WHERE id_cuenta = ?";
    $stmt_prof = $conn->prepare($sql_prof);
    $stmt_prof->bind_param("i", $id_cuenta);
    $stmt_prof->execute();
    $result_prof = $stmt_prof->get_result();
    
    if ($result_prof->num_rows === 0) {
        throw new Exception("Perfil profesional no encontrado.");
    }
    $prof_data = $result_prof->fetch_assoc();
    $id_profesional = $prof_data['id_profesional'];
    $current_status = $prof_data['disponibilidad'];
    $stmt_prof->close();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Modo lectura: Devolver el estado actual
        $response['success'] = true;
        $response['message'] = 'Estado de disponibilidad cargado.';
        $response['status'] = $current_status; // Ej: 'DISPONIBLE' o 'NO DISPONIBLE'
        echo json_encode($response);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Modo escritura: Obtener el nuevo estado y actualizar
        $new_status_raw = $_POST['status'] ?? 'off'; // 'on' o 'off'
        // Mapeamos el toggle a los valores que usamos en la BD (VARCHAR)
        $new_status = ($new_status_raw === 'on') ? 'DISPONIBLE' : 'NO DISPONIBLE';

        $sql_update = "UPDATE PerfilProfesional SET disponibilidad = ? WHERE id_profesional = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("si", $new_status, $id_profesional);
        
        if (!$stmt_update->execute()) {
            throw new Exception("Error al actualizar el estado: " . $stmt_update->error);
        }
        $stmt_update->close();
        
        $response['success'] = true;
        $response['message'] = "Disponibilidad actualizada a: {$new_status}";
        $response['status'] = $new_status;
    }

} catch (Exception $e) {
    error_log("Error de disponibilidad: " . $e->getMessage());
    $response['message'] = 'Fallo en la operación: ' . $e->getMessage();
}

if (isset($conn)) $conn->close();
echo json_encode($response);
?>