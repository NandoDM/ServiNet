<?php
// nandodm/servinet/ServiNet-35fb085a9f887b792b3d4ac23d22f72cd494ceec/api/get_client_history.php
session_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al obtener historial.', 'history' => []];

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['rol'] ?? '') !== 'cliente') {
    $response['message'] = 'Acceso denegado. Se requiere ser Cliente autenticado.';
    echo json_encode($response);
    exit;
}

$id_cliente = $_SESSION['id_cuenta'] ?? 0;

try {
    
    // Consulta compleja para obtener todos los datos necesarios para el historial.
    $sql = "
        SELECT 
            C.id_cita, C.fecha_hora, C.estado, C.fecha_creacion,
            S.nombre_servicio,
            P.monto, P.estado_pago,
            CP.id_cuenta AS prof_id_cuenta, 
            PP.id_profesional, /* 🔑 AÑADIDO: ID del perfil profesional para las reseñas */
            CP.nombre AS prof_nombre, CP.apellido_paterno AS prof_apellido,
            PP.calificacion_promedio
        FROM Cita C
        JOIN Servicio S ON C.id_servicio = S.id_servicio
        LEFT JOIN Pago P ON C.id_cita = P.id_cita 
        JOIN PerfilProfesional PP ON S.id_profesional = PP.id_profesional
        JOIN Cuenta CP ON PP.id_cuenta = CP.id_cuenta -- Cuenta del Profesional
        WHERE C.id_cliente = ?
        ORDER BY C.fecha_creacion DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        
        $monto_display = number_format((float)$row['monto'], 2, '.', ',');
        
        $history[] = [
            'id_cita' => $row['id_cita'],
            'servicio' => $row['nombre_servicio'],
            'fecha_contratacion' => date('d M, Y', strtotime($row['fecha_creacion'])),
            'fecha_servicio' => date('d M, Y H:i', strtotime($row['fecha_hora'])),
            'estado' => $row['estado'],
            'profesional_nombre' => $row['prof_nombre'] . ' ' . $row['prof_apellido'],
            'prof_id_cuenta' => $row['prof_id_cuenta'], 
            'prof_id' => $row['id_profesional'], /* 🔑 CAMBIO CLAVE */
            'monto' => $monto_display,
            'estado_pago' => $row['estado_pago'] ?? 'N/A', 
            'calificacion_prof' => $row['calificacion_promedio']
        ];
    }
    $stmt->close();

    $response['success'] = true;
    $response['message'] = 'Historial cargado.';
    $response['history'] = $history;

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_client_history.php: " . $e->getMessage());
    $response['message'] = 'Error interno del servidor: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>