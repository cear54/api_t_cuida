<?php
// Configuración para ambiente de producción
class DatabaseProduction {
    private $host = "localhost"; // Cambiar por IP del servidor de BD
    private $db_name = "u413241405_estancias"; // Nombre de tu BD en producción
    private $username = "u413241405_estancias"; // Usuario de BD en producción
    private $password = "Mrcear01061968"; // Password de BD en producción
    private $charset = "utf8mb4";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $exception) {
            error_log("Error de conexión: " . $exception->getMessage());
            return null;
        }

        return $this->conn;
    }
}
?>
