<?php
// nandodm/servinet/ServiNet-e728ea1c53d2fa493dc4a4edd362433270b9cb26/api/update_cita_status.php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al actualizar el estado de la cita.'];

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

$id_cuenta_prof = $_SESSION['id_cuenta'] ?? 0;

// 2. OBTENER Y VALIDAR DATOS DE ENTRADA
$id_cita = $_POST['cita_id'] ?? 0;
$action = $_POST['action'] ?? ''; // 'confirmar', 'rechazar', 'finalizar', 'cancelar'

if (empty($id_cita) || empty($action)) {
    $response['message'] = 'Faltan datos (ID de cita o acción).';
    echo json_encode($response);
    exit;
}

// Mapeo de acciones a estados de la BD
switch ($action) {
    case 'confirmar':
        $new_status = 'confirmada';
        break;
    case 'rechazar':
    case 'cancelar':
        $new_status = 'cancelada';
        break;
    case 'finalizar':
        $new_status = 'finalizada';
        break;
    default:
        $response['message'] = 'Acción inválida.';
        echo json_encode($response);
        exit;
}

$conn->begin_transaction();

try {
    // 3. VERIFICACIÓN CRÍTICA: Asegurar que el profesional es el dueño de la cita.
    $sql_check = "
        SELECT PP.id_cuenta
        FROM Cita C
        JOIN Servicio S ON C.id_servicio = S.id_servicio
        JOIN PerfilProfesional PP ON S.id_profesional = PP.id_profesional
        WHERE C.id_cita = ? AND PP.id_cuenta = ?
    ";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $id_cita, $id_cuenta_prof);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 0) {
        throw new Exception("Acceso denegado. No eres el profesional asociado a esta cita o la cita no existe.");
    }
    $stmt_check->close();
    
    // 4. ACTUALIZAR EL ESTADO DE LA CITA
    $sql_update = "UPDATE Cita SET estado = ? WHERE id_cita = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("si", $new_status, $id_cita);

    if (!$stmt_update->execute()) {
        throw new Exception("Error al actualizar el estado de la cita: " . $stmt_update->error);
    }
    $stmt_update->close();
    
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = "Cita #{$id_cita} marcada como '{$new_status}' exitosamente.";
    $response['new_status'] = $new_status;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error en update_cita_status.php: " . $e->getMessage());
    $response['message'] = 'Fallo en la actualización: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>