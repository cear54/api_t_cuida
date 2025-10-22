<?php

class FirebaseAPIv1 {
    private $projectId;
    private $clientEmail;
    private $privateKey;
    private $privateKeyId;
    private $clientId;
    
    public function __construct() {
        // Cargar variables de entorno desde .env
        $this->loadEnv();
        
        $this->projectId = $_ENV['FIREBASE_PROJECT_ID'] ?? 'estancias-55817';
        $this->clientEmail = $_ENV['FIREBASE_CLIENT_EMAIL'] ?? null;
        $this->privateKey = $_ENV['FIREBASE_PRIVATE_KEY'] ?? null;
        $this->privateKeyId = $_ENV['FIREBASE_PRIVATE_KEY_ID'] ?? null;
        $this->clientId = $_ENV['FIREBASE_CLIENT_ID'] ?? null;

        // Si se proporciona el JSON completo en base64, decodificar y extraer valores
        $serviceAccountBase64 = $_ENV['FIREBASE_SERVICE_ACCOUNT_BASE64'] ?? null;
        if ($serviceAccountBase64) {
            $decoded = base64_decode($serviceAccountBase64);
            $json = json_decode($decoded, true);
            if (is_array($json)) {
                $this->projectId = $json['project_id'] ?? $this->projectId;
                $this->clientEmail = $json['client_email'] ?? $this->clientEmail;
                $this->privateKey = $json['private_key'] ?? $this->privateKey;
                $this->privateKeyId = $json['private_key_id'] ?? $this->privateKeyId;
                $this->clientId = $json['client_id'] ?? $this->clientId;
            }
        }
        
        if (!$this->clientEmail || !$this->privateKey) {
            throw new Exception("Variables de entorno de Firebase no configuradas correctamente. Use FIREBASE_SERVICE_ACCOUNT_BASE64 o las variables individuales en .env");
        }
    }
    
    /**
     * Cargar variables de entorno desde archivo .env
     */
    private function loadEnv() {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
                    [$name, $value] = explode('=', $line, 2);
                    $name = trim($name);
                    // Remover comillas alrededor y preservar saltos de línea escaneados como \n
                    $value = trim($value);
                    if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                        $value = substr($value, 1, -1);
                    }
                    // Reemplazar secuencias \n por saltos reales en claves privadas
                    $value = str_replace('\\n', "\n", $value);
                    $_ENV[$name] = $value;
                }
            }
        }
    }
    
    /**
     * Obtener access token de OAuth2 para Firebase API v1
     */
    private function getAccessToken() {
        // Crear JWT para solicitar access token usando variables de entorno
        $now = time();
        $jwt = [
            'iss' => $this->clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600
        ];
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
        $payload = json_encode($jwt);
        
        $headerEncoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $payloadEncoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        
        $signature = '';
        $success = openssl_sign(
            $headerEncoded . '.' . $payloadEncoded,
            $signature,
            $this->privateKey,
            OPENSSL_ALGO_SHA256
        );
        
        if (!$success) {
            throw new Exception("Error al firmar JWT");
        }
        
        $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        $jwtToken = $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
        
        // Solicitar access token
        $response = $this->makeHttpRequest('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwtToken
        ], 'POST', false);
        
        if (isset($response['access_token'])) {
            return $response['access_token'];
        }
        
        throw new Exception("Error al obtener access token: " . json_encode($response));
    }
    
    /**
     * Enviar notificación usando Firebase API v1
     */
    public function sendNotification($token, $title, $body, $data = [], $icon = null) {
        $accessToken = $this->getAccessToken();
        
        // Enviar todo como data para que Flutter tenga control total
        // Esto evita notificaciones duplicadas
        $dataPayload = array_merge($data, [
            'title' => $title,
            'body' => $body,
            'notification_type' => 'admin_message',
            'show_notification' => 'true'
        ]);
        
        if ($icon) {
            $dataPayload['icon'] = $icon;
        }
        
        $message = [
            'message' => [
                'token' => $token,
                'data' => $dataPayload
            ]
        ];
        
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error cURL: " . $error);
        }
        
        curl_close($ch);
        
        $response = json_decode($result, true);
        
        if ($httpCode === 200) {
            return $response;
        } else {
            throw new Exception("Error Firebase API v1 (HTTP $httpCode): " . $result);
        }
    }
    
    /**
     * Enviar notificación a múltiples tokens
     */
    public function sendMulticast($tokens, $title, $body, $data = [], $icon = null) {
        if (empty($tokens)) {
            throw new Exception("No se proporcionaron tokens para envío");
        }
        
        $results = [];
        $errors = [];
        
        foreach ($tokens as $token) {
            try {
                $result = $this->sendNotification($token, $title, $body, $data, $icon);
                $results[] = [
                    'token' => $token,
                    'success' => true,
                    'result' => $result
                ];
            } catch (Exception $e) {
                $errors[] = [
                    'token' => $token,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'success_count' => count($results),
            'failure_count' => count($errors),
            'results' => $results,
            'errors' => $errors
        ];
    }
    
    /**
     * Realizar petición HTTP
     */
    private function makeHttpRequest($url, $data, $method = 'GET') {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // Si $asForm = false se enviará JSON, sino form-urlencoded
        $asForm = true;
        if (func_num_args() >= 4) {
            $asForm = func_get_arg(3);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($asForm) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/x-www-form-urlencoded'
                ]);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json'
                ]);
            }
        }

        $result = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error HTTP: " . $error);
        }
        
        curl_close($ch);
        
        $decoded = json_decode($result, true);
        if ($decoded === null) {
            // Return raw result if JSON decoding failed
            return $result;
        }
        return $decoded;
    }
}

?>