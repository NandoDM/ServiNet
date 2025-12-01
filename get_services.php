<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once 'db_config.php';

$response = ['success' => false, 'message' => 'Error al obtener servicios.', 'services' => []];

try {
    // Parámetros de búsqueda opcionales desde la URL
    $query_param = $_GET['query'] ?? '';
    $category_param = $_GET['categoria_id'] ?? '';
    $location_param = $_GET['ubicacion'] ?? ''; // Nueva variable para la ubicación

    $sql = "
        SELECT 
            S.id_servicio, S.nombre_servicio, S.descripcion, S.precio, S.duracion_minutos, S.imagen_url, /* 🔑 AÑADIDO: IMAGEN DEL SERVICIO */
            CS.nombre_categoria,
            PP.id_profesional, PP.nombre_completo AS nombre_profesional, PP.foto_perfil_url, /* 🔑 Foto de perfil del profesional */
            P.id_cuenta, P.email, P.telefono,
            AVG(R.calificacion) AS promedio_calificacion,
            COUNT(R.id_reseña) AS total_reseñas,
            PA.municipio, PA.estado
        FROM Servicio S
        JOIN CategoriaServicio CS ON S.id_categoria = CS.id_categoria
        JOIN PerfilProfesional PP ON S.id_profesional = PP.id_profesional
        JOIN Cuenta P ON PP.id_cuenta = P.id_cuenta
        LEFT JOIN Reseña R ON S.id_servicio = R.id_servicio
        LEFT JOIN PerfilProfesionalDireccion PAD ON PP.id_profesional = PAD.id_profesional
        LEFT JOIN Direccion PA ON PAD.id_direccion = PA.id_direccion
        WHERE S.disponible = TRUE
    ";

    $conditions = [];
    $params = [];
    $types = "";

    if (!empty($query_param)) {
        $conditions[] = "(S.nombre_servicio LIKE ? OR S.descripcion LIKE ? OR PP.nombre_completo LIKE ? OR CS.nombre_categoria LIKE ?)";
        $search_query = "%" . $query_param . "%";
        $params[] = $search_query;
        $params[] = $search_query;
        $params[] = $search_query;
        $params[] = $search_query;
        $types .= "ssss";
    }

    if (!empty($category_param) && $category_param !== 'all') {
        $conditions[] = "S.id_categoria = ?";
        $params[] = $category_param;
        $types .= "i";
    }

    // 🔑 Filtro por ubicación (municipio o estado)
    if (!empty($location_param)) {
        $conditions[] = "(PA.municipio LIKE ? OR PA.estado LIKE ?)";
        $location_search = "%" . $location_param . "%";
        $params[] = $location_search;
        $params[] = $location_search;
        $types .= "ss";
    }

    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }

    $sql .= " GROUP BY S.id_servicio ORDER BY S.fecha_creacion DESC";

    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    $services = [];
    while ($row = $result->fetch_assoc()) {
        $services[] = [
            'id_servicio' => $row['id_servicio'],
            'nombre_servicio' => $row['nombre_servicio'],
            'descripcion_servicio' => $row['descripcion'],
            'precio' => $row['precio'],
            'duracion_minutos' => $row['duracion_minutos'],
            'imagen_url' => $row['imagen_url'], // 🔑 IMAGEN ESPECÍFICA DEL SERVICIO
            'nombre_categoria' => $row['nombre_categoria'],
            'id_profesional' => $row['id_profesional'],
            'nombre_profesional' => $row['nombre_profesional'],
            'foto_perfil_profesional' => $row['foto_perfil_url'], // Todavía disponible si se necesita
            'promedio_calificacion' => round($row['promedio_calificacion'], 1),
            'total_reseñas' => $row['total_reseñas'],
            'ubicacion_municipio' => $row['municipio'],
            'ubicacion_estado' => $row['estado']
        ];
    }
    
    $response['success'] = true;
    $response['message'] = 'Servicios cargados exitosamente.';
    $response['services'] = $services;

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_services.php: " . $e->getMessage());
    $response['message'] = 'Error de servidor: ' . $e->getMessage();
}

if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close();

echo json_encode($response);