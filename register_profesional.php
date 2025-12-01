<?php
// nandodm/servinet/ServiNet-35fb085a9f887b792b3d4ac23d22f72cd494ceec/api/auth/register_profesional.php
header('Content-Type: application/json');

// Incluir el archivo de configuración de la base de datos
// NOTA: La ruta debe ser relativa al archivo (asumiendo que está en /api/auth/)
// Usamos este include asumiendo que db_config.php está un nivel arriba.
include_once '../db_config.php';

// ----------------------------------------------------
// 0. VERIFICACIÓN CRÍTICA DE CONEXIÓN A LA DB
// ----------------------------------------------------
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    // 🚨 ESTE MENSAJE EXPONDRÁ EL ERROR DE CONEXIÓN (TEMPORAL) 🚨
    echo json_encode(['success' => false, 'message' => 'ERROR DB: Fallo al conectar. Revise DB_config o el nombre de la DB. Detalle: ' . ($conn->connect_error ?? 'No se cargó la conexión.')]);
    exit;
}

// Asegurarse de que el método sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// ----------------------------------------------------
// 1. OBTENER Y VALIDAR DATOS DEL FORMULARIO
// ----------------------------------------------------

// Datos de la tabla cuenta
$correo = $_POST['correo'] ?? '';
$contrasena = $_POST['contrasena'] ?? '';
$nombre = $_POST['nombre'] ?? '';
$apellido_paterno = $_POST['apellido_paterno'] ?? '';
$apellido_materno = $_POST['apellido_materno'] ?? '';
$telefono = $_POST['telefono'] ?? '';
$municipio = $_POST['municipio'] ?? ''; 

// Datos específicos de la tabla perfilprofesional
$id_categoria_principal = $_POST['id_categoria_principal'] ?? 0;
$especialidades = $_POST['especialidades'] ?? '';
$experiencia_años = $_POST['experiencia'] ?? 0;
$tarifa = $_POST['tarifa'] ?? 0.00;
$descripcion_perfil = $_POST['descripcion'] ?? '';

// Nuevos campos opcionales/control
$nombre_negocio = $_POST['nombre_negocio'] ?? NULL;
$sitio_web = $_POST['sitio_web'] ?? NULL;
$estado_verificacion = $_POST['estado_verificacion'] ?? 'pendiente'; 
$disponibilidad_default = 'Bajo Demanda'; 


// ----------------------------------------------------
// 2. VALIDACIÓN DE CAMPOS OBLIGATORIOS
// ----------------------------------------------------

if (empty($correo) || empty($contrasena) || empty($nombre) || empty($apellido_paterno) || empty($telefono) || empty($municipio) || empty($especialidades) || empty($descripcion_perfil) || empty($id_categoria_principal)) {
    echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios de cuenta, perfil o categoría principal.']);
    $conn->close();
    exit;
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Formato de correo no válido.']);
    $conn->close();
    exit;
}

// ----------------------------------------------------
// 3. VERIFICAR DUPLICADOS
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
// 4. INICIO DE TRANSACCIÓN
// ----------------------------------------------------

$hashed_password = password_hash($contrasena, PASSWORD_DEFAULT);
$id_rol_profesional = 2; // Rol ID 2 es 'profesional'
$fecha_registro = date('Y-m-d H:i:s');
$activo = 1;

$conn->begin_transaction();

try {
    
    // 5. INSERTAR EN LA TABLA CUENTA
    $sql_cuenta = "INSERT INTO cuenta (correo, contraseña, nombre, apellido_paterno, apellido_materno, telefono, municipio, fecha_registro, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_cuenta = $conn->prepare($sql_cuenta);
    
    // Tipos: ssssssssi (9 parámetros)
    $stmt_cuenta->bind_param("ssssssssi", 
        $correo, 
        $hashed_password, 
        $nombre, 
        $apellido_paterno, 
        $apellido_materno, 
        $telefono, 
        $municipio, 
        $fecha_registro, 
        $activo
    );
    
    if (!$stmt_cuenta->execute()) {
        throw new Exception("Error al insertar en la tabla cuenta: " . $stmt_cuenta->error);
    }
    
    $id_nueva_cuenta = $conn->insert_id;
    $stmt_cuenta->close();
    
    // 6. ASIGNAR ROL (PROFESIONAL)
    $sql_rol = "INSERT INTO cuentarol (id_cuenta, id_rol) VALUES (?, ?)";
    $stmt_rol = $conn->prepare($sql_rol);
    $stmt_rol->bind_param("ii", $id_nueva_cuenta, $id_rol_profesional);
    
    if (!$stmt_rol->execute()) {
        throw new Exception("Error al asignar rol: " . $stmt_rol->error);
    }
    $stmt_rol->close();

    // 7. INSERTAR EN LA TABLA PERFILPROFESIONAL (CON SOLUCIÓN DE TIPOS)
    $sql_perfil = "INSERT INTO perfilprofesional (
        id_cuenta, id_categoria_principal, especialidades, experiencia, tarifa, disponibilidad, descripcion, fecha_creacion, nombre_negocio, sitio_web, estado_verificacion
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_perfil = $conn->prepare($sql_perfil);
    
    // CADENA DE TIPOS: iisssssssss (11 parámetros - todos los campos de texto y decimal/int como string)
    $stmt_perfil->bind_param("iisssssssss", 
        $id_nueva_cuenta, 
        $id_categoria_principal, 
        $especialidades, 
        $experiencia_años, 
        $tarifa,           
        $disponibilidad_default,
        $descripcion_perfil,
        $fecha_registro,
        $nombre_negocio, 
        $sitio_web,      
        $estado_verificacion 
    );

    if (!$stmt_perfil->execute()) {
        // La excepción captura el error de MySQLi si falla la inserción
        throw new Exception("Error al insertar en la tabla perfilprofesional: " . $stmt_perfil->error);
    }
    $stmt_perfil->close();
    
    // 8. COMMIT DE LA TRANSACCIÓN
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Registro de profesional exitoso. Su perfil será revisado por el administrador.',
        'user' => [
            'id_cuenta' => $id_nueva_cuenta, 
            'nombre' => $nombre, 
            'rol' => 'profesional'
        ]
    ]);
    
} catch (Exception $e) {
    // 9. ROLLBACK Y RESPUESTA PARA DEPURACIÓN
    $conn->rollback();
    
    // 🚨 ESTE MENSAJE EXPONDRÁ EL ERROR ESPECÍFICO DE LA TRANSACCIÓN 🚨
    $mensaje_error_depuracion = 'Fallo en la transacción. Error específico: ' . $e->getMessage();
    
    error_log("Error de registro profesional: " . $e->getMessage()); 
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => $mensaje_error_depuracion]);
}

$conn->close();
?>