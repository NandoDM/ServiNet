<?php
// nandodm/servinet/ServiNet-35fb085a9f887b792b3d4ac23d22f72cd494ceec/api/delete_account.php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST");

require_once 'db_config.php'; 

$response = ['success' => false, 'message' => 'Error al procesar la solicitud.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// 1. VERIFICACIÓN DE SESIÓN
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $response['message'] = 'Acceso denegado. No autenticado.';
    echo json_encode($response);
    exit;
}

$id_cuenta = $_SESSION['id_cuenta'] ?? 0;
$password = $_POST['password'] ?? ''; 
$rol = $_SESSION['rol'] ?? 'usuario';

if (empty($id_cuenta) || empty($password)) {
    $response['message'] = 'Faltan datos de autenticación.';
    echo json_encode($response);
    exit;
}

$conn->begin_transaction();

try {
    // 2. VERIFICAR CONTRASEÑA ACTUAL
    $sql_verify = "SELECT contraseña FROM Cuenta WHERE id_cuenta = ?";
    $stmt_verify = $conn->prepare($sql_verify);
    $stmt_verify->bind_param("i", $id_cuenta);
    $stmt_verify->execute();
    $result_verify = $stmt_verify->get_result();
    $user = $result_verify->fetch_assoc();
    $stmt_verify->close();

    if (!$user || !password_verify($password, $user['contraseña'])) {
        $response['message'] = 'Contraseña incorrecta. Eliminación denegada.';
        echo json_encode($response);
        exit;
    }

    // 3. ELIMINAR CUENTA (ON DELETE CASCADE hará el resto)
    $sql_delete = "DELETE FROM Cuenta WHERE id_cuenta = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $id_cuenta);

    if (!$stmt_delete->execute()) {
        throw new Exception("Error al ejecutar la eliminación: " . $stmt_delete->error);
    }
    $stmt_delete->close();
    
    // 4. COMMIT Y CIERRE DE SESIÓN
    $conn->commit();
    
    // Cerrar sesión antes de responder
    session_destroy();

    $response['success'] = true;
    $response['message'] = "La cuenta de {$rol} ha sido eliminada permanentemente.";

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error de eliminación de cuenta: " . $e->getMessage());
    $response['message'] = 'Fallo en la eliminación: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>