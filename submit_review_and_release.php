<?php
// nandodm/servinet/ServiNet-e728ea1c53d2fa493dc4a4edd362433270b9cb26/api/submit_review_and_release.php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al procesar reseña y pago.'];

// 1. VERIFICACIÓN DE SESIÓN Y ROL
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['rol'] ?? '') !== 'cliente') {
    $response['message'] = 'Acceso denegado. Se requiere ser Cliente autenticado.';
    echo json_encode($response);
    exit;
}

$id_cliente = $_SESSION['id_cuenta'] ?? 0;

// 2. OBTENER Y VALIDAR DATOS DE ENTRADA
$id_cita = $_POST['cita_id'] ?? 0;
$id_profesional = $_POST['prof_id'] ?? 0; // ID del Perfil Profesional (PP.id_profesional)
$calificacion = $_POST['calificacion'] ?? 0;
$comentario = $_POST['comentario'] ?? '';

if (empty($id_cita) || empty($id_profesional) || $calificacion < 1 || $calificacion > 5) {
    $response['message'] = 'Faltan datos obligatorios o la calificación es inválida.';
    echo json_encode($response);
    exit;
}

$conn->begin_transaction();

try {
    // A. VERIFICACIÓN DE PERTENENCIA (CRÍTICO)
    // Asegurar que la cita sea del cliente logueado y esté en estado 'confirmada'
    $sql_check = "
        SELECT 
            C.estado, P.id_pago, TS.id_transaccion, TS.estado AS escrow_estado, CU_PROF.id_cuenta AS prof_cuenta_id
        FROM Cita C
        JOIN Servicio S ON C.id_servicio = S.id_servicio
        JOIN PerfilProfesional PP ON S.id_profesional = PP.id_profesional
        JOIN Cuenta CU_PROF ON PP.id_cuenta = CU_PROF.id_cuenta
        LEFT JOIN Pago P ON C.id_cita = P.id_cita
        LEFT JOIN TransaccionSegura TS ON P.id_pago = TS.id_pago
        WHERE C.id_cita = ? AND C.id_cliente = ?
    ";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $id_cita, $id_cliente);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $data = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$data) {
        throw new Exception("Cita no encontrada o no te pertenece.");
    }
    if ($data['estado'] !== 'confirmada') {
        throw new Exception("La cita debe estar 'confirmada' antes de finalizar y calificar.");
    }
    if ($data['escrow_estado'] !== 'retenido') {
        throw new Exception("El pago ya fue liberado o está en disputa.");
    }

    // B. INSERTAR LA RESEÑA
    $sql_reseña = "
        INSERT INTO Reseña (id_cita, calificacion, comentario) 
        VALUES (?, ?, ?)
    ";
    $stmt_reseña = $conn->prepare($sql_reseña);
    $stmt_reseña->bind_param("iis", $id_cita, $calificacion, $comentario);
    
    if (!$stmt_reseña->execute()) {
        throw new Exception("Error al insertar la reseña: " . $stmt_reseña->error);
    }
    $stmt_reseña->close();

    // C. ACTUALIZAR ESTADO DE CITA A 'finalizada'
    $sql_cita = "UPDATE Cita SET estado = 'finalizada' WHERE id_cita = ?";
    $stmt_cita = $conn->prepare($sql_cita);
    $stmt_cita->bind_param("i", $id_cita);
    $stmt_cita->execute();
    $stmt_cita->close();

    // D. LIBERAR PAGO (Actualizar TransaccionSegura y Pago)
    $fecha_liberacion = date('Y-m-d H:i:s');
    
    $sql_liberar_escrow = "UPDATE TransaccionSegura SET estado = 'liberado', fecha_liberacion = ? WHERE id_transaccion = ?";
    $stmt_escrow = $conn->prepare($sql_liberar_escrow);
    $stmt_escrow->bind_param("si", $fecha_liberacion, $data['id_transaccion']);
    $stmt_escrow->execute();
    $stmt_escrow->close();
    
    $sql_update_pago = "UPDATE Pago SET estado_pago = 'pagado', fecha_pago = ? WHERE id_pago = ?";
    $stmt_pago = $conn->prepare($sql_update_pago);
    $stmt_pago->bind_param("si", $fecha_liberacion, $data['id_pago']);
    $stmt_pago->execute();
    $stmt_pago->close();

    // E. ACTUALIZAR CALIFICACIÓN PROMEDIO DEL PROFESIONAL
    // Lo hacemos con una subconsulta para obtener el nuevo promedio
    $sql_avg = "
        UPDATE PerfilProfesional PP
        SET calificacion_promedio = (
            SELECT AVG(R.calificacion) 
            FROM Reseña R
            JOIN Cita C ON R.id_cita = C.id_cita
            JOIN Servicio S ON C.id_servicio = S.id_servicio
            WHERE S.id_profesional = PP.id_profesional
        )
        WHERE PP.id_profesional = ?
    ";
    $stmt_avg = $conn->prepare($sql_avg);
    $stmt_avg->bind_param("i", $id_profesional);
    $stmt_avg->execute();
    $stmt_avg->close();


    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Servicio finalizado, pago liberado y reseña registrada exitosamente.';

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error de reseña/liberación: " . $e->getMessage());
    $response['message'] = 'Fallo en la transacción: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>