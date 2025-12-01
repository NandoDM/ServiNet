<?php
// Configuración de cabeceras para permitir peticiones AJAX y devolver JSON
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); // Añadido para evitar problemas de CORS
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

session_start(); // Iniciar la sesión para almacenar el estado de login

// Incluir el archivo de configuración de la base de datos
// ASEGÚRATE DE QUE ESTA RUTA SEA CORRECTA
include_once '../db_config.php'; 

// Respuesta por defecto
$response = ['success' => false, 'message' => 'Error de servidor desconocido.'];


// Asegurarse de que el método sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// ----------------------------------------------------
// 1. OBTENER Y VALIDAR DATOS 
// ----------------------------------------------------

$correo = $_POST['correo'] ?? ''; 
$contrasena = $_POST['contrasena'] ?? ''; 

// Verificar que la conexión esté disponible antes de continuar
if (!isset($conn) || $conn->connect_error) {
    $response['message'] = 'Error de conexión a la base de datos.';
    echo json_encode($response);
    exit;
}

if (empty($correo) || empty($contrasena)) {
    $response['message'] = 'El correo y la contraseña son obligatorios.';
    $conn->close();
    echo json_encode($response);
    exit;
}

// ----------------------------------------------------
// 2. BUSCAR USUARIO Y CONTRASEÑA (MODIFICADO)
// ----------------------------------------------------

// Modificamos la consulta para OBTENER EL CAMPO foto_perfil_url
$sql = "SELECT c.id_cuenta, c.correo, c.contraseña, c.nombre, c.foto_perfil_url, r.nombre_rol 
        FROM cuenta c
        JOIN cuentarol cr ON c.id_cuenta = cr.id_cuenta
        JOIN rol r ON cr.id_rol = r.id_rol
        WHERE c.correo = ? AND c.activo = 1"; // Solo cuentas activas

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $response['message'] = 'Error al preparar la consulta: ' . $conn->error;
    $conn->close();
    echo json_encode($response);
    exit;
}

$stmt->bind_param("s", $correo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $response['message'] = 'Correo o contraseña incorrectos.';
    $stmt->close();
    $conn->close();
    echo json_encode($response);
    exit;
}

// ----------------------------------------------------
// 3. VERIFICAR CONTRASEÑA Y OBTENER DATOS
// ----------------------------------------------------

$user = $result->fetch_assoc();
$hashed_password = $user['contraseña'];

// Verificar el hash de la contraseña
if (!password_verify($contrasena, $hashed_password)) {
    $response['message'] = 'Correo o contraseña incorrectos.';
    $stmt->close();
    $conn->close();
    echo json_encode($response);
    exit;
}

// ----------------------------------------------------
// 4. INICIO DE SESIÓN EXITOSO (MODIFICADO)
// ----------------------------------------------------

// Almacenar datos de la sesión
$_SESSION['loggedin'] = true;
$_SESSION['id_cuenta'] = $user['id_cuenta'];
$_SESSION['nombre'] = $user['nombre'];
$_SESSION['rol'] = $user['nombre_rol']; 
$_SESSION['correo'] = $user['correo']; 
// 🔑 CORRECCIÓN CLAVE: Almacenar la URL de la foto en la sesión
$_SESSION['foto_perfil_url'] = $user['foto_perfil_url'];

// Respuesta exitosa
$response['success'] = true; 
$response['message'] = 'Inicio de sesión exitoso.';
$response['user'] = [
    'id_cuenta' => $user['id_cuenta'],
    'nombre' => $user['nombre'],
    'rol' => $user['nombre_rol'], 
    // 🔑 CORRECCIÓN CLAVE: Devolver la URL de la foto para el localStorage
    'foto_perfil_url' => $user['foto_perfil_url'] 
];

$stmt->close();
$conn->close();

echo json_encode($response);
exit;
?>