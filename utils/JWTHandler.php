<?php
// Cargar variables de entorno
require_once __DIR__ . '/../config/env.php';

class JWTHandler {
    private static function getSecretKey() {
        return EnvLoader::get('JWT_SECRET_KEY', 't_cuida_default_secret_key_2025');
    }
    
    private static function getSecretIV() {
        return EnvLoader::get('JWT_SECRET_IV', 't_cuida_default_iv_2025');
    }
    
    private static function getEncryptMethod() {
        return EnvLoader::get('ENCRYPT_METHOD', 'AES-256-CBC');
    }
    
    private static function getExpireHours() {
        return EnvLoader::getInt('JWT_EXPIRE_HOURS', 24);
    }
    
    // Generar JWT Token
    public static function generateToken($user_data) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $expireHours = self::getExpireHours();
        $payload = json_encode([
            'user_id' => $user_data['id'],
            'usuario' => $user_data['usuario'],
            'tipo_usuario' => $user_data['tipo_usuario'],
            'nino_id' => $user_data['nino_id'] ?? null,
            'personal_id' => $user_data['personal_id'] ?? null,
            'empresa_id' => $user_data['empresa_id'] ?? null,
            'iat' => time(),
            'exp' => time() + ($expireHours * 60 * 60)
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::getSecretKey(), true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    // Verificar JWT Token
    public static function verifyToken($token) {
        try {
            $tokenParts = explode('.', $token);
            
            if (count($tokenParts) !== 3) {
                return false;
            }
            
            $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[0]));
            $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
            $signatureProvided = $tokenParts[2];
            
            // Verificar la estructura
            $headerDecoded = json_decode($header, true);
            $payloadDecoded = json_decode($payload, true);
            
            if (!$headerDecoded || !$payloadDecoded) {
                return false;
            }
            
            // Verificar expiración
            if (isset($payloadDecoded['exp']) && $payloadDecoded['exp'] < time()) {
                return false;
            }
            
            // Verificar la firma
            $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
            $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
            
            $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::getSecretKey(), true);
            $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
            
            if (!hash_equals($base64Signature, $signatureProvided)) {
                return false;
            }
            
            return $payloadDecoded;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Obtener token del header Authorization
    public static function getTokenFromHeader() {
        $headers = apache_request_headers();
        
        if (!$headers) {
            $headers = $_SERVER;
        }
        
        $authHeader = null;
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization' || $key === 'HTTP_AUTHORIZATION') {
                $authHeader = $value;
                break;
            }
        }
        
        if (!$authHeader) {
            return null;
        }
        
        // Extraer token del formato "Bearer token"
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    // Verificar autenticación requerida
    public static function requireAuth() {
        $token = self::getTokenFromHeader();
        
        if (!$token) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Token de autorización requerido'
            ]);
            exit();
        }
        
        $userData = self::verifyToken($token);
        
        if (!$userData) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Token inválido o expirado'
            ]);
            exit();
        }
        
        // Verificar que el usuario esté activo en la base de datos
        $userId = $userData['user_id'] ?? $userData['id'] ?? null;
        if ($userId) {
            try {
                require_once __DIR__ . '/../config/database.php';
                $database = new Database();
                $db = $database->getConnection();
                
                $stmt = $db->prepare("SELECT activo FROM usuarios_app WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user || $user['activo'] != 1) {
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Usuario inactivo o deshabilitado'
                    ]);
                    exit();
                }
            } catch (Exception $e) {
                // En caso de error de DB, permitir continuar para no romper la app
                // pero loggear el error
                error_log("Error verificando estado de usuario: " . $e->getMessage());
            }
        }
        
        return $userData;
    }
}
?>
