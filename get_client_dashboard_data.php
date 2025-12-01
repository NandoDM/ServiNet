<?php
// nandodm/servinet/ServiNet-35fb085a9f887b792b3d4ac23d22f72cd494ceec/api/get_client_dashboard_data.php
session_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al obtener datos del dashboard.', 'data' => []];

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['rol'] ?? '') !== 'cliente') {
    $response['message'] = 'Acceso denegado. Se requiere ser Cliente autenticado.';
    echo json_encode($response);
    exit;
}

$id_cuenta = $_SESSION['id_cuenta'] ?? 0;

try {
    
    // 1. CONTEO DE CITAS PENDIENTES/CONFIRMADAS
    $sql_citas = "SELECT COUNT(id_cita) AS citas_pendientes
                  FROM Cita
                  WHERE id_cliente = ? AND estado IN ('pendiente', 'confirmada')";
    $stmt_citas = $conn->prepare($sql_citas);
    $stmt_citas->bind_param("i", $id_cuenta);
    $stmt_citas->execute();
    $result_citas = $stmt_citas->get_result()->fetch_assoc();
    $citas_pendientes = $result_citas['citas_pendientes'];
    $stmt_citas->close();

    // 2. OBTENER ÚLTIMAS 5 NOTIFICACIONES
    $sql_notif = "SELECT tipo, contenido, fecha_envio, leido
                  FROM Notificacion
                  WHERE id_cuenta = ?
                  ORDER BY fecha_envio DESC
                  LIMIT 5";
    $stmt_notif = $conn->prepare($sql_notif);
    $stmt_notif->bind_param("i", $id_cuenta);
    $stmt_notif->execute();
    $result_notif = $stmt_notif->get_result();
    
    $notificaciones = [];
    while ($row = $result_notif->fetch_assoc()) {
        $notificaciones[] = $row;
    }
    $stmt_notif->close();
    
    // 3. OBTENER NOMBRE DEL USUARIO (para el saludo, aunque app.js ya lo hace)
    $nombre = $_SESSION['nombre'] ?? 'Cliente';

    $response['success'] = true;
    $response['message'] = 'Datos del dashboard cargados.';
    $response['data'] = [
        'nombre_usuario' => $nombre,
        'citas_pendientes' => $citas_pendientes,
        'notificaciones' => $notificaciones
    ];

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_client_dashboard_data.php: " . $e->getMessage());
    $response['message'] = 'Error interno del servidor: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>