<?php
// nandodm/servinet/ServiNet-48ba9527754bd129e6df928d75d5a7bfac0a7456/delete_portfolio_photo.php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al eliminar la foto.'];

// 1. VERIFICACIÓN DE SESIÓN Y ROL
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['rol'] ?? '') !== 'profesional') {
    $response['message'] = 'Acceso denegado. Se requiere ser Profesional autenticado.';
    echo json_encode($response);
    exit;
}

$id_cuenta = $_SESSION['id_cuenta'] ?? 0;
$id_foto = $_POST['id_foto'] ?? 0;

if (!is_numeric($id_foto) || $id_foto <= 0) {
    $response['message'] = 'ID de foto inválido.';
    echo json_encode($response);
    exit;
}

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    $response['message'] = 'Error de conexión a la base de datos.';
    echo json_encode($response);
    exit;
}

$conn->begin_transaction();

try {
    // 2. VERIFICACIÓN DE PROPIEDAD Y OBTENER URL
    // CRÍTICO: Asegura que el usuario logueado sea el dueño de la foto y obtiene la URL para eliminar el archivo.
    $sql_check = "
        SELECT 
            PP.id_profesional, P.url_imagen 
        FROM PerfilProfesional PP
        JOIN PortafolioProfesional P ON PP.id_profesional = P.id_profesional
        WHERE P.id_foto = ? AND PP.id_cuenta = ?
    ";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $id_foto, $id_cuenta);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 0) {
        throw new Exception("Acceso denegado. La foto no existe o no te pertenece.");
    }
    $photo_data = $result_check->fetch_assoc();
    $image_url = $photo_data['url_imagen'];
    $stmt_check->close();
    
    // 3. ELIMINAR EL REGISTRO DE LA BASE DE DATOS
    $sql_delete_db = "DELETE FROM PortafolioProfesional WHERE id_foto = ?";
    $stmt_delete_db = $conn->prepare($sql_delete_db);
    $stmt_delete_db->bind_param("i", $id_foto);

    if (!$stmt_delete_db->execute()) {
        throw new Exception("Error al eliminar el registro de la DB: " . $stmt_delete_db->error);
    }
    $stmt_delete_db->close();
    
    // 4. ELIMINAR EL ARCHIVO FÍSICO
    $base_path = __DIR__ . '/../'; // Ruta base del proyecto (asumiendo que /api/ está en la raíz)
    $file_path_to_delete = $base_path . str_replace('/ServiNet/', '', $image_url);

    // Verificamos si el archivo existe y lo eliminamos
    if (file_exists($file_path_to_delete)) {
        if (!unlink($file_path_to_delete)) {
            // No detenemos la transacción, pero registramos la advertencia
            error_log("ADVERTENCIA: No se pudo eliminar el archivo físico: " . $file_path_to_delete);
        }
    } else {
        error_log("ADVERTENCIA: Archivo físico no encontrado en la ruta: " . $file_path_to_delete);
    }
    
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = 'Foto eliminada correctamente del portafolio.';

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error en delete_portfolio_photo.php: " . $e->getMessage());
    $response['message'] = 'Fallo en la eliminación: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>