<?php
// nandodm/servinet/ServiNet-e728ea1c53d2fa493dc4a4edd362433270b9cb26/api/get_profesional_public_profile.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al cargar perfil.', 'data' => null];

// 1. OBTENER ID DEL PROFESIONAL
$id_profesional = $_GET['id'] ?? 0;
$id_servicio = $_GET['service'] ?? 0;

if (!is_numeric($id_profesional) || $id_profesional <= 0) {
    $response['message'] = 'ID de profesional inválido.';
    echo json_encode($response);
    exit;
}

try {
    // 2. CONSULTA PRINCIPAL: DATOS DE CUENTA Y PERFIL PROFESIONAL
    $sql_profile = "
        SELECT 
            CU.id_cuenta, CU.nombre, CU.apellido_paterno, CU.apellido_materno, CU.municipio, CU.foto_perfil_url,
            PP.especialidades, PP.experiencia, PP.tarifa, PP.descripcion, PP.calificacion_promedio, PP.estado_verificacion
        FROM PerfilProfesional PP
        JOIN Cuenta CU ON PP.id_cuenta = CU.id_cuenta
        WHERE PP.id_profesional = ? AND CU.activo = TRUE
    ";
    
    $stmt_profile = $conn->prepare($sql_profile);
    $stmt_profile->bind_param("i", $id_profesional);
    $stmt_profile->execute();
    $result_profile = $stmt_profile->get_result();
    $profile = $result_profile->fetch_assoc();
    $stmt_profile->close();

    if (!$profile) {
        $response['message'] = 'Perfil no encontrado o inactivo.';
        echo json_encode($response);
        exit;
    }
    
    // 3. OBTENER PORTAFOLIO DE TRABAJOS (NUEVA CONSULTA)
    $sql_portfolio = "
        SELECT id_foto, url_imagen, descripcion
        FROM PortafolioProfesional
        WHERE id_profesional = ?
        ORDER BY fecha_subida DESC
        LIMIT 9 /* Mostrar hasta 9 fotos */
    ";

    $stmt_portfolio = $conn->prepare($sql_portfolio);
    $stmt_portfolio->bind_param("i", $id_profesional);
    $stmt_portfolio->execute();
    $result_portfolio = $stmt_portfolio->get_result();

    $portfolio = [];
    while ($row = $result_portfolio->fetch_assoc()) {
        $portfolio[] = $row;
    }
    $stmt_portfolio->close();
    
    // 4. OBTENER EL SERVICIO ESPECÍFICO (Si se pasa un ID de servicio)
    $service_data = null;
    if (is_numeric($id_servicio) && $id_servicio > 0) {
        $sql_service = "
            SELECT S.nombre_servicio, S.precio, S.duracion_minutos
            FROM Servicio S
            WHERE S.id_servicio = ? AND S.id_profesional = ?
        ";
        $stmt_service = $conn->prepare($sql_service);
        $stmt_service->bind_param("ii", $id_servicio, $id_profesional);
        $stmt_service->execute();
        $service_data = $stmt_service->get_result()->fetch_assoc();
        $stmt_service->close();
    }
    
    // 5. OBTENER ÚLTIMAS 3 RESEÑAS (Se mantiene la consulta)
    $sql_reviews = "
        SELECT 
            R.calificacion, R.comentario, R.fecha_reseña,
            CC.nombre AS cliente_nombre, CC.apellido_paterno AS cliente_apellido
        FROM Reseña R
        JOIN Cita C ON R.id_cita = C.id_cita
        JOIN Servicio S ON C.id_servicio = S.id_servicio
        JOIN Cuenta CC ON C.id_cliente = CC.id_cuenta
        WHERE S.id_profesional = ?
        ORDER BY R.fecha_reseña DESC
        LIMIT 3
    ";
    
    $stmt_reviews = $conn->prepare($sql_reviews);
    $stmt_reviews->bind_param("i", $id_profesional);
    $stmt_reviews->execute();
    $result_reviews = $stmt_reviews->get_result();
    
    $reviews = [];
    while ($row = $result_reviews->fetch_assoc()) {
        $reviews[] = $row;
    }
    $stmt_reviews->close();
    
    // 6. RESPUESTA FINAL (Añadiendo el portafolio)
    $response['success'] = true;
    $response['message'] = 'Perfil cargado exitosamente.';
    $response['data'] = [
        'perfil' => $profile,
        'servicio_seleccionado' => $service_data,
        'resenas' => $reviews,
        'portafolio' => $portfolio
    ];

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_profesional_public_profile.php: " . $e->getMessage());
    $response['message'] = 'Error de servidor: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>