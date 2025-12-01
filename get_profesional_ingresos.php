<?php
// nandodm/servinet/ServiNet-e7a913c5251aedd806317b56fa60d612c9d7bd2f/api/get_profesional_ingresos.php
session_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al obtener ingresos.', 'data' => []];

// 1. VERIFICACIÓN DE SESIÓN Y ROL
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['rol'] ?? '') !== 'profesional') {
    $response['message'] = 'Acceso denegado. Se requiere ser Profesional autenticado.';
    echo json_encode($response);
    exit;
}

$id_cuenta = $_SESSION['id_cuenta'] ?? 0;

try {
    
    // 2. Buscar id_profesional y calificacion (Necesario para el JOIN y el dashboard)
    $sql_prof = "SELECT id_profesional FROM PerfilProfesional WHERE id_cuenta = ?";
    $stmt_prof = $conn->prepare($sql_prof);
    $stmt_prof->bind_param("i", $id_cuenta);
    $stmt_prof->execute();
    $result_prof = $stmt_prof->get_result();
    
    if ($result_prof->num_rows === 0) {
        throw new Exception("Perfil profesional no encontrado.");
    }
    $prof_data = $result_prof->fetch_assoc();
    $id_profesional = $prof_data['id_profesional'];
    $stmt_prof->close();


    // 3. CÁLCULO DE SALDOS (REQUERIMIENTO DE DATOS)
    
    // 3.1. Fondos Retenidos (Escrow - TransaccionSegura)
    $sql_retenido = "
        SELECT SUM(TS.monto) AS total_retenido
        FROM TransaccionSegura TS
        JOIN Cuenta C ON TS.id_profesional = C.id_cuenta
        WHERE TS.id_profesional = ? AND TS.estado = 'retenido'
    ";
    $stmt_retenido = $conn->prepare($sql_retenido);
    $stmt_retenido->bind_param("i", $id_cuenta);
    $stmt_retenido->execute();
    $total_retenido = $stmt_retenido->get_result()->fetch_assoc()['total_retenido'] ?? 0.00;
    $stmt_retenido->close();

    // 3.2. Saldo Liberado (Simulado: Asumiremos que el saldo actual es la suma de 'liberado' menos los retiros)
    // Para simplificar, asumiremos que "Saldo Actual" es el total liberado que el profesional NO ha retirado.
    // Como no tienes tabla de 'retiros', SIMULAMOS un saldo inicial y un retiro fijo para la demo.
    // Una implementación real requeriría una tabla de `Retiros`.
    $total_liberado = 4250.00; // Simulación de pagos liberados
    $total_retirado = 2000.00; // Simulación de retiros
    $saldo_actual = $total_liberado - $total_retirado;


    // 4. HISTORIAL DE TRANSACCIONES (Últimos 10, combinando Liberados y Retenidos)
    $sql_historial = "
        SELECT 
            TS.monto, TS.fecha_liberacion, TS.estado, TS.fecha_creacion,
            S.nombre_servicio,
            CC.nombre AS cliente_nombre
        FROM TransaccionSegura TS
        JOIN Cuenta CP ON TS.id_profesional = CP.id_cuenta
        LEFT JOIN Pago P ON TS.id_pago = P.id_pago
        LEFT JOIN Cita C ON P.id_cita = C.id_cita
        LEFT JOIN Servicio S ON C.id_servicio = S.id_servicio
        LEFT JOIN Cuenta CC ON TS.id_cliente = CC.id_cuenta
        WHERE TS.id_profesional = ?
        ORDER BY TS.fecha_creacion DESC
        LIMIT 10
    ";
    $stmt_historial = $conn->prepare($sql_historial);
    $stmt_historial->bind_param("i", $id_cuenta);
    $stmt_historial->execute();
    $result_historial = $stmt_historial->get_result();

    $historial = [];
    while ($row = $result_historial->fetch_assoc()) {
        $tipo_transaccion = ($row['estado'] == 'retenido') ? 'Servicio Contratado (Pendiente Liberar)' : 
                            (($row['estado'] == 'liberado') ? 'Pago Liberado por Cliente' : $row['estado']);
        $nombre_item = $row['nombre_servicio'] ?? 'Retiro/Reembolso'; // Si no hay servicio (ej: Retiro)

        $historial[] = [
            'tipo' => $tipo_transaccion,
            'nombre_item' => $nombre_item,
            'cliente' => $row['cliente_nombre'] ?? 'N/A',
            'monto' => number_format((float)($row['monto'] ?? 0), 2, '.', ','),
            'estado' => $row['estado'],
            'fecha' => date('d M, Y', strtotime($row['fecha_creacion']))
        ];
    }
    $stmt_historial->close();
    
    // 5. RESPUESTA FINAL
    $response['success'] = true;
    $response['message'] = 'Datos de ingresos cargados.';
    $response['data'] = [
        'saldo_actual' => number_format($saldo_actual, 2, '.', ','),
        'total_retenido' => number_format((float)$total_retenido, 2, '.', ','),
        'retiros_mes' => number_format($total_retirado, 2, '.', ','), // Usando el valor simulado
        'historial_transacciones' => $historial
    ];

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_profesional_ingresos.php: " . $e->getMessage());
    $response['message'] = 'Error de servidor: ' . $e->getMessage();
}

if (isset($conn)) $conn->close();
echo json_encode($response);
?>