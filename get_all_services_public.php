<?php
// nandodm/servinet/ServiNet-e7a913c5251aedd806317b56fa60d612c9d7bd2f/api/get_all_services_public.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// 🚨 CORRECCIÓN DE RUTA: Usamos __DIR__ para asegurar la conexión.
require_once __DIR__ . '/db_config.php';

$response = ['success' => false, 'message' => 'Error al obtener servicios.', 'services' => []];

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    $response['message'] = 'Error de conexión a la base de datos: ' . ($conn->connect_error ?? 'No definida.');
    echo json_encode($response);
    exit;
}

try {
    // 1. CONSTRUIR LA CONSULTA BASE (MODIFICADA: AÑADIMOS S.imagen_url)
    $sql = "
        SELECT 
            S.id_servicio, S.nombre_servicio AS titulo, S.descripcion, S.precio AS precio_base, 
            S.duracion_minutos AS duracion, S.disponible AS service_disponible,
            S.imagen_url, /* 🔑 AÑADIDO: IMAGEN ESPECÍFICA DEL SERVICIO */
            CS.id_categoria, CS.nombre_categoria,
            PP.id_profesional, PP.especialidades, PP.experiencia, 
            PP.calificacion_promedio, PP.estado_verificacion, PP.disponibilidad AS prof_disponibilidad,
            CU.id_cuenta AS prof_id_cuenta,
            CU.nombre AS prof_nombre, CU.apellido_paterno AS prof_apellido, CU.municipio, 
            CU.foto_perfil_url AS foto_perfil_url
        FROM Servicio S
        JOIN CategoriaServicio CS ON S.id_categoria = CS.id_categoria
        JOIN PerfilProfesional PP ON S.id_profesional = PP.id_profesional
        JOIN Cuenta CU ON PP.id_cuenta = CU.id_cuenta
        WHERE S.disponible = TRUE AND CU.activo = TRUE 
    ";

    $params = [];
    $types = "";
    $where_clauses = [];

    // 2. APLICAR FILTROS (Mantenido)
    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $search_like = "%" . $_GET['search'] . "%";
        $where_clauses[] = " (S.nombre_servicio LIKE ? OR S.descripcion LIKE ? OR CU.nombre LIKE ?) ";
        $types .= "sss";
        $params[] = $search_like;
        $params[] = $search_like;
        $params[] = $search_like;
    }
    if (isset($_GET['categoria']) && $_GET['categoria'] !== '' && $_GET['categoria'] !== 'all') {
        $where_clauses[] = "CS.id_categoria = ?";
        $params[] = $_GET['categoria'];
        $types .= "i";
    }
    if (isset($_GET['municipio']) && $_GET['municipio'] !== '' && $_GET['municipio'] !== 'all') {
        $where_clauses[] = "CU.municipio = ?";
        $params[] = $_GET['municipio'];
        $types .= "s";
    }
    if (isset($_GET['precio_rango']) && $_GET['precio_rango'] !== '' && $_GET['precio_rango'] !== 'all') {
        switch ($_GET['precio_rango']) {
            case '0-500':
                $where_clauses[] = "S.precio <= 500";
                break;
            case '501-1000':
                $where_clauses[] = "S.precio >= 501 AND S.precio <= 1000";
                break;
            case '1001-plus':
                $where_clauses[] = "S.precio > 1000";
                break;
        }
    }
    if (isset($_GET['disponibilidad']) && $_GET['disponibilidad'] === 'true') {
        $where_clauses[] = "PP.disponibilidad = 'DISPONIBLE'";
    }

    // Unir las cláusulas WHERE
    if (!empty($where_clauses)) {
        $sql .= " AND " . implode(" AND ", $where_clauses);
    }
    $sql .= " ORDER BY PP.calificacion_promedio DESC, S.precio ASC";

    // 3. PREPARAR Y EJECUTAR LA CONSULTA
    $stmt = $conn->prepare($sql);
    
    if ($stmt === FALSE) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    if (!empty($params)) {
        $bind_params = array_merge([$types], $params);
        $ref_array = [];
        foreach ($bind_params as $key => $value) {
            $ref_array[$key] = &$bind_params[$key];
        }
        @call_user_func_array([$stmt, 'bind_param'], $ref_array);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    $services = [];
    while ($row = $result->fetch_assoc()) {
        $disponibilidad_final = ($row['prof_disponibilidad'] === 'DISPONIBLE');
        
        $services[] = [
            'id' => $row['id_servicio'],
            'titulo' => $row['titulo'],
            'descripcion' => $row['descripcion'],
            'precio_base' => $row['precio_base'],
            'duracion' => $row['duracion'],
            'is_disponible' => (bool)$row['service_disponible'], 
            'disponibilidad_final' => $disponibilidad_final,
            'imagen_url' => $row['imagen_url'], /* 🔑 DATO ENVIADO AL FRONTEND */
            'categoria' => [
                'id' => $row['id_categoria'],
                'nombre' => $row['nombre_categoria']
            ],
            'profesional' => [
                'id' => $row['id_profesional'],
                'id_cuenta' => $row['prof_id_cuenta'],
                'nombre' => $row['prof_nombre'] . ' ' . $row['prof_apellido'],
                'municipio' => $row['municipio'],
                'calificacion_promedio' => $row['calificacion_promedio'] ?? 0.00,
                'verificado' => $row['estado_verificacion'] === 'verificado',
                'foto_perfil' => $row['foto_perfil_url'],
                'prof_disponibilidad_estado' => $row['prof_disponibilidad'] 
            ]
        ];
    }
    $stmt->close();

    $response['success'] = true;
    $response['message'] = 'Servicios cargados exitosamente.';
    $response['services'] = $services;

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_all_services_public.php: " . $e->getMessage());
    $response['message'] = 'Error de servidor: No se pudieron cargar los servicios.';
}

$conn->close();
echo json_encode($response);