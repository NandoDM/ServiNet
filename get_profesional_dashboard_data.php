<?php
// nandodm/servinet/ServiNet-e7a913c5251aedd806317b56fa60d612c9d7bd2f/api/get_profesional_dashboard_data.php
session_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al obtener datos del dashboard profesional.', 'data' => []];

// 1. VERIFICACIÓN DE SESIÓN Y ROL
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['rol'] ?? '') !== 'profesional') {
    $response['message'] = 'Acceso denegado. Se requiere ser Profesional autenticado.';
    echo json_encode($response);
    exit;
}

$id_cuenta = $_SESSION['id_cuenta'] ?? 0;

try {
    
    // Buscar id_profesional a partir de id_cuenta
    $sql_prof = "SELECT id_profesional, calificacion_promedio FROM PerfilProfesional WHERE id_cuenta = ?";
    $stmt_prof = $conn->prepare($sql_prof);
    $stmt_prof->bind_param("i", $id_cuenta);
    $stmt_prof->execute();
    $result_prof = $stmt_prof->get_result()->fetch_assoc();
    $id_profesional = $result_prof['id_profesional'] ?? 0;
    $calificacion_promedio = $result_prof['calificacion_promedio'] ?? '0.00';
    $stmt_prof->close();
    
    if ($id_profesional === 0) {
        throw new Exception("Perfil profesional no encontrado.");
    }
    
    // 2. CONTEO DE CITAS PENDIENTES/CONFIRMADAS
    $sql_citas = "SELECT COUNT(T1.id_cita) AS citas_pendientes
                  FROM Cita T1
                  JOIN Servicio T2 ON T1.id_servicio = T2.id_servicio
                  WHERE T2.id_profesional = ? AND T1.estado IN ('pendiente', 'confirmada')";
    $stmt_citas = $conn->prepare($sql_citas);
    $stmt_citas->bind_param("i", $id_profesional);
    $stmt_citas->execute();
    $result_citas = $stmt_citas->get_result()->fetch_assoc();
    $citas_pendientes = $result_citas['citas_pendientes'];
    $stmt_citas->close();

    // 3. CÁLCULO DE FONDOS POR LIBERAR (Retenidos en Escrow)
    // Buscamos el monto en TransaccionSegura que aún esté 'retenido' y provenga de los servicios del profesional
    $sql_fondos = "
        SELECT SUM(TS.monto) AS total_retenido
        FROM TransaccionSegura TS
        JOIN Pago P ON TS.id_pago = P.id_pago
        JOIN Cita C ON P.id_cita = C.id_cita
        JOIN Servicio S ON C.id_servicio = S.id_servicio
        WHERE S.id_profesional = ? AND TS.estado = 'retenido'
    ";
    $stmt_fondos = $conn->prepare($sql_fondos);
    $stmt_fondos->bind_param("i", $id_profesional);
    $stmt_fondos->execute();
    $result_fondos = $stmt_fondos->get_result()->fetch_assoc();
    $total_retenido = $result_fondos['total_retenido'] ?? 0.00;
    $stmt_fondos->close();
    
    // 4. ÚLTIMAS 5 CITAS AGENDADAS (PENDIENTES/CONFIRMADAS)
    $sql_latest = "
        SELECT 
            Cita.id_cita, Cita.fecha_hora, Cita.estado,
            Servicio.nombre_servicio,
            Cuenta.nombre AS cliente_nombre, Cuenta.apellido_paterno AS cliente_apellido
        FROM Cita 
        JOIN Servicio ON Cita.id_servicio = Servicio.id_servicio
        JOIN Cuenta ON Cita.id_cliente = Cuenta.id_cuenta
        WHERE Servicio.id_profesional = ? AND Cita.estado IN ('pendiente', 'confirmada')
        ORDER BY Cita.fecha_hora ASC
        LIMIT 5
    ";
    $stmt_latest = $conn->prepare($sql_latest);
    $stmt_latest->bind_param("i", $id_profesional);
    $stmt_latest->execute();
    $result_latest = $stmt_latest->get_result();
    
    $latest_appointments = [];
    while ($row = $result_latest->fetch_assoc()) {
        $latest_appointments[] = [
            'id_cita' => $row['id_cita'],
            'servicio' => $row['nombre_servicio'],
            'fecha_hora' => date('d M, Y H:i', strtotime($row['fecha_hora'])),
            'estado' => $row['estado'],
            'cliente' => $row['cliente_nombre'] . ' ' . $row['cliente_apellido']
        ];
    }
    $stmt_latest->close();

    $response['success'] = true;
    $response['message'] = 'Datos del dashboard cargados.';
    $response['data'] = [
        'nombre_usuario' => $_SESSION['nombre'] ?? 'Profesional',
        'citas_pendientes' => (int)$citas_pendientes,
        'fondos_retenidos' => number_format((float)$total_retenido, 2, '.', ','),
        'calificacion' => number_format((float)$calificacion_promedio, 2, '.', ','),
        'latest_appointments' => $latest_appointments
    ];

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_profesional_dashboard_data.php: " . $e->getMessage());
    $response['message'] = 'Error interno del servidor: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>