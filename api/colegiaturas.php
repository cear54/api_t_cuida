<?php
// Última actualización: 2025-10-25 - Mensajes de error mejorados
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Debug logging
error_log("🔍 DEBUG: colegiaturas.php iniciado - Versión actualizada 2025-10-25");
error_log("🔍 DEBUG: REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    error_log("🔍 DEBUG: Handling OPTIONS request");
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../utils/JWTHandler.php';

// Función para enviar respuesta JSON
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    error_log("📤 DEBUG: Enviando respuesta - Success: $success, Message: $message, Status: $statusCode");
    http_response_code($statusCode);
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

// Validar el token JWT
try {
    error_log("🔐 DEBUG: Validando token JWT...");
    $decodedToken = JWTHandler::requireAuth();
    
    // Debug completo del token
    error_log("🔍 DEBUG: Token decodificado completo: " . json_encode($decodedToken));
    
    // Mapear correctamente los campos del token
    $usuarioId = $decodedToken['user_id'] ?? $decodedToken['id'] ?? null;
    $empresaId = $decodedToken['empresa_id'] ?? null;
    
    if (!$usuarioId || !$empresaId) {
        error_log("❌ DEBUG: Datos faltantes en token - UsuarioId: $usuarioId, EmpresaId: $empresaId");
        sendJsonResponse(false, 'Token incompleto - faltan datos de usuario o empresa', null, 401);
    }
    
    error_log("✅ DEBUG: Token válido - UsuarioId: $usuarioId, EmpresaId: $empresaId");
} catch (Exception $e) {
    error_log("❌ DEBUG: Error en token: " . $e->getMessage());
    sendJsonResponse(false, 'Token inválido: ' . $e->getMessage(), null, 401);
}

// Obtener datos del request
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

error_log("📥 DEBUG: Input recibido: " . json_encode($input));
error_log("🎯 DEBUG: Action: $action");

// Crear conexión a la base de datos
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    sendJsonResponse(false, 'Error de conexión a la base de datos', null, 500);
}

switch ($action) {
    case 'get_by_nino':
        handleGetByNino($db, $input, $usuarioId, $empresaId, $decodedToken);
        break;
    
    case 'get_historial':
        handleGetHistorial($db, $input, $usuarioId, $empresaId, $decodedToken);
        break;
    
    case 'get_resumen':
        handleGetResumen($db, $input, $usuarioId, $empresaId, $decodedToken);
        break;
    
    default:
        sendJsonResponse(false, 'Acción no válida', null, 400);
}

function handleGetByNino($db, $input, $usuarioId, $empresaId, $decodedToken) {
    try {
        error_log("🔍 DEBUG: handleGetByNino - UsuarioId: $usuarioId, EmpresaId: $empresaId");
        
        // Primero verificar si el usuario existe en la base de datos
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM usuarios_app WHERE id = ?");
        $stmt->execute([$usuarioId]);
        $userExists = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("👤 DEBUG: Usuario ID $usuarioId existe en DB: " . ($userExists['count'] > 0 ? 'SÍ' : 'NO'));
        
        // Verificar usuarios en la empresa
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM usuarios_app WHERE empresa_id = ?");
        $stmt->execute([$empresaId]);
        $companyUsers = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("🏢 DEBUG: Usuarios en empresa $empresaId: " . $companyUsers['count']);
        
        // Obtener información del usuario autenticado
        $stmt = $db->prepare("
            SELECT tipo_usuario, nino_id, activo 
            FROM usuarios_app 
            WHERE id = ? AND empresa_id = ?
        ");
        $stmt->execute([$usuarioId, $empresaId]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("👤 DEBUG: Usuario encontrado: " . json_encode($usuario));
        
        if (!$usuario) {
            error_log("❌ DEBUG: Usuario no encontrado con ID $usuarioId y empresa $empresaId");
            
            // Debug adicional: buscar el usuario sin filtro de empresa
            $stmt = $db->prepare("SELECT id, empresa_id, activo FROM usuarios_app WHERE id = ?");
            $stmt->execute([$usuarioId]);
            $userAnyCompany = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("🔍 DEBUG: Usuario en cualquier empresa: " . json_encode($userAnyCompany));
            
            sendJsonResponse(false, 'Usuario no encontrado o inactivo', null, 403);
        }
        
        if ($usuario['activo'] != 1) {
            error_log("❌ DEBUG: Usuario inactivo");
            sendJsonResponse(false, 'Usuario inactivo', null, 403);
        }

        // Determinar el nino_id según el tipo de usuario
        if ($usuario['tipo_usuario'] === 'familia') {
            // Para usuarios tipo familia, usar el nino_id del token
            $ninoId = $decodedToken['nino_id'] ?? $usuario['nino_id'] ?? null;
            error_log("👨‍👩‍👧‍👦 DEBUG: Usuario familia - ninoId del token: $ninoId");
            if (empty($ninoId)) {
                error_log("❌ DEBUG: Usuario familia sin niño asociado");
                sendJsonResponse(false, 'No hay ningún menor asociado a esta cuenta familiar', null, 403);
            }
        } else {
            // Para otros tipos de usuario (admin, personal), permitir consultar cualquier niño
            if (empty($input['nino_id'])) {
                error_log("❌ DEBUG: Usuario no-familia sin nino_id en request");
                sendJsonResponse(false, 'ID del niño es requerido', null, 400);
            }
            $ninoId = $input['nino_id'];
            error_log("👮 DEBUG: Usuario admin/personal - ninoId del request: $ninoId");
        }

        // Obtener colegiaturas activas del niño con información del niño y nombres de períodos
        error_log("🔍 DEBUG: Buscando colegiaturas para ninoId: $ninoId, empresaId: $empresaId");
        $stmt = $db->prepare("
            SELECT 
                c.*,
                n.nombre as nino_nombre,
                n.apellido_paterno,
                n.apellido_materno,
                np.periodo_1, np.periodo_2, np.periodo_3, np.periodo_4, np.periodo_5, np.periodo_6,
                np.periodo_7, np.periodo_8, np.periodo_9, np.periodo_10, np.periodo_11, np.periodo_12,
                np.periodo_13, np.periodo_14, np.periodo_15, np.periodo_16, np.periodo_17
            FROM colegiaturas_2 c
            JOIN ninos n ON c.nino_id = n.id
            LEFT JOIN nombres_periodos np ON c.id = np.ciclo_id
            WHERE c.nino_id = ? 
            AND c.empresa_id = ? 
            AND c.activo = 1
            ORDER BY c.fecha_inicio DESC
            LIMIT 1
        ");
        $stmt->execute([$ninoId, $empresaId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("📊 DEBUG: Resultado de consulta: " . ($result ? "ENCONTRADO" : "NO ENCONTRADO"));
        
        if ($result) {
            // Agregar nombre completo
            $result['nino_nombre_completo'] = trim($result['nino_nombre'] . ' ' . $result['apellido_paterno'] . ' ' . $result['apellido_materno']);
            
            // Procesar nombres de períodos - solo incluir los que no sean "sin_definir" y tengan monto
            $result['nombres_periodos'] = [];
            $periodosExcluidos = [];
            for ($i = 1; $i <= 17; $i++) {
                $nombrePeriodo = $result["periodo_$i"] ?? 'sin_definir';
                $monto = floatval($result["pago_$i"] ?? 0);
                
                // Solo incluir si tiene nombre válido Y tiene monto configurado
                if ($nombrePeriodo !== 'sin_definir' && !empty($nombrePeriodo) && $monto > 0) {
                    $result['nombres_periodos'][$i] = $nombrePeriodo;
                } else if ($nombrePeriodo === 'sin_definir' || empty($nombrePeriodo) || $monto <= 0) {
                    $periodosExcluidos[] = "P$i (nombre: '$nombrePeriodo', monto: $monto)";
                }
                // Limpiar los campos individuales del resultado
                unset($result["periodo_$i"]);
            }
            
            error_log("✅ DEBUG: Períodos incluidos: " . json_encode($result['nombres_periodos']));
            if (!empty($periodosExcluidos)) {
                error_log("🚫 DEBUG: Períodos excluidos: " . implode(", ", $periodosExcluidos));
            }
            
            error_log("✅ DEBUG: Colegiaturas encontradas para: " . $result['nino_nombre_completo']);
            error_log("📅 DEBUG: Períodos configurados: " . json_encode($result['nombres_periodos']));
            sendJsonResponse(true, 'Colegiaturas obtenidas exitosamente', $result);
        } else {
            error_log("❌ DEBUG: No se encontraron colegiaturas activas para niño ID: $ninoId");
            
            // Verificar si el niño existe
            $stmt = $db->prepare("SELECT nombre, apellido_paterno FROM ninos WHERE id = ?");
            $stmt->execute([$ninoId]);
            $nino = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($nino) {
                $nombreNino = trim($nino['nombre'] . ' ' . $nino['apellido_paterno']);
                error_log("👶 DEBUG: El niño '$nombreNino' existe pero no tiene colegiaturas activas");
                sendJsonResponse(false, "No se encontraron colegiaturas activas para $nombreNino. Las colegiaturas aún no han sido configuradas para este menor.", null, 200);
            } else {
                error_log("❌ DEBUG: El niño ID $ninoId no existe en la base de datos");
                sendJsonResponse(false, 'El menor no fue encontrado en el sistema', null, 404);
            }
        }

    } catch (Exception $e) {
        error_log("💥 DEBUG: Exception en handleGetByNino: " . $e->getMessage());
        sendJsonResponse(false, 'Error al obtener colegiaturas: ' . $e->getMessage(), null, 500);
    }
}

function handleGetHistorial($db, $input, $usuarioId, $empresaId, $decodedToken) {
    try {
        // Obtener información del usuario autenticado
        $stmt = $db->prepare("
            SELECT tipo_usuario, nino_id 
            FROM usuarios_app 
            WHERE id = ? AND empresa_id = ? AND activo = 1
        ");
        $stmt->execute([$usuarioId, $empresaId]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            sendJsonResponse(false, 'Usuario no encontrado o inactivo', null, 403);
        }

        // Determinar el nino_id según el tipo de usuario
        if ($usuario['tipo_usuario'] === 'familia') {
            // Para usuarios tipo familia, usar el nino_id del token
            $ninoId = $usuario['nino_id'];
            if (empty($ninoId)) {
                sendJsonResponse(false, 'No hay ningún menor asociado a esta cuenta familiar', null, 403);
            }
        } else {
            // Para otros tipos de usuario (admin, personal), permitir consultar cualquier niño
            if (empty($input['nino_id'])) {
                sendJsonResponse(false, 'ID del niño es requerido', null, 400);
            }
            $ninoId = $input['nino_id'];
        }

        // Obtener historial de colegiaturas
        $stmt = $db->prepare("
            SELECT 
                c.*,
                n.nombre as nino_nombre,
                n.apellido_paterno,
                n.apellido_materno
            FROM colegiaturas_2 c
            JOIN ninos n ON c.nino_id = n.id
            WHERE c.nino_id = ? 
            AND c.empresa_id = ?
            ORDER BY c.fecha_inicio DESC
        ");
        $stmt->execute([$ninoId, $empresaId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Procesar resultados para agregar información adicional
        foreach ($results as &$result) {
            $result['nino_nombre_completo'] = trim($result['nino_nombre'] . ' ' . $result['apellido_paterno'] . ' ' . $result['apellido_materno']);
            
            // Calcular estadísticas
            $pagosRealizados = 0;
            $montoTotal = 0;
            $montoPagado = 0;
            
            for ($i = 1; $i <= 17; $i++) {
                $monto = floatval($result["pago_$i"] ?? 0);
                if ($monto > 0) {
                    $montoTotal += $monto;
                    if ($result["pagado_$i"] == 1) {
                        $pagosRealizados++;
                        $montoPagado += $monto;
                    }
                }
            }
            
            $result['estadisticas'] = [
                'pagos_realizados' => $pagosRealizados,
                'monto_total' => $montoTotal,
                'monto_pagado' => $montoPagado,
                'monto_pendiente' => $montoTotal - $montoPagado
            ];
        }

        sendJsonResponse(true, 'Historial obtenido exitosamente', $results);

    } catch (Exception $e) {
        sendJsonResponse(false, 'Error al obtener historial: ' . $e->getMessage(), null, 500);
    }
}

function handleGetResumen($db, $input, $usuarioId, $empresaId, $decodedToken) {
    try {
        // Obtener información del usuario autenticado
        $stmt = $db->prepare("
            SELECT tipo_usuario, nino_id 
            FROM usuarios_app 
            WHERE id = ? AND empresa_id = ? AND activo = 1
        ");
        $stmt->execute([$usuarioId, $empresaId]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            sendJsonResponse(false, 'Usuario no encontrado o inactivo', null, 403);
        }

        // Determinar el nino_id según el tipo de usuario
        if ($usuario['tipo_usuario'] === 'familia') {
            // Para usuarios tipo familia, usar el nino_id del token
            $ninoId = $usuario['nino_id'];
            if (empty($ninoId)) {
                sendJsonResponse(false, 'No hay ningún menor asociado a esta cuenta familiar', null, 403);
            }
        } else {
            // Para otros tipos de usuario (admin, personal), permitir consultar cualquier niño
            if (empty($input['nino_id'])) {
                sendJsonResponse(false, 'ID del niño es requerido', null, 400);
            }
            $ninoId = $input['nino_id'];
        }

        // Obtener colegiaturas activas para el resumen
        $stmt = $db->prepare("
            SELECT 
                c.*,
                n.nombre as nino_nombre,
                n.apellido_paterno,
                n.apellido_materno
            FROM colegiaturas_2 c
            JOIN ninos n ON c.nino_id = n.id
            WHERE c.nino_id = ? 
            AND c.empresa_id = ? 
            AND c.activo = 1
            ORDER BY c.fecha_inicio DESC
            LIMIT 1
        ");
        $stmt->execute([$ninoId, $empresaId]);
        $colegiatura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$colegiatura) {
            sendJsonResponse(false, 'No se encontraron colegiaturas activas para generar resumen');
        }

        // Calcular resumen detallado - solo períodos con monto válido
        $totalPeriodos = 0;
        $pagosRealizados = 0;
        $montoTotal = 0;
        $montoPagado = 0;
        $proximoVencimiento = null;
        $ultimoPago = null;

        for ($i = 1; $i <= 17; $i++) {
            $monto = floatval($colegiatura["pago_$i"] ?? 0);
            // Solo procesar períodos con monto válido (mayor a 0)
            if ($monto > 0) {
                $totalPeriodos++;
                $montoTotal += $monto;
                
                if ($colegiatura["pagado_$i"] == 1) {
                    $pagosRealizados++;
                    $montoPagado += $monto;
                    
                    // Actualizar último pago
                    if ($colegiatura["fecha_pago_$i"]) {
                        if (!$ultimoPago || $colegiatura["fecha_pago_$i"] > $ultimoPago) {
                            $ultimoPago = $colegiatura["fecha_pago_$i"];
                        }
                    }
                } else if (!$proximoVencimiento) {
                    // Primer pago pendiente (próximo vencimiento)
                    $proximoVencimiento = [
                        'periodo' => $i,
                        'monto' => $monto
                    ];
                }
            }
        }

        $resumen = [
            'nino_nombre_completo' => trim($colegiatura['nino_nombre'] . ' ' . $colegiatura['apellido_paterno'] . ' ' . $colegiatura['apellido_materno']),
            'ciclo_escolar' => date('Y', strtotime($colegiatura['fecha_inicio'])) . '-' . date('Y', strtotime($colegiatura['fecha_fin'])),
            'fecha_inicio' => $colegiatura['fecha_inicio'],
            'fecha_fin' => $colegiatura['fecha_fin'],
            'total_periodos' => $totalPeriodos,
            'pagos_realizados' => $pagosRealizados,
            'pagos_pendientes' => $totalPeriodos - $pagosRealizados,
            'monto_total' => $montoTotal,
            'monto_pagado' => $montoPagado,
            'monto_pendiente' => $montoTotal - $montoPagado,
            'porcentaje_pagado' => $montoTotal > 0 ? round(($montoPagado / $montoTotal) * 100, 2) : 0,
            'proximo_vencimiento' => $proximoVencimiento,
            'ultimo_pago' => $ultimoPago
        ];

        sendJsonResponse(true, 'Resumen generado exitosamente', $resumen);

    } catch (Exception $e) {
        sendJsonResponse(false, 'Error al generar resumen: ' . $e->getMessage(), null, 500);
    }
}
?>