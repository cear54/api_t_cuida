<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar OPTIONS request para CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Verificar que el método sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Incluir archivos necesarios
include_once '../config/database.php';
include_once '../utils/JWTHandler.php';

// Verificar token JWT - Múltiples métodos para obtener el header Authorization
$authHeader = null;
$headers = getallheaders();

if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
} elseif (isset($headers['authorization'])) {
    $authHeader = $headers['authorization'];
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['HTTP_X_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_X_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $apacheHeaders = apache_request_headers();
    if (isset($apacheHeaders['Authorization'])) {
        $authHeader = $apacheHeaders['Authorization'];
    }
}

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['error' => 'Token requerido']);
    exit;
}

$token = substr($authHeader, 7);

try {
    $jwtHandler = new JWTHandler();
    $payload = $jwtHandler->verifyToken($token);
    
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido o expirado']);
        exit;
    }
    
    $userId = $payload['user_id'];
    $personalId = $payload['personal_id']; // ID del personal/educadora
    $empresaId = $payload['empresa_id']; // Obtener empresa_id del token
    
    if (!$empresaId) {
        http_response_code(401);
        echo json_encode(['error' => 'Token no contiene información de empresa']);
        exit;
    }
    
    if (!$personalId) {
        http_response_code(401);
        echo json_encode(['error' => 'Token no contiene información de personal']);
        exit;
    }
    
    // Validar suscripción de la empresa
    require_once '../middleware/subscription_validator.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $subscriptionStatus = SubscriptionValidator::validateSubscription($db, $empresaId);
    
    if (!$subscriptionStatus['valid']) {
        http_response_code($subscriptionStatus['code']);
        echo json_encode([
            'success' => false,
            'message' => $subscriptionStatus['message']
        ]);
        exit;
    }
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

// Obtener datos del cuerpo de la petición
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['nino_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID del niño requerido']);
    exit;
}

$ninoId = $input['nino_id'];

// Validar que desayuno sea obligatorio
if (!isset($input['desayuno']) || $input['desayuno'] === null || $input['desayuno'] === '') {
    http_response_code(400);
    echo json_encode(['error' => 'El campo desayuno es obligatorio']);
    exit;
}

// Datos de alimentación
$desayuno = $input['desayuno'];
$colacion = $input['colacion'] ?? null;
$comida = $input['comida'] ?? null;

// Datos de sueño/descanso - sin valores por defecto
$suenoDescanso = isset($input['sueno_descanso']) && $input['sueno_descanso'] !== null ? ($input['sueno_descanso'] ? 'Si' : 'No') : null;
$tiempoSiesta = $input['tiempo_siesta'] ?? null;

// Datos de baño - sin valores por defecto
$pipi = isset($input['pipi']) && $input['pipi'] !== null ? ($input['pipi'] ? 'Si' : 'No') : null;
$numeroVecesPipi = $input['numero_veces_pipi'] ?? null;
$popo = isset($input['popo']) && $input['popo'] !== null ? ($input['popo'] ? 'Si' : 'No') : null;
$numeroVecesPopo = $input['numero_veces_popo'] ?? null;

// Datos de aviso - sin valores por defecto
$avisopipiPopo = isset($input['aviso_pipi_popo']) && $input['aviso_pipi_popo'] !== null ? ($input['aviso_pipi_popo'] ? 'Si' : 'No') : null;
$cuandoAviso = $input['cuando_aviso'] ?? null;
$cuantasVecesAviso = $input['cuantas_veces_aviso'] ?? null;

// Estado de ánimo
$estadoAnimo = $input['estado_animo'] ?? null;

// Accidentes y salud
$tuvoAccidente = isset($input['tuvo_accidente']) ? (bool)$input['tuvo_accidente'] : false;
$descripcionAccidente = $input['descripcion_accidente'] ?? null;
$problemaSalud = isset($input['problema_salud']) ? (bool)$input['problema_salud'] : false;
$descripcionSalud = $input['descripcion_salud'] ?? null;

// Observaciones
$observaciones = $input['observaciones'] ?? null;

// Imágenes (pueden venir como array o como campos individuales)
$imagen1 = null;
$imagen2 = null;
$imagen3 = null;

if (isset($input['imagenes']) && is_array($input['imagenes'])) {
    // Si vienen como array (desde Flutter con subida de imágenes)
    $imagenesArray = $input['imagenes'];
    if (count($imagenesArray) > 0) $imagen1 = $imagenesArray[0];
    if (count($imagenesArray) > 1) $imagen2 = $imagenesArray[1];
    if (count($imagenesArray) > 2) $imagen3 = $imagenesArray[2];
} else {
    // Si vienen como campos individuales (formato anterior)
    $imagen1 = $input['imagen1'] ?? null;
    $imagen2 = $input['imagen2'] ?? null;
    $imagen3 = $input['imagen3'] ?? null;
}

// Validaciones básicas - Solo desayuno es obligatorio
if (!$desayuno) {
    http_response_code(400);
    echo json_encode(['error' => 'Información de desayuno requerida']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verificar que el niño pertenezca a la misma empresa
    $checkQuery = "SELECT n.id FROM ninos n 
                   WHERE n.id = :nino_id 
                   AND n.empresa_id = :empresa_id 
                   AND n.activo = 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':nino_id', $ninoId, PDO::PARAM_INT);
    $checkStmt->bindParam(':empresa_id', $empresaId, PDO::PARAM_STR);
    $checkStmt->execute();

    if ($checkStmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Niño no encontrado o no pertenece a tu empresa']);
        exit;
    }

    // Verificar si ya existe una bitácora para hoy
    $today = date('Y-m-d');
    $existingQuery = "SELECT id FROM bitacoras 
                      WHERE nino_id = :nino_id 
                      AND empresa_id = :empresa_id 
                      AND DATE(fecha) = :fecha";
    $existingStmt = $db->prepare($existingQuery);
    $existingStmt->bindParam(':nino_id', $ninoId, PDO::PARAM_INT);
    $existingStmt->bindParam(':empresa_id', $empresaId, PDO::PARAM_STR);
    $existingStmt->bindParam(':fecha', $today);
    $existingStmt->execute();

    if ($existingStmt->rowCount() > 0) {
        // Actualizar registro existente
        $existing = $existingStmt->fetch();
        $bitacoraId = $existing['id'];
        
        $updateQuery = "UPDATE bitacoras 
                        SET desayuno = :desayuno,
                            colacion = :colacion,
                            comida = :comida,
                            sueno_descanso = :sueno_descanso,
                            tiempo_siesta = :tiempo_siesta,
                            pipi = :pipi,
                            numero_veces_pipi = :numero_veces_pipi,
                            popo = :popo,
                            numero_veces_popo = :numero_veces_popo,
                            aviso_pipi_popo = :aviso_pipi_popo,
                            cuando_aviso = :cuando_aviso,
                            cuantas_veces_aviso = :cuantas_veces_aviso,
                            estado_animo = :estado_animo,
                            tuvo_accidente = :tuvo_accidente,
                            descripcion_accidente = :descripcion_accidente,
                            problema_salud = :problema_salud,
                            descripcion_salud = :descripcion_salud,
                            observaciones = :observaciones,
                            imagen1 = :imagen1,
                            imagen2 = :imagen2,
                            imagen3 = :imagen3,
                            educadora_id = :educadora_id,
                            updated_at = NOW()
                        WHERE id = :bitacora_id";
        
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':desayuno', $desayuno);
        $updateStmt->bindParam(':colacion', $colacion);
        $updateStmt->bindParam(':comida', $comida);
        $updateStmt->bindParam(':sueno_descanso', $suenoDescanso);
        $updateStmt->bindParam(':tiempo_siesta', $tiempoSiesta);
        $updateStmt->bindParam(':pipi', $pipi);
        $updateStmt->bindParam(':numero_veces_pipi', $numeroVecesPipi);
        $updateStmt->bindParam(':popo', $popo);
        $updateStmt->bindParam(':numero_veces_popo', $numeroVecesPopo);
        $updateStmt->bindParam(':aviso_pipi_popo', $avisopipiPopo);
        $updateStmt->bindParam(':cuando_aviso', $cuandoAviso);
        $updateStmt->bindParam(':cuantas_veces_aviso', $cuantasVecesAviso);
        $updateStmt->bindParam(':estado_animo', $estadoAnimo);
        $updateStmt->bindParam(':tuvo_accidente', $tuvoAccidente, PDO::PARAM_BOOL);
        $updateStmt->bindParam(':descripcion_accidente', $descripcionAccidente);
        $updateStmt->bindParam(':problema_salud', $problemaSalud, PDO::PARAM_BOOL);
        $updateStmt->bindParam(':descripcion_salud', $descripcionSalud);
        $updateStmt->bindParam(':observaciones', $observaciones);
        $updateStmt->bindParam(':imagen1', $imagen1);
        $updateStmt->bindParam(':imagen2', $imagen2);
        $updateStmt->bindParam(':imagen3', $imagen3);
        $updateStmt->bindParam(':educadora_id', $personalId, PDO::PARAM_INT);
        $updateStmt->bindParam(':bitacora_id', $bitacoraId, PDO::PARAM_INT);

        if ($updateStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Bitácora actualizada exitosamente',
                'bitacora_id' => $bitacoraId,
                'action' => 'updated'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error al actualizar la bitácora']);
        }
    } else {
        // Crear nuevo registro
        $insertQuery = "INSERT INTO bitacoras (
                            nino_id, 
                            empresa_id, 
                            fecha, 
                            desayuno,
                            colacion,
                            comida,
                            sueno_descanso,
                            tiempo_siesta,
                            pipi,
                            numero_veces_pipi,
                            popo,
                            numero_veces_popo,
                            aviso_pipi_popo,
                            cuando_aviso,
                            cuantas_veces_aviso,
                            estado_animo,
                            tuvo_accidente,
                            descripcion_accidente,
                            problema_salud,
                            descripcion_salud,
                            observaciones,
                            imagen1,
                            imagen2,
                            imagen3,
                            educadora_id, 
                            created_at
                        ) VALUES (
                            :nino_id, 
                            :empresa_id, 
                            :fecha, 
                            :desayuno,
                            :colacion,
                            :comida,
                            :sueno_descanso,
                            :tiempo_siesta,
                            :pipi,
                            :numero_veces_pipi,
                            :popo,
                            :numero_veces_popo,
                            :aviso_pipi_popo,
                            :cuando_aviso,
                            :cuantas_veces_aviso,
                            :estado_animo,
                            :tuvo_accidente,
                            :descripcion_accidente,
                            :problema_salud,
                            :descripcion_salud,
                            :observaciones,
                            :imagen1,
                            :imagen2,
                            :imagen3,
                            :educadora_id, 
                            NOW()
                        )";
        
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bindParam(':nino_id', $ninoId, PDO::PARAM_INT);
        $insertStmt->bindParam(':empresa_id', $empresaId, PDO::PARAM_STR);
        $insertStmt->bindParam(':fecha', $today);
        $insertStmt->bindParam(':desayuno', $desayuno);
        $insertStmt->bindParam(':colacion', $colacion);
        $insertStmt->bindParam(':comida', $comida);
        $insertStmt->bindParam(':sueno_descanso', $suenoDescanso);
        $insertStmt->bindParam(':tiempo_siesta', $tiempoSiesta);
        $insertStmt->bindParam(':pipi', $pipi);
        $insertStmt->bindParam(':numero_veces_pipi', $numeroVecesPipi);
        $insertStmt->bindParam(':popo', $popo);
        $insertStmt->bindParam(':numero_veces_popo', $numeroVecesPopo);
        $insertStmt->bindParam(':aviso_pipi_popo', $avisopipiPopo);
        $insertStmt->bindParam(':cuando_aviso', $cuandoAviso);
        $insertStmt->bindParam(':cuantas_veces_aviso', $cuantasVecesAviso);
        $insertStmt->bindParam(':estado_animo', $estadoAnimo);
        $insertStmt->bindParam(':tuvo_accidente', $tuvoAccidente, PDO::PARAM_BOOL);
        $insertStmt->bindParam(':descripcion_accidente', $descripcionAccidente);
        $insertStmt->bindParam(':problema_salud', $problemaSalud, PDO::PARAM_BOOL);
        $insertStmt->bindParam(':descripcion_salud', $descripcionSalud);
        $insertStmt->bindParam(':observaciones', $observaciones);
        $insertStmt->bindParam(':imagen1', $imagen1);
        $insertStmt->bindParam(':imagen2', $imagen2);
        $insertStmt->bindParam(':imagen3', $imagen3);
        $insertStmt->bindParam(':educadora_id', $personalId, PDO::PARAM_INT);

        if ($insertStmt->execute()) {
            $bitacoraId = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Bitácora creada exitosamente',
                'bitacora_id' => $bitacoraId,
                'action' => 'created'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error al crear la bitácora']);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
