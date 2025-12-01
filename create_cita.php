<?php
// nandodm/servinet/ServiNet-e728ea1c53d2fa493dc4a4edd362433270b9cb26/api/create_cita.php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al solicitar la cita.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// 1. VERIFICACIÓN DE SESIÓN Y ROL (Solo clientes pueden agendar)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['rol'] ?? '') !== 'cliente') {
    $response['message'] = 'Acceso denegado. Debes iniciar sesión como cliente para agendar citas.';
    echo json_encode($response);
    exit;
}

$id_cliente = $_SESSION['id_cuenta'] ?? 0;

// 2. OBTENER Y VALIDAR DATOS DE ENTRADA
$id_servicio = $_POST['id_servicio'] ?? 0;
$fecha = $_POST['fecha'] ?? '';
$hora = $_POST['hora'] ?? '';
$comentario = $_POST['comentario'] ?? '';

if (empty($id_cliente) || empty($id_servicio) || empty($fecha) || empty($hora)) {
    $response['message'] = 'Faltan datos obligatorios (cliente, servicio, fecha u hora).';
    echo json_encode($response);
    exit;
}

// Combinar fecha y hora para el formato DATETIME de MySQL
$fecha_hora = $fecha . ' ' . $hora . ':00'; // Asumiendo hora en HH:MM

// 3. INICIAR TRANSACCIÓN Y REGISTRAR CITA
$conn->begin_transaction();

try {
    // A. Verificar que el ID del servicio exista y obtener el id_profesional asociado.
    $sql_service = "SELECT id_profesional, precio FROM Servicio WHERE id_servicio = ?";
    $stmt_service = $conn->prepare($sql_service);
    $stmt_service->bind_param("i", $id_servicio);
    $stmt_service->execute();
    $result_service = $stmt_service->get_result();
    
    if ($result_service->num_rows === 0) {
        throw new Exception("Servicio no encontrado.");
    }
    $service_data = $result_service->fetch_assoc();
    $id_profesional = $service_data['id_profesional'];
    $precio_base = $service_data['precio'];
    $stmt_service->close();
    
    
    // B. Insertar la Cita (Estado por defecto: 'pendiente')
    $sql_cita = "
        INSERT INTO Cita (id_cliente, id_servicio, fecha_hora, estado, comentario_cliente) 
        VALUES (?, ?, ?, 'pendiente', ?)
    ";
    $stmt_cita = $conn->prepare($sql_cita);
    $stmt_cita->bind_param("iiss", $id_cliente, $id_servicio, $fecha_hora, $comentario);
    
    if (!$stmt_cita->execute()) {
        throw new Exception("Error al insertar la cita: " . $stmt_cita->error);
    }
    $id_nueva_cita = $conn->insert_id;
    $stmt_cita->close();
    
    // C. SIMULACIÓN DE PAGO INICIAL (Se asume que la cita requiere un pago)
    // En una aplicación real, esto se haría DENTRO de un flujo de pago con un proveedor (Stripe/PayPal),
    // pero para fines de DB, registramos el pago inicial como 'pendiente' o 'procesando'.
    
    $monto_estimado = $precio_base; // Usamos el precio base como monto inicial
    $metodo_pago = 'Pendiente';
    $estado_pago = 'pendiente'; 
    
    $sql_pago = "
        INSERT INTO Pago (id_cita, monto, metodo_pago, estado_pago) 
        VALUES (?, ?, ?, ?)
    ";
    $stmt_pago = $conn->prepare($sql_pago);
    $stmt_pago->bind_param("idss", $id_nueva_cita, $monto_estimado, $metodo_pago, $estado_pago);
    
    if (!$stmt_pago->execute()) {
        throw new Exception("Error al registrar el pago inicial: " . $stmt_pago->error);
    }
    $id_nuevo_pago = $conn->insert_id;
    $stmt_pago->close();
    
    // D. SIMULACIÓN DE TRANSACCIÓN SEGURA (ESCROW)
    // Esto es crucial para la seguridad del pago. Se registra el monto a retener.
    $sql_escrow = "
        INSERT INTO TransaccionSegura (id_pago, id_cliente, id_profesional, monto, estado) 
        VALUES (?, ?, ?, ?, 'retenido')
    ";
    // Nota: Necesitamos el ID de cuenta del profesional para TransaccionSegura. 
    // Lo obtenemos de PerfilProfesional para asegurar que sea el id_cuenta (columna id_profesional en TransaccionSegura apunta a Cuenta.id_cuenta).
    $sql_prof_cuenta = "SELECT id_cuenta FROM PerfilProfesional WHERE id_profesional = ?";
    $stmt_prof_cuenta = $conn->prepare($sql_prof_cuenta);
    $stmt_prof_cuenta->bind_param("i", $id_profesional);
    $stmt_prof_cuenta->execute();
    $prof_cuenta_id = $stmt_prof_cuenta->get_result()->fetch_assoc()['id_cuenta'];
    $stmt_prof_cuenta->close();
    
    $stmt_escrow = $conn->prepare($sql_escrow);
    // Tipos: iidd (id_pago, id_cliente, id_profesional, monto)
    $stmt_escrow->bind_param("iiid", $id_nuevo_pago, $id_cliente, $prof_cuenta_id, $monto_estimado); 
    
    if (!$stmt_escrow->execute()) {
        throw new Exception("Error al crear el registro de Escrow: " . $stmt_escrow->error);
    }
    $stmt_escrow->close();


    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = '¡Solicitud de cita enviada! El profesional revisará tu solicitud pronto.';
    $response['id_cita'] = $id_nueva_cita;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error al crear cita: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Fallo en la transacción de agendamiento: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>