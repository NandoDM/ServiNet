<?php
// nandodm/servinet/ServiNet-e728ea1c53d2fa493dc4a4edd362433270b9cb26/api/get_factura_data.php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al obtener datos de la factura.', 'data' => null];

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $response['message'] = 'Acceso denegado. Se requiere autenticación.';
    echo json_encode($response);
    exit;
}

$id_cita = $_GET['cita_id'] ?? 0;
$id_cuenta_solicitante = $_SESSION['id_cuenta'] ?? 0; // Cliente logueado

if (!is_numeric($id_cita) || $id_cita <= 0) {
    $response['message'] = 'ID de cita inválido.';
    echo json_encode($response);
    exit;
}

try {
    // 1. CONSULTA PRINCIPAL: Obtener datos de Cita, Pago, Transacción, Cliente y Profesional.
    $sql = "
        SELECT 
            C.id_cita, C.fecha_creacion, C.estado AS cita_estado, 
            S.nombre_servicio, S.precio AS tarifa_base,
            P.monto AS monto_pagado, P.estado_pago, P.fecha_pago,
            TS.estado AS escrow_estado,
            
            CU_C.nombre AS cliente_nombre, CU_C.apellido_paterno AS cliente_apellido, 
            CU_C.correo AS cliente_correo, CU_C.telefono AS cliente_telefono,
            
            CU_P.nombre AS prof_nombre, CU_P.apellido_paterno AS prof_apellido, 
            PP.especialidades, PP.id_profesional
        FROM Cita C
        JOIN Servicio S ON C.id_servicio = S.id_servicio
        JOIN Pago P ON C.id_cita = P.id_cita
        JOIN TransaccionSegura TS ON P.id_pago = TS.id_pago
        JOIN Cuenta CU_C ON C.id_cliente = CU_C.id_cuenta /* Cliente */
        JOIN PerfilProfesional PP ON S.id_profesional = PP.id_profesional
        JOIN Cuenta CU_P ON PP.id_cuenta = CU_P.id_cuenta /* Profesional */
        WHERE C.id_cita = ? AND C.id_cliente = ? /* Solo el cliente dueño puede ver la factura */
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id_cita, $id_cuenta_solicitante);
    $stmt->execute();
    $result = $stmt->get_result();
    $factura_data = $result->fetch_assoc();
    $stmt->close();

    if (!$factura_data) {
        throw new Exception("Factura no encontrada o no tienes permiso para verla.");
    }
    
    // 2. Cálculo de comisiones y totales
    $monto_total = (float)$factura_data['monto_pagado'];
    $comision_rate = 0.10; // 10% simulado
    $comision_servinet = $monto_total * $comision_rate;
    $subtotal = $monto_total - $comision_servinet;
    
    // 3. Verificación de estado
    if ($factura_data['estado_pago'] !== 'pagado') {
        throw new Exception("El pago de este servicio no ha sido liberado (Estado: {$factura_data['estado_pago']}).");
    }

    $response['success'] = true;
    $response['message'] = 'Datos de factura cargados.';
    $response['data'] = [
        'factura_id' => 'FAC-' . str_pad($id_cita, 5, '0', STR_PAD_LEFT),
        'fecha_emision' => date('d/m/Y H:i', strtotime($factura_data['fecha_pago'])),
        'estado_pago' => $factura_data['estado_pago'],
        
        'cliente' => [
            'nombre' => html_entity_decode("{$factura_data['cliente_nombre']} {$factura_data['cliente_apellido']}"),
            'correo' => $factura_data['cliente_correo'],
            'telefono' => $factura_data['cliente_telefono']
        ],
        
        'profesional' => [
            'nombre' => html_entity_decode("{$factura_data['prof_nombre']} {$factura_data['prof_apellido']}"),
            'especialidades' => $factura_data['especialidades']
        ],
        
        'servicio' => [
            'descripcion' => $factura_data['nombre_servicio'],
            'tarifa_base' => $monto_total,
            'monto_pagado' => $monto_total
        ],
        
        'totales' => [
            'subtotal' => $subtotal,
            'comision_servinet' => $comision_servinet,
            'total_final' => $monto_total
        ]
    ];

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error al obtener factura: " . $e->getMessage());
    $response['message'] = 'Error interno del servidor: ' . $e->getMessage();
}

if (isset($conn)) $conn->close();
echo json_encode($response);