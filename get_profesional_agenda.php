<?php
// nandodm/servinet/ServiNet-e7a913c5251aedd806317b56fa60d612c9d7bd2f/api/get_profesional_agenda.php
session_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al obtener agenda.', 'agenda' => []];

// 1. VERIFICACIÓN DE SESIÓN Y ROL
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['rol'] ?? '') !== 'profesional') {
    $response['message'] = 'Acceso denegado. Se requiere ser Profesional autenticado.';
    echo json_encode($response);
    exit;
}

$id_cuenta = $_SESSION['id_cuenta'] ?? 0;

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    $response['message'] = 'Error de conexión a la base de datos.';
    echo json_encode($response);
    exit;
}

try {
    
    // 2. Buscar id_profesional.
    $sql_prof = "SELECT id_profesional FROM PerfilProfesional WHERE id_cuenta = ?";
    $stmt_prof = $conn->prepare($sql_prof);
    $stmt_prof->bind_param("i", $id_cuenta);
    $stmt_prof->execute();
    $result_prof = $stmt_prof->get_result();

    if ($result_prof->num_rows === 0) {
        throw new Exception("Perfil de profesional no encontrado para esta cuenta.");
    }
    $prof_data = $result_prof->fetch_assoc();
    $id_profesional = $prof_data['id_profesional'];
    $stmt_prof->close();


    // 3. Obtener las citas asociadas a los servicios de este profesional
    $sql = "
        SELECT 
            C.id_cita, C.fecha_hora, C.estado, C.fecha_creacion, C.comentario_cliente,
            S.nombre_servicio,
            CU.id_cuenta AS cliente_id_cuenta, /* 🚨 Campo agregado para el chat 🚨 */
            CU.nombre AS cliente_nombre, CU.apellido_paterno AS cliente_apellido,
            P.monto
        FROM Cita C
        JOIN Servicio S ON C.id_servicio = S.id_servicio
        JOIN PerfilProfesional PP ON S.id_profesional = PP.id_profesional
        JOIN Cuenta CU ON C.id_cliente = CU.id_cuenta
        LEFT JOIN Pago P ON C.id_cita = P.id_cita
        WHERE PP.id_profesional = ?
        ORDER BY C.fecha_hora ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_profesional);
    $stmt->execute();
    $result = $stmt->get_result();

    $agenda = [];
    while ($row = $result->fetch_assoc()) {
        
        $monto_display = number_format((float)($row['monto'] ?? 0), 2, '.', ',');
        
        $agenda[] = [
            'id_cita' => $row['id_cita'],
            'servicio' => $row['nombre_servicio'],
            'fecha_hora' => date('d M, Y H:i', strtotime($row['fecha_hora'])),
            'estado' => $row['estado'],
            'cliente_nombre' => $row['cliente_nombre'] . ' ' . $row['cliente_apellido'],
            'cliente_id' => $row['cliente_id_cuenta'], /* 🚨 Dato inyectado en el JSON 🚨 */
            'comentario_cliente' => $row['comentario_cliente'],
            'monto' => $monto_display,
            'fecha_creacion' => date('d M, Y', strtotime($row['fecha_creacion']))
        ];
    }
    $stmt->close();
    
    $response['success'] = true;
    $response['message'] = 'Agenda cargada exitosamente.';
    $response['agenda'] = $agenda;

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_profesional_agenda.php: " . $e->getMessage());
    $response['message'] = 'Error de servidor: ' . $e->getMessage();
}

if (isset($conn)) $conn->close();
echo json_encode($response);
?>