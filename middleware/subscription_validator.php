<?php
/**
 * Middleware para validar suscripciones de empresas
 * Este middleware verifica que la empresa del usuario tenga una suscripción activa y vigente
 */

class SubscriptionValidator {
    
    /**
     * Valida la suscripción de una empresa
     * 
     * @param PDO $db Conexión a la base de datos
     * @param string $empresa_id ID de la empresa a validar
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validateSubscription($db, $empresa_id) {
        try {
            $fecha_actual = date('Y-m-d');
            
            $query = "SELECT id, estado, fecha_fin, fecha_fin_prueba, en_periodo_prueba 
                      FROM suscripciones 
                      WHERE empresa_id = :empresa_id 
                      ORDER BY id DESC 
                      LIMIT 1";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":empresa_id", $empresa_id);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                return [
                    'valid' => false,
                    'message' => 'No se encontró una suscripción válida para su empresa. Contacte al administrador.',
                    'code' => 403
                ];
            }
            
            $suscripcion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verificar estado de la suscripción
            $estado = $suscripcion['estado'];
            if ($estado === 'vencida' || $estado === 'cancelada' || $estado === 'suspendida') {
                return [
                    'valid' => false,
                    'message' => "Su suscripción está " . $estado . ". Contacte al administrador para reactivarla.",
                    'code' => 403
                ];
            }
            
            // Verificar fechas de vencimiento
            $fecha_fin = $suscripcion['fecha_fin'];
            $fecha_fin_prueba = $suscripcion['fecha_fin_prueba'];
            
            // Si está en período de prueba, verificar fecha_fin_prueba
            if ($suscripcion['en_periodo_prueba'] == 1) {
                if (!empty($fecha_fin_prueba) && $fecha_actual > $fecha_fin_prueba) {
                    return [
                        'valid' => false,
                        'message' => 'El período de prueba ha finalizado. Contacte al administrador para renovar su suscripción.',
                        'code' => 403
                    ];
                }
            } else {
                // Si no está en prueba, verificar fecha_fin
                if (!empty($fecha_fin) && $fecha_actual > $fecha_fin) {
                    return [
                        'valid' => false,
                        'message' => 'Su suscripción ha expirado. Contacte al administrador para renovar.',
                        'code' => 403
                    ];
                }
            }
            
            // Suscripción válida
            return [
                'valid' => true,
                'message' => 'Suscripción válida',
                'code' => 200
            ];
            
        } catch (Exception $e) {
            return [
                'valid' => false,
                'message' => 'Error al verificar la suscripción: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
    
    /**
     * Middleware que verifica JWT y suscripción
     * Se debe incluir en endpoints protegidos después de los headers
     * 
     * @param PDO $db Conexión a la base de datos
     * @return array Datos del token decodificado si es válido
     */
    public static function validateAuthAndSubscription($db) {
        // Obtener headers de autorización
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : 
                     (isset($headers['authorization']) ? $headers['authorization'] : null);
        
        if (!$authHeader) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Token de autorización no proporcionado'
            ]);
            exit;
        }
        
        // Extraer el token del header
        $token = str_replace('Bearer ', '', $authHeader);
        
        // Validar el token JWT
        require_once __DIR__ . '/../utils/JWTHandler.php';
        $decoded = JWTHandler::validateToken($token);
        
        if (!$decoded) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Token inválido o expirado'
            ]);
            exit;
        }
        
        // Validar suscripción de la empresa
        $empresa_id = $decoded->empresa_id ?? null;
        
        if (!$empresa_id) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Token no contiene información de empresa'
            ]);
            exit;
        }
        
        $subscriptionStatus = self::validateSubscription($db, $empresa_id);
        
        if (!$subscriptionStatus['valid']) {
            http_response_code($subscriptionStatus['code']);
            echo json_encode([
                'success' => false,
                'message' => $subscriptionStatus['message']
            ]);
            exit;
        }
        
        // Todo válido, retornar los datos del token
        return $decoded;
    }
}
?>
