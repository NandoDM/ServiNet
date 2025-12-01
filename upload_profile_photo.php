<?php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al subir la foto.'];

// 1. VERIFICACIÓN DE SESIÓN
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $response['message'] = 'Acceso denegado. Debe iniciar sesión.';
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

// Directorio donde se guardarán las fotos (Relativo a tu proyecto base)
$upload_dir = __DIR__ . '/../uploads/perfiles/';
$base_url_prefix = '/ServiNet/'; // Prefijo de la URL web
$base_url_local = __DIR__ . '/../'; // Ruta base local del proyecto

// 2. PROCESAR LA IMAGEN SUBIDA
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    
    $file_info = $_FILES['profile_photo'];
    $allowed_types = ['image/jpeg', 'image/png'];
    
    if ($file_info['size'] > 5000000) { // 5 MB
        $response['message'] = 'El archivo es demasiado grande (Máx. 5MB).';
    } elseif (!in_array($file_info['type'], $allowed_types)) {
        $response['message'] = 'Tipo de archivo no permitido. Solo JPG y PNG.';
    } else {
        
        // Generar nombre único
        $extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
        $file_name = $id_cuenta . '_' . time() . '.' . $extension;
        $file_path = $upload_dir . $file_name;
        $db_url = $base_url_prefix . 'uploads/perfiles/' . $file_name;

        // Intentar mover el archivo del directorio temporal al destino final
        if (move_uploaded_file($file_info['tmp_name'], $file_path)) {
            
            try {
                
                // 3. OBTENER URL ANTIGUA (Para eliminación)
                $sql_get_old = "SELECT foto_perfil_url FROM Cuenta WHERE id_cuenta = ?";
                $stmt_get_old = $conn->prepare($sql_get_old);
                $stmt_get_old->bind_param("i", $id_cuenta);
                $stmt_get_old->execute();
                $old_photo_url = $stmt_get_old->get_result()->fetch_assoc()['foto_perfil_url'] ?? null;
                $stmt_get_old->close();
                
                
                // 4. ACTUALIZAR LA BASE DE DATOS con la nueva URL
                $sql_update = "UPDATE Cuenta SET foto_perfil_url = ? WHERE id_cuenta = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("si", $db_url, $id_cuenta);

                if (!$stmt_update->execute()) {
                    throw new Exception("Error al actualizar la URL en la DB: " . $stmt_update->error);
                }
                $stmt_update->close();
                
                
                // 5. ELIMINAR EL ARCHIVO FÍSICO ANTIGUO
                // Reemplazamos el prefijo web con la ruta local
                if ($old_photo_url && strpos($old_photo_url, $base_url_prefix) === 0) {
                    
                    $local_path_to_delete = $base_url_local . substr($old_photo_url, strlen($base_url_prefix));
                    
                    // Verificamos si existe el archivo (y no es la imagen por defecto si la tuvieras)
                    if (file_exists($local_path_to_delete) && !is_dir($local_path_to_delete)) {
                        @unlink($local_path_to_delete);
                    }
                }
                
                // 6. ÉXITO
                $_SESSION['foto_perfil_url'] = $db_url; 
                
                $response['success'] = true;
                $response['message'] = 'Foto de perfil actualizada y archivo anterior eliminado (si existía).';
                $response['photo_url'] = $db_url;

            } catch (Exception $e) {
                // Si la DB falla, eliminamos el archivo subido para limpiar.
                @unlink($file_path);
                $response['message'] = 'Error de DB: ' . $e->getMessage();
            }

        } else {
            $response['message'] = 'Error al mover el archivo subido. Verifique los permisos de la carpeta.';
        }
    }
} else {
    $response['message'] = 'No se subió ningún archivo o ocurrió un error desconocido.';
}

$conn->close();
echo json_encode($response);
?>