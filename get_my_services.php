<?php
// Configuración de cabeceras
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET");

// Incluir la configuración de la base de datos
require_once 'db_config.php';

$response = ['success' => false, 'message' => 'Error al obtener tus servicios.', 'services' => []];

// ----------------------------------------------------
// 1. OBTENER ID DE CUENTA (Simulación)
// ----------------------------------------------------
// ESTO DEBE VENIR DEL FRONTEND (localStorage) EN LA VIDA REAL.
// Lo obtenemos de GET/POST o usamos un valor de prueba.
session_start();
$id_cuenta = $_GET['id_cuenta'] ?? ($_SESSION['id_cuenta'] ?? 0);

if (empty($id_cuenta)) {
    $response['message'] = 'ID de cuenta no proporcionado.';
    echo json_encode($response);
    exit;
}

try {
    // 2. Buscar id_profesional. Necesitamos este ID para filtrar los servicios.
    $sql_prof = "SELECT id_profesional FROM PerfilProfesional WHERE id_cuenta = ?";
    $stmt_prof = $conn->prepare($sql_prof);
    $stmt_prof->bind_param("i", $id_cuenta);
    $stmt_prof->execute();
    $result_prof = $stmt_prof->get_result();

    if ($result_prof->num_rows === 0) {
        throw new Exception("Perfil de profesional no encontrado para esta cuenta.");
    }
    $prof_data = $result_prof->fetch_assoc();
    $id_profesional = $prof_data['id_profesional'];
    $stmt_prof->close();


    // 3. Obtener todos los servicios asociados a ese id_profesional
    $sql = "
        SELECT 
            S.id_servicio, S.nombre_servicio, S.descripcion, S.precio, S.duracion_minutos, S.disponible,
            S.imagen_url, /* 🔑 AÑADIDO: URL de la imagen del servicio */
            CS.nombre_categoria
        FROM Servicio S
        JOIN CategoriaServicio CS ON S.id_categoria = CS.id_categoria
        WHERE S.id_profesional = ?
        ORDER BY S.disponible DESC, S.fecha_creacion DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_profesional);
    $stmt->execute();
    $result = $stmt->get_result();

    $services = [];
    while ($row = $result->fetch_assoc()) {
        $services[] = [
            'id' => $row['id_servicio'],
            'titulo' => $row['nombre_servicio'],
            'descripcion' => $row['descripcion'],
            'precio_base' => $row['precio'],
            'duracion' => $row['duracion_minutos'],
            'categoria_nombre' => $row['nombre_categoria'],
            'disponible' => $row['disponible'], // 1 = Activo, 0 = Pausado
            'imagen_url' => $row['imagen_url'] // 🔑 DATO ENVIADO AL FRONTEND
        ];
    }
    
    $response['success'] = true;
    $response['message'] = 'Tus servicios han sido cargados.';
    $response['services'] = $services;

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_my_services.php: " . $e->getMessage());
    $response['message'] = 'Error de servidor: ' . $e->getMessage();
}

if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close();

echo json_encode($response);