<?php
/**
 * Cargador de Variables de Entorno
 * Carga y procesa variables desde archivo .env
 */
class EnvLoader {
    private static $loaded = false;
    private static $vars = [];

    /**
     * Cargar variables del archivo .env
     */
    public static function load($envFile = null) {
        if (self::$loaded) {
            return;
        }

        // Determinar ruta del archivo .env
        if ($envFile === null) {
            $envFile = dirname(__DIR__) . '/.env';
        }

        // Verificar si el archivo existe
        if (!file_exists($envFile)) {
            throw new Exception("Archivo .env no encontrado en: " . $envFile);
        }

        // Leer y procesar el archivo
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Saltar comentarios
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Procesar líneas con formato KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remover comillas si existen
                $value = trim($value, '"\'');

                // Guardar en array interno y $_ENV
                self::$vars[$key] = $value;
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    /**
     * Obtener variable de entorno
     */
    public static function get($key, $default = null) {
        self::load();
        
        // Buscar en diferentes fuentes
        if (isset(self::$vars[$key])) {
            return self::$vars[$key];
        }
        
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    /**
     * Verificar si una variable existe
     */
    public static function has($key) {
        return self::get($key) !== null;
    }

    /**
     * Obtener todas las variables cargadas
     */
    public static function all() {
        self::load();
        return self::$vars;
    }

    /**
     * Obtener variable como booleano
     */
    public static function getBool($key, $default = false) {
        $value = self::get($key, $default);
        
        if (is_bool($value)) {
            return $value;
        }
        
        return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
    }

    /**
     * Obtener variable como entero
     */
    public static function getInt($key, $default = 0) {
        return intval(self::get($key, $default));
    }
}

// Auto-cargar variables al incluir este archivo
try {
    EnvLoader::load();
} catch (Exception $e) {
    // En desarrollo, mostrar error
    if (EnvLoader::get('APP_DEBUG', false)) {
        error_log("Error cargando .env: " . $e->getMessage());
    }
}
?>
