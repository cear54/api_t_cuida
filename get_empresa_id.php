<?php
require_once 'config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$stmt = $pdo->query("SELECT DISTINCT empresa_id FROM salidas LIMIT 1");
$resultado = $stmt->fetch();
echo $resultado['empresa_id'];
?>