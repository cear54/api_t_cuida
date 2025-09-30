<?php
// Configurar zona horaria para México
date_default_timezone_set('America/Mexico_City');

// Cargar variables de entorno
require_once __DIR__ . '/env.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    public $conn;

    public function __construct() {
        // Cargar configuración desde variables de entorno
        $this->host = EnvLoader::get('DB_HOST', 'localhost');
        $this->db_name = EnvLoader::get('DB_NAME', 'estancias');
        $this->username = EnvLoader::get('DB_USERNAME', 'root');
        $this->password = EnvLoader::get('DB_PASSWORD', '');
        $this->charset = EnvLoader::get('DB_CHARSET', 'utf8');
    }

    // Método para obtener la conexión a la base de datos
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            $debug = EnvLoader::getBool('APP_DEBUG', false);
            $message = $debug ? "Error de conexión: " . $exception->getMessage() : "Error de conexión a la base de datos";
            throw new Exception($message);
        }

        return $this->conn;
    }
}
?>
