<?php
/**
 * Helper para manejo consistente de fechas y zona horaria
 * Creado para resolver inconsistencias de timezone en T-Cuida
 */

class TimezoneHelper {
    
    // Zona horaria estándar para la aplicación
    const TIMEZONE = 'America/Mexico_City';
    
    /**
     * Configurar zona horaria global
     */
    public static function setDefaultTimezone() {
        // Forzar configuración de timezone
        if (date_default_timezone_get() !== self::TIMEZONE) {
            date_default_timezone_set(self::TIMEZONE);
        }
    }
    
    /**
     * Obtener fecha actual en formato Y-m-d
     * @return string Fecha en formato YYYY-MM-DD
     */
    public static function getCurrentDate() {
        self::setDefaultTimezone();
        return date('Y-m-d');
    }
    
    /**
     * Obtener timestamp actual en formato Y-m-d H:i:s
     * @return string Timestamp en formato YYYY-MM-DD HH:MM:SS
     */
    public static function getCurrentTimestamp() {
        self::setDefaultTimezone();
        return date('Y-m-d H:i:s');
    }
    
    /**
     * Obtener hora actual en formato H:i:s
     * @return string Hora en formato HH:MM:SS
     */
    public static function getCurrentTime() {
        self::setDefaultTimezone();
        return date('H:i:s');
    }
    
    /**
     * Validar formato de fecha
     * @param string $date Fecha a validar
     * @return bool True si el formato es válido
     */
    public static function validateDateFormat($date) {
        $fechaValida = DateTime::createFromFormat('Y-m-d', $date);
        return $fechaValida && $fechaValida->format('Y-m-d') === $date;
    }
    
    /**
     * Validar formato de hora
     * @param string $time Hora a validar
     * @return bool True si el formato es válido
     */
    public static function validateTimeFormat($time) {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time);
    }
    
    /**
     * Convertir fecha de cliente a fecha del servidor (si es necesario)
     * @param string|null $clientDate Fecha del cliente
     * @return string Fecha validada o fecha actual del servidor
     */
    public static function getValidDate($clientDate = null) {
        if ($clientDate && self::validateDateFormat($clientDate)) {
            return $clientDate;
        }
        return self::getCurrentDate();
    }
    
    /**
     * Formatear fecha para mostrar al usuario
     * @param string $date Fecha en formato Y-m-d
     * @return string Fecha formateada para México
     */
    public static function formatDateForDisplay($date) {
        $dateTime = DateTime::createFromFormat('Y-m-d', $date);
        if ($dateTime) {
            return $dateTime->format('d/m/Y');
        }
        return $date;
    }
    
    /**
     * Formatear timestamp para mostrar al usuario
     * @param string $timestamp Timestamp en formato Y-m-d H:i:s
     * @return string Timestamp formateado para México
     */
    public static function formatTimestampForDisplay($timestamp) {
        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
        if ($dateTime) {
            return $dateTime->format('d/m/Y H:i');
        }
        return $timestamp;
    }
}
?>