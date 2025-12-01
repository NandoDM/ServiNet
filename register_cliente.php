<?php
header('Content-Type: application/json');

// Incluir el archivo de configuración de la base de datos
include_once '../db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// ----------------------------------------------------
// 1. OBTENER Y VALIDAR DATOS DE ENTRADA
// ----------------------------------------------------

$correo = $_POST['correo'] ?? '';
$contrasena = $_POST['contrasena'] ?? '';
$nombre = $_POST['nombre'] ?? '';
$apellido_paterno = $_POST['apellido_paterno'] ?? '';
$apellido_materno = $_POST['apellido_materno'] ?? '';
$telefono = $_POST['telefono'] ?? '';
$municipio = $_POST['municipio'] ?? ''; // <-- CAPTURADO DEL FRONTEND

// VALIDACIÓN BÁSICA
if (empty($correo) || empty($contrasena) || empty($nombre) || empty($apellido_paterno) || empty($telefono) || empty($municipio)) {
    echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios.']);
    $conn->close();
    exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Formato de correo no válido.']);
    $conn->close();
    exit;
}

// ----------------------------------------------------
// 2. VERIFICAR DUPLICADOS
// ----------------------------------------------------

$stmt = $conn->prepare("SELECT id_cuenta FROM cuenta WHERE correo = ?");
$stmt->bind_param("s", $correo);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está registrado.']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// ----------------------------------------------------
// 3. INSERCIÓN (Transacción)
// ----------------------------------------------------

$hashed_password = password_hash($contrasena, PASSWORD_DEFAULT);
$id_rol_cliente = 1; // ID 1 = 'cliente' (según servinet_db)
$fecha_registro = date('Y-m-d H:i:s');
$activo = 1;

$conn->begin_transaction();

try {
    
    // 1. INSERTAR EN LA TABLA CUENTA (Añadir campo municipio)
    $sql_cuenta = "INSERT INTO cuenta (correo, contraseña, nombre, apellido_paterno, apellido_materno, telefono, municipio, fecha_registro, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_cuenta = $conn->prepare($sql_cuenta);
    
    // Tipos de parámetros: ssssssssi (9)
    $stmt_cuenta->bind_param("ssssssssi", 
        $correo, 
        $hashed_password, 
        $nombre, 
        $apellido_paterno, 
        $apellido_materno, 
        $telefono, 
        $municipio, // <-- Usamos la variable capturada
        $fecha_registro, 
        $activo
    );
    
    if (!$stmt_cuenta->execute()) {
        throw new Exception("Error al insertar en la tabla cuenta: " . $stmt_cuenta->error);
    }
    
    $id_nueva_cuenta = $conn->insert_id;
    $stmt_cuenta->close();
    
    // 2. ASIGNAR ROL (CLIENTE)
    $sql_rol = "INSERT INTO cuentarol (id_cuenta, id_rol) VALUES (?, ?)";
    $stmt_rol = $conn->prepare($sql_rol);
    $stmt_rol->bind_param("ii", $id_nueva_cuenta, $id_rol_cliente);
    
    if (!$stmt_rol->execute()) {
        throw new Exception("Error al asignar rol: " . $stmt_rol->error);
    }
    $stmt_rol->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Registro de cliente exitoso.',
        'user' => [
            'id_cuenta' => $id_nueva_cuenta, 
            'nombre' => $nombre, 
            'rol' => 'cliente'
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error de registro: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Fallo en el registro. Intenta más tarde.']);
}


$conn->close();
?>