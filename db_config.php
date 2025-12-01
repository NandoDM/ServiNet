<?php
// nandodm/servinet/ServiNet-e728ea1c53d2fa493dc4a4edd362433270b9cb26/db_config.php
// Configuración de la base de datos
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');    
define('DB_PASSWORD', '');        
define('DB_NAME', 'servinet_db'); 

// Variable global de conexión
$conn = null;

// Intentar conexión a la base de datos MySQL
try {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    // Verificar si ocurrió un error de conexión
    if ($conn->connect_error) {
        // Lanzamos una excepción, pero solo se registrará en el log, no se mostrará.
        throw new Exception("Fallo de conexión a la base de datos: " . $conn->connect_error);
    }

    // Establecer el conjunto de caracteres a UTF8 (recomendado)
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // Solo registramos el error para depuración
    error_log("ERROR CRÍTICO DB: " . $e->getMessage()); 
    $conn = null; 
}
// Nota: El script llamador (la API) debe verificar si $conn es null antes de usarlo.
?>