# Implementación de Validación de Suscripciones

## Resumen de Cambios

Se ha implementado un sistema centralizado de validación de suscripciones que verifica que la empresa del usuario tenga una suscripción activa y vigente en cada petición al API.

## Archivos Creados

### 1. `middleware/subscription_validator.php`
Middleware que contiene la lógica de validación de suscripciones.

**Funciones principales:**
- `validateSubscription($db, $empresa_id)`: Valida el estado y fechas de la suscripción
- `validateAuthAndSubscription($db)`: Valida JWT + suscripción en una sola llamada

**Validaciones que realiza:**
1. Verifica que exista una suscripción para la empresa
2. Verifica que el estado sea válido (`'prueba'` o `'activa'`)
3. Rechaza estados: `'vencida'`, `'cancelada'`, `'suspendida'`
4. Si está en período de prueba (`en_periodo_prueba = 1`): verifica `fecha_fin_prueba`
5. Si NO está en período de prueba: verifica `fecha_fin`

### 2. `middleware/USAGE_EXAMPLES.php`
Documentación con ejemplos de uso del middleware.

## Archivos Modificados

### 1. `utils/JWTHandler.php`
Se actualizó el método `requireAuth()` para incluir validación de suscripción automáticamente.

**Beneficio:** Todos los endpoints que usan `JWTHandler::requireAuth()` ahora validan suscripción sin cambios adicionales.

### 2. `api/login.php`
Mantiene la validación de suscripción en el login (validación temprana).

### 3. `api/get_children.php` (ejemplo)
Se agregó validación de suscripción después de verificar el JWT.

### 4. `api/asistencia_entrada.php` (ejemplo)
Se agregó validación de suscripción después de verificar el JWT.

## Estrategia de Implementación

### Doble Validación (Opción A - IMPLEMENTADA)

1. **En el Login:** Valida antes de generar el token
   - Evita crear tokens para empresas sin suscripción válida

2. **En cada Endpoint:** Valida en cada petición
   - Protege contra suscripciones que expiran mientras hay sesión activa
   - Verifica cambios de estado en tiempo real

### Endpoints Protegidos Automáticamente

Los siguientes endpoints ya están protegidos sin necesidad de cambios (usan `requireAuth()`):

- `nino.php`
- `eventos.php`
- `tareas.php`
- `colegiaturas.php`
- `personal_salon.php`
- `tareas_familia.php`
- Y cualquier otro que use `JWTHandler::requireAuth()`

### Endpoints que Requieren Actualización Manual

Los siguientes endpoints tienen validación JWT manual y deben actualizarse:

- `asistencia_salida.php`
- `asistencia_estado.php`
- `asistencia_historial.php`
- `bitacora_comportamiento.php`
- `obtener_bitacora.php`
- `obtener_bitacora_imagenes.php`
- `salida_registro.php`
- `subir_imagen_bitacora.php`
- `subir_imagen_bitacora_url.php`
- `get_children_admin.php`
- `get_ninos_academico.php`
- `eliminar_imagen_bitacora.php`
- `reportes_actividades.php`

**Para actualizarlos:** Agregar después de la validación JWT:

```php
// Validar suscripción de la empresa
if ($empresa_id) {
    require_once '../middleware/subscription_validator.php';
    $subscriptionStatus = SubscriptionValidator::validateSubscription($db, $empresa_id);
    
    if (!$subscriptionStatus['valid']) {
        http_response_code($subscriptionStatus['code']);
        echo json_encode([
            'success' => false,
            'message' => $subscriptionStatus['message']
        ]);
        exit;
    }
}
```

## Mensajes de Error

- **403:** "No se encontró una suscripción válida para su empresa. Contacte al administrador."
- **403:** "Su suscripción está vencida/cancelada/suspendida. Contacte al administrador para reactivarla."
- **403:** "El período de prueba ha finalizado. Contacte al administrador para renovar su suscripción."
- **403:** "Su suscripción ha expirado. Contacte al administrador para renovar."

## Comportamiento en Flutter

Cuando la app reciba un error 403 con estos mensajes:
1. Debe cerrar la sesión local
2. Mostrar el mensaje al usuario
3. Redirigir al login

## Testing

Para probar la validación:

1. **Crear suscripción vencida:**
```sql
UPDATE suscripciones SET fecha_fin = '2025-01-01' WHERE empresa_id = 'emp_xxx';
```

2. **Cambiar estado:**
```sql
UPDATE suscripciones SET estado = 'vencida' WHERE empresa_id = 'emp_xxx';
```

3. **Intentar acceder:** El API debe responder con 403 y mensaje de error.

## Próximos Pasos

1. ✅ Implementar middleware de validación
2. ✅ Actualizar JWTHandler::requireAuth()
3. ✅ Mantener validación en login
4. ✅ Actualizar get_children.php y asistencia_entrada.php como ejemplos
5. ⏳ Actualizar endpoints restantes con validación JWT manual
6. ⏳ Implementar manejo de error 403 en Flutter
7. ⏳ Testing completo

## Notas Importantes

- La validación NO aplica a endpoints públicos (login, registro, etc.)
- La validación se hace en cada petición para detectar cambios en tiempo real
- Los administradores del sistema deben poder gestionar suscripciones independientemente
- Se recomienda implementar un dashboard de administración de suscripciones

---

**Fecha de implementación:** 18 de noviembre de 2025
**Versión del API:** Incluir en próximo commit
