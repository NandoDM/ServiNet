<?php
// Configuración de cabeceras y manejo de sesión
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

// Incluir la configuración de la base de datos
require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error de servidor desconocido.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// 1. OBTENER DATOS Y ID DE CUENTA DEL FORMULARIO JS
session_start();
$id_cuenta = $_SESSION['id_cuenta'] ?? 0;
$nombre_servicio = $_POST['nombre_servicio'] ?? '';
$descripcion = $_POST['descripcion'] ?? '';
$precio = $_POST['precio'] ?? 0.00;
$id_categoria = $_POST['id_categoria'] ?? '';
$duracion_minutos = $_POST['duracion'] ?? 60;

// 2. VALIDACIONES BÁSICAS
if (empty($id_cuenta)) {
    $response['message'] = 'ID de cuenta no proporcionado. Asegúrate de estar logueado.';
    echo json_encode($response);
    exit;
}

if (empty($nombre_servicio) || empty($descripcion) || empty($precio) || empty($id_categoria)) {
    $response['message'] = 'Faltan campos obligatorios del servicio (nombre, descripción, precio o categoría).';
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
    
    // 3. BUSCAR id_profesional USANDO id_cuenta
    $sql_prof = "SELECT id_profesional FROM PerfilProfesional WHERE id_cuenta = ?";
    $stmt_prof = $conn->prepare($sql_prof);
    $stmt_prof->bind_param("i", $id_cuenta);
    $stmt_prof->execute();
    $result_prof = $stmt_prof->get_result();

    if ($result_prof->num_rows === 0) {
        throw new Exception("Cuenta no encontrada o no es un perfil de profesional activo.");
    }
    $prof_data = $result_prof->fetch_assoc();
    $id_profesional = $prof_data['id_profesional'];
    $stmt_prof->close();

    // 🔑 4. PROCESAR SUBIDA DE IMAGEN (NUEVA LÓGICA)
    $upload_dir = __DIR__ . '/../uploads/servicios/'; // Carpeta de subida
    $base_url = '/ServiNet/uploads/servicios/';
    $db_url = '/ServiNet/assets/img/default_service.webp'; // Valor por defecto de BD

    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
             error_log("Fallo al crear el directorio de subida de servicios.");
        }
    }
    
    if (isset($_FILES['service_image']) && $_FILES['service_image']['error'] === UPLOAD_ERR_OK) {
        $file_info = $_FILES['service_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        
        if (!in_array($file_info['type'], $allowed_types) || $file_info['size'] > 5000000) { // 5MB
            $response['warning'] = 'Imagen no subida: Tipo de archivo no permitido o es demasiado grande (Máx. 5MB).';
        } else {
            $extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
            $file_name = $id_profesional . '_srv_' . time() . '.' . $extension;
            $file_path = $upload_dir . $file_name;
            $new_db_url = $base_url . $file_name;

            if (move_uploaded_file($file_info['tmp_name'], $file_path)) {
                $db_url = $new_db_url; // URL exitosa
            } else {
                $response['warning'] = 'Imagen no subida: Fallo al mover el archivo en el servidor.';
            }
        }
    }


    // 5. INSERTAR EL NUEVO SERVICIO (AÑADIENDO imagen_url)
    $sql_insert = "
        INSERT INTO Servicio (id_profesional, id_categoria, nombre_servicio, descripcion, precio, duracion_minutos, imagen_url, disponible) 
        VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
    ";
    $stmt_insert = $conn->prepare($sql_insert);
    
    $stmt_insert->bind_param("iissdis", 
        $id_profesional, 
        $id_categoria, 
        $nombre_servicio, 
        $descripcion, 
        $precio, 
        $duracion_minutos,
        $db_url // 🔑 URL DE LA IMAGEN
    );

    if (!$stmt_insert->execute()) {
        throw new Exception("Error al insertar servicio: " . $stmt_insert->error);
    }
    
    $new_service_id = $conn->insert_id;
    $stmt_insert->close();
    
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = $response['warning'] ?? 'Servicio publicado exitosamente. ¡Comienza a recibir citas!';
    $response['service_id'] = $new_service_id;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error en add_service.php: " . $e->getMessage());
    $response['message'] = 'Fallo en la publicación: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);