<?php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al subir la foto al portafolio.'];

// 1. VERIFICACIÓN DE SESIÓN Y ROL
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['rol'] ?? '') !== 'profesional') {
    $response['message'] = 'Acceso denegado. Se requiere ser Profesional autenticado.';
    echo json_encode($response);
    exit;
}

$id_cuenta = $_SESSION['id_cuenta'] ?? 0;
$descripcion = $_POST['photo_description'] ?? ''; // Descripción opcional del trabajo

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    $response['message'] = 'Error de conexión a la base de datos.';
    echo json_encode($response);
    exit;
}

try {
    // 2. BUSCAR id_profesional a partir de id_cuenta
    $sql_prof = "SELECT id_profesional FROM PerfilProfesional WHERE id_cuenta = ?";
    $stmt_prof = $conn->prepare($sql_prof);
    $stmt_prof->bind_param("i", $id_cuenta);
    $stmt_prof->execute();
    $result_prof = $stmt_prof->get_result();
    $prof_data = $result_prof->fetch_assoc();
    $id_profesional = $prof_data['id_profesional'] ?? 0;
    $stmt_prof->close();

    if ($id_profesional === 0) {
        throw new Exception("Perfil profesional no encontrado. No se puede subir el portafolio.");
    }
    
    // Directorios de subida (CRÍTICO)
    $upload_dir = __DIR__ . '/../uploads/portafolio/';
    $base_url = '/ServiNet/uploads/portafolio/';
    
    // 🔑 INTENTA CREAR EL DIRECTORIO Y VERIFICA PERMISOS
    if (!is_dir($upload_dir)) {
        // Intenta crear el directorio si no existe
        if (!mkdir($upload_dir, 0777, true)) {
             throw new Exception("Directorio de subida NO existe y no pudo ser creado: Verifique permisos en 'uploads/'");
        }
    }
    
    // 3. PROCESAR LA IMAGEN SUBIDA
    if (isset($_FILES['portfolio_photo']) && $_FILES['portfolio_photo']['error'] === UPLOAD_ERR_OK) {
        
        $file_info = $_FILES['portfolio_photo'];
        $allowed_types = ['image/jpeg', 'image/png'];
        
        if ($file_info['size'] > 10000000) { // 10 MB
            $response['message'] = 'El archivo es demasiado grande (Máx. 10MB).';
        } elseif (!in_array($file_info['type'], $allowed_types)) {
            $response['message'] = 'Tipo de archivo no permitido. Solo JPG y PNG.';
        } else {
            // Generar un nombre único (ej. ID_PROF_timestamp.jpg)
            $extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
            $file_name = $id_profesional . '_port_' . time() . '.' . $extension;
            $file_path = $upload_dir . $file_name;
            $db_url = $base_url . $file_name;

            // Intentar mover el archivo
            if (move_uploaded_file($file_info['tmp_name'], $file_path)) {
                
                // 4. INSERTAR EN LA TABLA PortafolioProfesional
                $sql_insert = "INSERT INTO PortafolioProfesional (id_profesional, url_imagen, descripcion) VALUES (?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("iss", $id_profesional, $db_url, $descripcion);

                if (!$stmt_insert->execute()) {
                    throw new Exception("Error al insertar la URL en la DB: " . $stmt_insert->error);
                }
                
                $response['success'] = true;
                $response['message'] = 'Foto de trabajo subida exitosamente.';

            } else {
                // 🚨 MENSAJE DETALLADO DE FALLO DE ESCRITURA 🚨
                $response['message'] = 'Fallo de escritura. Verifique si el servidor tiene permisos para escribir en la carpeta ' . $upload_dir;
            }
        }
    } else {
        // Captura errores específicos de PHP al subir archivos
        $php_error_msg = match ($_FILES['portfolio_photo']['error'] ?? UPLOAD_ERR_NO_FILE) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo excede el límite permitido por el servidor.',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente.',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo.',
            default => 'Ocurrió un error desconocido durante la subida.'
        };
        $response['message'] = $php_error_msg;
    }

} catch (Exception $e) {
    error_log("Error de subida de portafolio: " . $e->getMessage());
    $response['message'] = 'Error interno del servidor. Detalle: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>