<?php
// Configuración de cabeceras
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// 🚨 CORRECCIÓN DE RUTA: Usamos __DIR__ para asegurar la conexión.
require_once __DIR__ . '/db_config.php';

// Asegurarse de que solo se pueda acceder mediante GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$response = ['success' => false, 'message' => 'Error al obtener municipios.', 'data' => []]; 

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    $response['message'] = 'Error de conexión a la base de datos: ' . ($conn->connect_error ?? 'No definida.');
    echo json_encode($response);
    exit;
}

try {
    // Consulta para obtener los nombres de los municipios activos, ordenados alfabéticamente
    $sql = "SELECT nombre_municipio FROM MunicipioPuebla WHERE activo = TRUE ORDER BY nombre_municipio ASC";
    $result = $conn->query($sql);

    if ($result === FALSE) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }

    $municipios = [];
    while ($row = $result->fetch_assoc()) {
        $municipios[] = [
            'nombre' => $row['nombre_municipio'] 
        ];
    }
    
    $response['success'] = true;
    $response['message'] = 'Lista de municipios obtenida correctamente.';
    $response['data'] = $municipios; // Usamos 'data' como clave de array para el JS

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Error de servidor: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>