<?php
// nandodm/servinet/ServiNet-48ba9527754bd129e6df928d75d5a7bfac0a7456/get_portfolio_photos.php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al cargar el portafolio.', 'photos' => []];

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
    // 2. BUSCAR id_profesional
    $sql_prof = "SELECT id_profesional FROM PerfilProfesional WHERE id_cuenta = ?";
    $stmt_prof = $conn->prepare($sql_prof);
    $stmt_prof->bind_param("i", $id_cuenta);
    $stmt_prof->execute();
    $result_prof = $stmt_prof->get_result();
    $prof_data = $result_prof->fetch_assoc();
    $id_profesional = $prof_data['id_profesional'] ?? 0;
    $stmt_prof->close();

    if ($id_profesional === 0) {
        throw new Exception("Perfil profesional no encontrado.");
    }
    
    // 3. OBTENER LISTA DE FOTOS DEL PORTAFOLIO
    $sql_photos = "
        SELECT id_foto, url_imagen, descripcion, fecha_subida
        FROM PortafolioProfesional
        WHERE id_profesional = ?
        ORDER BY fecha_subida DESC
    ";
    
    $stmt_photos = $conn->prepare($sql_photos);
    $stmt_photos->bind_param("i", $id_profesional);
    $stmt_photos->execute();
    $result_photos = $stmt_photos->get_result();

    $photos = [];
    while ($row = $result_photos->fetch_assoc()) {
        $photos[] = [
            'id_foto' => $row['id_foto'],
            'url_imagen' => $row['url_imagen'],
            'descripcion' => $row['descripcion'],
            'fecha_subida' => date('d M, Y', strtotime($row['fecha_subida']))
        ];
    }
    $stmt_photos->close();
    
    $response['success'] = true;
    $response['message'] = 'Portafolio cargado exitosamente.';
    $response['photos'] = $photos;

} catch (Exception $e) {
    error_log("Error en get_portfolio_photos.php: " . $e->getMessage());
    $response['message'] = 'Error de servidor: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>