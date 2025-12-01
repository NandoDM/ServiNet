<?php
// nandodm/servinet/ServiNet-e728ea1c53d2fa493dc4a4edd362433270b9cb26/api/create_disputa.php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al registrar la disputa.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// 1. VERIFICACIÓN DE SESIÓN (Debe ser cliente o profesional)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $response['message'] = 'Acceso denegado. Se requiere autenticación.';
    echo json_encode($response);
    exit;
}

$id_cuenta = $_SESSION['id_cuenta'] ?? 0;
$rol = $_SESSION['rol'] ?? 'usuario'; // 'cliente' o 'profesional'

// 2. OBTENER Y VALIDAR DATOS DE ENTRADA
$id_cita = $_POST['cita_id'] ?? 0;
$motivo = $_POST['motivo'] ?? '';
$iniciada_por = $_POST['iniciada_por'] ?? ''; // Debe ser 'cliente' o 'profesional'

if (empty($id_cita) || empty($motivo) || ($iniciada_por !== 'cliente' && $iniciada_por !== 'profesional')) {
    $response['message'] = 'Faltan datos obligatorios (cita, motivo, o rol de inicio).';
    echo json_encode($response);
    exit;
}

$conn->begin_transaction();

try {
    // 3. ENCONTRAR TRANSACCIONES Y VERIFICAR PROPIEDAD
    $sql_propiedad = "
        SELECT 
            TS.id_transaccion, TS.estado AS escrow_estado,
            Cita.id_cliente, CU_PROF.id_cuenta AS prof_cuenta_id
        FROM Cita
        JOIN Pago P ON Cita.id_cita = P.id_cita
        JOIN TransaccionSegura TS ON P.id_pago = TS.id_pago
        JOIN Servicio S ON Cita.id_servicio = S.id_servicio
        JOIN PerfilProfesional PP ON S.id_profesional = PP.id_profesional
        JOIN Cuenta CU_PROF ON PP.id_cuenta = CU_PROF.id_cuenta /* Cuenta del profesional */
        WHERE Cita.id_cita = ? 
    ";
    $stmt_propiedad = $conn->prepare($sql_propiedad);
    $stmt_propiedad->bind_param("i", $id_cita);
    $stmt_propiedad->execute();
    $data_transaccion = $stmt_propiedad->get_result()->fetch_assoc();
    $stmt_propiedad->close();

    if (!$data_transaccion) {
        throw new Exception("Transacción de Escrow no encontrada para esta cita.");
    }

    $es_cliente = ($id_cuenta == $data_transaccion['id_cliente']);
    $es_profesional = ($id_cuenta == $data_transaccion['prof_cuenta_id']);

    // 🚨 VERIFICACIÓN DE ACCESO MEJORADA 🚨
    if (!$es_cliente && !$es_profesional) {
        throw new Exception("Acceso denegado. No eres el cliente ni el profesional de esta transacción.");
    }
    
    // 5. ACTUALIZAR ESTADO DE ESCROW A 'en_disputa'
    $id_transaccion = $data_transaccion['id_transaccion'];
    
    $sql_update_escrow = "UPDATE TransaccionSegura SET estado = 'en_disputa', comentario = ? WHERE id_transaccion = ?";
    $stmt_update_escrow = $conn->prepare($sql_update_escrow);
    $comentario_disputa = "Disputa iniciada por {$iniciada_por}: {$motivo}";
    $stmt_update_escrow->bind_param("si", $comentario_disputa, $id_transaccion);
    $stmt_update_escrow->execute();
    $stmt_update_escrow->close();

    // 6. INSERTAR REGISTRO EN LA TABLA DISPUTA
    $sql_disputa = "
        INSERT INTO Disputa (id_transaccion, iniciada_por, motivo, estado) 
        VALUES (?, ?, ?, 'abierta')
    ";
    $stmt_disputa = $conn->prepare($sql_disputa);
    $stmt_disputa->bind_param("iss", $id_transaccion, $iniciada_por, $motivo);
    $stmt_disputa->execute();
    $stmt_disputa->close();


    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = "Disputa #{$id_transaccion} registrada. El administrador iniciará la mediación.";
    $response['id_transaccion'] = $id_transaccion;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error de disputa: " . $e->getMessage());
    http_response_code(500);
    // 🚨 Devolvemos solo un mensaje genérico por seguridad 🚨
    $response['message'] = 'Fallo en la transacción de disputa. Por favor, intenta de nuevo.';
}

$conn->close();
echo json_encode($response);
?>