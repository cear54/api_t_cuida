<?php
// Log file para debugging
$logFile = '../logs/bitacora_debug.log';
function writeDebugLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeDebugLog('=== INICIO REQUEST bitacora_comportamiento.php ===');
writeDebugLog('Method: ' . $_SERVER['REQUEST_METHOD']);
writeDebugLog('Headers: ' . json_encode(getallheaders()));

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
include_once '../includes/timezone_helper.php';

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
    writeDebugLog('ERROR: Token no encontrado en headers');
    error_log('[bitacora_comportamiento.php] ERROR: Token no encontrado en headers');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token requerido',
        'debug' => 'No se encontró Authorization header'
    ]);
    exit;
}

$token = substr($authHeader, 7);
writeDebugLog('Token recibido: ' . substr($token, 0, 30) . '...');
error_log('[bitacora_comportamiento.php] Token recibido: ' . substr($token, 0, 20) . '...');

try {
    $jwtHandler = new JWTHandler();
    $payload = $jwtHandler->verifyToken($token);
    
    writeDebugLog('Payload: ' . json_encode($payload));
    
    if (!$payload) {
        writeDebugLog('ERROR: Token inválido o expirado');
        error_log('[bitacora_comportamiento.php] ERROR: Token inválido o expirado');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Token inválido o expirado',
            'debug' => 'verifyToken returned false'
        ]);
        exit;
    }
    
    error_log('[bitacora_comportamiento.php] Payload decodificado: ' . json_encode($payload));
    
    $userId = $payload['user_id'];
    $personalId = $payload['personal_id'] ?? null; // ID del personal/educadora (puede ser null para familias)
    $empresaId = $payload['empresa_id']; // Obtener empresa_id del token
    $userTipo = $payload['tipo_usuario'] ?? null; // Tipo de usuario (familiar, educadora, administrador)
    
    writeDebugLog('userId: ' . $userId . ', personalId: ' . ($personalId ?? 'NULL') . ', empresaId: ' . $empresaId . ', tipo_usuario: ' . ($userTipo ?? 'NULL'));
    error_log('[bitacora_comportamiento.php] userId: ' . $userId . ', personalId: ' . ($personalId ?? 'NULL') . ', empresaId: ' . $empresaId . ', tipo_usuario: ' . ($userTipo ?? 'NULL'));
    
    if (!$empresaId) {
        writeDebugLog('ERROR: Token no contiene empresa_id');
        error_log('[bitacora_comportamiento.php] ERROR: Token no contiene empresa_id');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Token no contiene información de empresa',
            'debug' => 'empresa_id missing from payload'
        ]);
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
    writeDebugLog('EXCEPTION en verificación de token: ' . $e->getMessage());
    error_log('[bitacora_comportamiento.php] EXCEPTION: ' . $e->getMessage());
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token inválido',
        'debug' => $e->getMessage()
    ]);
    exit;
}

// Obtener datos del cuerpo de la petición
$input = json_decode(file_get_contents('php://input'), true);
error_log('[bitacora_comportamiento.php] Input recibido: ' . json_encode($input));

if (!$input || !isset($input['nino_id'])) {
    error_log('[bitacora_comportamiento.php] ERROR: nino_id no proporcionado');
    http_response_code(400);
    echo json_encode(['error' => 'ID del niño requerido']);
    exit;
}

$ninoId = $input['nino_id'];

// Verificar si es SOLO una actualización de comentarios de familia
$esSoloComentariosFamilia = isset($input['comentarios_familia']) && 
                             count(array_filter(array_keys($input), function($key) {
                                 return $key !== 'nino_id' && $key !== 'comentarios_familia';
                             })) === 0;

error_log('[bitacora_comportamiento.php] Es solo comentarios familia: ' . ($esSoloComentariosFamilia ? 'SI' : 'NO'));

// Si NO es solo comentarios de familia, validar personal_id y desayuno
if (!$esSoloComentariosFamilia) {
    if (!$personalId) {
        error_log('[bitacora_comportamiento.php] ERROR: personal_id requerido para bitácora completa');
        http_response_code(401);
        echo json_encode(['error' => 'Token no contiene información de personal']);
        exit;
    }
    
    // Validar que desayuno sea obligatorio
    if (!isset($input['desayuno']) || $input['desayuno'] === null || $input['desayuno'] === '') {
        error_log('[bitacora_comportamiento.php] ERROR: desayuno es obligatorio');
        http_response_code(400);
        echo json_encode(['error' => 'El campo desayuno es obligatorio']);
        exit;
    }
} else {
    // Para comentarios de familia, validar que el usuario sea tipo 'familia'
    if ($userTipo !== 'familia') {
        writeDebugLog('ERROR: Usuario no es de tipo familia. Tipo recibido: ' . ($userTipo ?? 'NULL'));
        error_log('[bitacora_comportamiento.php] ERROR: Usuario no es de tipo familia. tipo_usuario: ' . ($userTipo ?? 'NULL'));
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Solo usuarios tipo familia pueden agregar comentarios',
            'debug' => 'tipo_usuario en token: ' . ($userTipo ?? 'NULL') . ', esperado: familia'
        ]);
        exit;
    }
    writeDebugLog('Usuario validado como tipo familia');
    error_log('[bitacora_comportamiento.php] Usuario validado como tipo familia');
}

// Inicializar variables por defecto
$desayuno = null;
$colacion = null;
$comida = null;
$suenoDescanso = null;
$tiempoSiesta = null;
$pipi = null;
$numeroVecesPipi = null;
$popo = null;
$numeroVecesPopo = null;
$avisopipiPopo = null;
$cuandoAviso = null;
$cuantasVecesAviso = null;
$estadoAnimo = null;
$tuvoAccidente = false;
$descripcionAccidente = null;
$problemaSalud = false;
$descripcionSalud = null;
$observaciones = null;
$imagen1 = null;
$imagen2 = null;
$imagen3 = null;

// Si NO es solo comentarios, procesar todos los campos de bitácora
if (!$esSoloComentariosFamilia) {
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
}

// Comentarios de familia (procesarindependientemente del tipo de request)
$comentariosFamilia = $input['comentarios_familia'] ?? null;
$comentariosFamiliaUserId = null;
$comentariosFamiliaFecha = null;

if ($comentariosFamilia) {
    $comentariosFamiliaUserId = $userId;
    $comentariosFamiliaFecha = date('Y-m-d H:i:s');
    error_log('[bitacora_comportamiento.php] Procesando comentarios de familia. UserId: ' . $userId);
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
    $today = TimezoneHelper::getCurrentDate();
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
        
        if ($esSoloComentariosFamilia) {
            // Si solo se están actualizando comentarios de familia
            error_log('[bitacora_comportamiento.php] Actualizando solo comentarios de familia en bitácora existente');
            
            $updateQuery = "UPDATE bitacoras 
                            SET comentarios_familia = :comentarios_familia,
                                comentarios_familia_user_id = :comentarios_familia_user_id,
                                comentarios_familia_fecha = :comentarios_familia_fecha,
                                updated_at = NOW()
                            WHERE id = :bitacora_id";
            
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':comentarios_familia', $comentariosFamilia);
            $updateStmt->bindParam(':comentarios_familia_user_id', $comentariosFamiliaUserId, PDO::PARAM_INT);
            $updateStmt->bindParam(':comentarios_familia_fecha', $comentariosFamiliaFecha);
            $updateStmt->bindParam(':bitacora_id', $bitacoraId, PDO::PARAM_INT);
            
        } else {
            // Actualizar bitácora completa
            error_log('[bitacora_comportamiento.php] Actualizando bitácora completa');
            
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
                                comentarios_familia = :comentarios_familia,
                                comentarios_familia_user_id = :comentarios_familia_user_id,
                                comentarios_familia_fecha = :comentarios_familia_fecha,
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
            $updateStmt->bindParam(':comentarios_familia', $comentariosFamilia);
            $updateStmt->bindParam(':comentarios_familia_user_id', $comentariosFamiliaUserId, PDO::PARAM_INT);
            $updateStmt->bindParam(':comentarios_familia_fecha', $comentariosFamiliaFecha);
            $updateStmt->bindParam(':educadora_id', $personalId, PDO::PARAM_INT);
            $updateStmt->bindParam(':bitacora_id', $bitacoraId, PDO::PARAM_INT);
        }

        if ($updateStmt->execute()) {
            error_log('[bitacora_comportamiento.php] Bitácora actualizada exitosamente. ID: ' . $bitacoraId);
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
        // No existe bitácora para hoy
        
        // Si solo se están enviando comentarios de familia, no se puede crear una bitácora nueva
        if ($esSoloComentariosFamilia) {
            error_log('[bitacora_comportamiento.php] ERROR: Intento de crear bitácora con solo comentarios de familia');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'No se puede agregar comentarios si no existe una bitácora para hoy',
                'message' => 'La educadora debe registrar primero la bitácora del día'
            ]);
            exit;
        }
        
        // Crear nuevo registro de bitácora completa
        error_log('[bitacora_comportamiento.php] Creando nueva bitácora');
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
        $insertStmt->bindParam(':comentarios_familia', $comentariosFamilia);
        $insertStmt->bindParam(':comentarios_familia_user_id', $comentariosFamiliaUserId, PDO::PARAM_INT);
        $insertStmt->bindParam(':comentarios_familia_fecha', $comentariosFamiliaFecha);

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
