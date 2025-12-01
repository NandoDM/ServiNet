<?php
// nandodm/servinet/ServiNet-e7a913c5251aedd806317b56fa60d612c9d7bd2f/api/update_service.php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al actualizar el servicio.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// 1. OBTENER DATOS
$id_servicio = $_POST['service_id'] ?? 0;
$nombre_servicio = $_POST['nombre_servicio'] ?? '';
$descripcion = $_POST['descripcion'] ?? '';
$precio = $_POST['precio'] ?? 0.00;
$id_categoria = $_POST['id_categoria'] ?? '';
$duracion_minutos = $_POST['duracion'] ?? 60;
$disponible = $_POST['disponible'] ?? 0; 
$id_cuenta = $_SESSION['id_cuenta'] ?? 0;

// 2. VALIDACIONES BÁSICAS
if (empty($id_servicio) || empty($nombre_servicio) || empty($descripcion) || empty($precio) || empty($id_categoria)) {
    $response['message'] = 'Faltan campos obligatorios para la actualización.';
    echo json_encode($response);
    exit;
}

if (!is_numeric($precio) || $precio <= 0) {
    $response['message'] = 'El precio debe ser un valor positivo.';
    echo json_encode($response);
    exit;
}

$conn->begin_transaction();

try {
    // 3. VERIFICACIÓN DE PROPIEDAD Y OBTENCIÓN DE DATOS ACTUALES
    $sql_check = "
        SELECT PP.id_profesional, S.imagen_url
        FROM PerfilProfesional PP
        JOIN Servicio S ON PP.id_profesional = S.id_profesional
        WHERE S.id_servicio = ? AND PP.id_cuenta = ?
    ";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $id_servicio, $id_cuenta);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 0) {
        throw new Exception("Acceso denegado. No eres el propietario de este servicio o el servicio no existe.");
    }
    $service_data = $result_check->fetch_assoc();
    $id_profesional = $service_data['id_profesional'];
    $current_image_url = $service_data['imagen_url'];
    $stmt_check->close();

    // 🔑 4. PROCESAR SUBIDA DE IMAGEN Y ELIMINAR LA ANTERIOR (NUEVA LÓGICA)
    
    $base_url_prefix = '/ServiNet/'; // Prefijo de la URL web
    $base_path_local = __DIR__ . '/../'; // Ruta base local del proyecto
    
    $db_url = $current_image_url; // Mantener URL actual por defecto
    $image_update_clause = ""; 
    
    // Solo si se envió un nuevo archivo para subir
    if (isset($_FILES['service_image']) && $_FILES['service_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/servicios/';
        $base_url = '/ServiNet/uploads/servicios/';

        $file_info = $_FILES['service_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        
        if (!in_array($file_info['type'], $allowed_types) || $file_info['size'] > 5000000) { // 5MB
            $response['warning'] = 'Imagen no actualizada: Tipo de archivo no permitido o es demasiado grande.';
        } else {
            $extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
            $file_name = $id_profesional . '_srv_' . time() . '.' . $extension;
            $file_path = $upload_dir . $file_name;
            $new_db_url = $base_url . $file_name;

            if (move_uploaded_file($file_info['tmp_name'], $file_path)) {
                
                // 🔑 ELIMINACIÓN DEL ARCHIVO FÍSICO ANTERIOR
                $default_image_path = '/ServiNet/assets/img/default_service.webp';
                
                // Si existe una URL antigua y NO es la imagen por defecto
                if ($current_image_url && $current_image_url !== $default_image_path) {
                    $local_path_to_delete = $base_path_local . substr($current_image_url, strlen($base_url_prefix));
                    
                    if (file_exists($local_path_to_delete) && !is_dir($local_path_to_delete)) {
                        @unlink($local_path_to_delete);
                    }
                }
                
                $db_url = $new_db_url;
                $image_update_clause = ", imagen_url = ?"; // Se añade la cláusula para actualizar la URL

            } else {
                $response['warning'] = 'Imagen no actualizada: Fallo al mover el archivo en el servidor.';
            }
        }
    }
    

    // 5. ACTUALIZAR EL SERVICIO (SQL dinámico para la imagen)
    $sql_update = "
        UPDATE Servicio 
        SET 
            id_categoria = ?, 
            nombre_servicio = ?, 
            descripcion = ?, 
            precio = ?, 
            duracion_minutos = ?,
            disponible = ?
            {$image_update_clause}
        WHERE id_servicio = ?
    ";
    $stmt_update = $conn->prepare($sql_update);
    
    // Configuración dinámica de bind_param (añadir la imagen URL si es necesario)
    $params = [
        $id_categoria, 
        $nombre_servicio, 
        $descripcion, 
        $precio, 
        $duracion_minutos,
        $disponible
    ];
    $types = "issdii";

    if (!empty($image_update_clause)) {
        $params[] = $db_url;
        $types .= "s";
    }
    
    $params[] = $id_servicio;
    $types .= "i";
    
    // Lógica para vincular parámetros dinámicamente
    $bind_params = array_merge([$types], $params);
    $ref_array = [];
    foreach ($bind_params as $key => $value) {
        $ref_array[$key] = &$bind_params[$key];
    }
    
    call_user_func_array([$stmt_update, 'bind_param'], $ref_array);

    if (!$stmt_update->execute()) {
        throw new Exception("Error al actualizar servicio: " . $stmt_update->error);
    }
    
    $stmt_update->close();
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = $response['warning'] ?? 'Servicio actualizado exitosamente.';
    $response['new_image_url'] = $db_url;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error en update_service.php: " . $e->getMessage());
    $response['message'] = 'Fallo en la actualización: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);