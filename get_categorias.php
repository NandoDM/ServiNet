<?php
// Configuración de cabeceras
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// Incluir el archivo de configuración de la base de datos
// 🚨 CORRECCIÓN DE RUTA: Usamos __DIR__ para asegurar la conexión.
require_once __DIR__ . '/db_config.php'; 

// Asegurarse de que solo se pueda acceder mediante GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$response = ['success' => false, 'message' => 'Error al obtener categorías.', 'categorias' => []];

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    $response['message'] = 'Error de conexión a la base de datos: ' . ($conn->connect_error ?? 'No definida.');
    echo json_encode($response);
    exit;
}

try {
    // Consulta para obtener el ID y el nombre de las categorías activas.
    $sql = "SELECT id_categoria, nombre_categoria FROM CategoriaServicio WHERE activa = TRUE ORDER BY nombre_categoria ASC";
    $result = $conn->query($sql);

    if ($result === FALSE) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }

    $categorias = [];
    while ($row = $result->fetch_assoc()) {
        $categorias[] = [
            'id_categoria' => $row['id_categoria'], 
            'nombre_categoria' => $row['nombre_categoria'] 
        ];
    }
    
    $response['success'] = true;
    $response['message'] = 'Lista de categorías obtenida correctamente.';
    $response['categorias'] = $categorias;

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Error de servidor: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>