# Configuración de Deploy - API T-Cuida
# Servidor de Producción

## 🌐 Servidor Remoto

### Credenciales SSH
```bash
ssh -p 65002 u413241405@srv1268.hstgr.io
```

- **Usuario SSH**: `u413241405`
- **Host**: `srv1268.hstgr.io`
- **Puerto**: `65002`
- **Path API**: `~/public_html/api_t_cuida`

### Base de Datos (MariaDB)
- **Host**: `localhost`
- **Database**: `u413241405_estancias`
- **Usuario**: `u413241405_estancias`
- **Password**: `Mrcear01061968`

### URLs de Producción
- **API Base**: `https://estancias.cear54.com/api_t_cuida/api`
- **Health Check**: `https://estancias.cear54.com/api_t_cuida/api/health.php`
- **Dominio**: `estancias.cear54.com`

---

## 🚀 Deploy Completo

### 1. Deploy Full (Código + .env)

```powershell
cd c:\xampp\htdocs\api_t_cuida
.\deploy.ps1
```

**Hace**:
1. ✅ Verifica conexión SSH
2. ✅ Crea backup del deploy anterior
3. ✅ Crea tar.gz excluyendo (.git, .env, logs, *.md)
4. ✅ Sube vía SCP al servidor
5. ✅ Extrae en el servidor
6. ✅ Configura permisos (755 dirs, 644 archivos)
7. ✅ Sube .env adaptado para producción
8. ✅ Verifica endpoint health.php
9. ✅ Muestra información del servidor

### 2. Deploy Solo .env

```powershell
.\deploy.ps1 -OnlyEnv
```

Útil cuando solo cambian variables de entorno.

### 3. Quick Deploy (Archivos Específicos)

```powershell
.\quick-deploy.ps1 -Files "api/login.php","api/health.php"
```

Útil para hotfixes rápidos de endpoints específicos.

### 4. Deploy Sin Backup

```powershell
.\deploy.ps1 -SkipBackup
```

Más rápido, pero sin backup previo.

---

## 📋 Checklist Pre-Deploy

Antes de hacer deploy, verificar:

- [ ] Código probado localmente
- [ ] Variables de .env correctas
- [ ] Migración SQL lista (si aplica)
- [ ] Sin errores en logs locales
- [ ] Health endpoint respondiendo: `http://localhost/api_t_cuida/api/health.php`

---

## 🔧 Comandos Útiles SSH

### Conectar al Servidor

```bash
ssh -p 65002 u413241405@srv1268.hstgr.io
```

### Ver Archivos en el Servidor

```bash
ssh -p 65002 u413241405@srv1268.hstgr.io "ls -lh ~/public_html/api_t_cuida/api"
```

### Ver Logs de Error

```bash
ssh -p 65002 u413241405@srv1268.hstgr.io "tail -n 50 ~/public_html/api_t_cuida/error.log"
```

### Ejecutar Migración SQL

```bash
ssh -p 65002 u413241405@srv1268.hstgr.io \
  "mysql -h localhost -u u413241405_estancias -p'Mrcear01061968' u413241405_estancias \
   < ~/public_html/api_t_cuida/database/migration_XXX.sql"
```

### Consultar Base de Datos

```bash
ssh -p 65002 u413241405@srv1268.hstgr.io \
  "mysql -h localhost -u u413241405_estancias -p'Mrcear01061968' u413241405_estancias \
   -e 'SHOW TABLES;'"
```

### Ver Espacio en Disco

```bash
ssh -p 65002 u413241405@srv1268.hstgr.io "df -h ~/public_html/api_t_cuida"
```

### Cambiar Permisos (si es necesario)

```bash
ssh -p 65002 u413241405@srv1268.hstgr.io \
  "cd ~/public_html/api_t_cuida && chmod -R 755 . && find . -type f -name '*.php' -exec chmod 644 {} \;"
```

---

## 🐛 Troubleshooting

### Error: "Permission denied"

```bash
# Verificar permisos
ssh -p 65002 u413241405@srv1268.hstgr.io "ls -la ~/public_html/api_t_cuida"

# Corregir permisos
ssh -p 65002 u413241405@srv1268.hstgr.io \
  "cd ~/public_html/api_t_cuida && chmod -R 755 ."
```

### Error: "Database connection failed"

1. Verificar credenciales en .env remoto:
```bash
ssh -p 65002 u413241405@srv1268.hstgr.io "cat ~/public_html/api_t_cuida/.env | grep DB_"
```

2. Probar conexión a DB:
```bash
ssh -p 65002 u413241405@srv1268.hstgr.io \
  "mysql -h localhost -u u413241405_estancias -p'Mrcear01061968' -e 'SELECT 1;'"
```

### Health Endpoint No Responde

1. Verificar que el archivo existe:
```bash
ssh -p 65002 u413241405@srv1268.hstgr.io "ls -lh ~/public_html/api_t_cuida/api/health.php"
```

2. Ver logs:
```bash
ssh -p 65002 u413241405@srv1268.hstgr.io "tail -n 100 ~/php_error.log"
```

3. Probar localmente primero:
```powershell
curl http://localhost/api_t_cuida/api/health.php
```

### Error: "tar: command not found" en Windows

El script necesita `tar` de Git Bash. Asegúrate de tener instalado **Git for Windows**.

Alternativa si no tienes Git:
```powershell
# Usar compresión nativa de PowerShell (más lento)
Compress-Archive -Path $TEMP_DIR\* -DestinationPath "$env:TEMP\$ARCHIVE_NAME"
```

---

## 📊 Verificación Post-Deploy

### 1. Health Check

```bash
curl https://estancias.cear54.com/api_t_cuida/api/health.php
```

**Respuesta esperada**:
```json
{
  "success": true,
  "status": "healthy",
  "database_status": "connected",
  "api_version": "1.0"
}
```

### 2. Test Login Endpoint

```powershell
$body = @{
    email = "test@test.com"
    password = "test123"
} | ConvertTo-Json

Invoke-RestMethod -Uri "https://estancias.cear54.com/api_t_cuida/api/login.php" `
    -Method Post `
    -Body $body `
    -ContentType "application/json"
```

### 3. Verificar Archivo Específico

```bash
ssh -p 65002 u413241405@srv1268.hstgr.io \
  "head -n 20 ~/public_html/api_t_cuida/api/login.php"
```

---

## 🔒 Seguridad

### Variables de Entorno (.env)

El script automáticamente adapta `.env` para producción:

```env
# Cambia automáticamente:
APP_ENV=development    → APP_ENV=production
APP_DEBUG=true        → APP_DEBUG=false
CORS_ORIGIN=*         → CORS_ORIGIN=https://estancias.cear54.com
```

### Archivos NO Desplegados

Por seguridad, estos archivos **no se suben**:

- `.git/` - Control de versiones
- `.env` - Variables locales (se adapta para producción)
- `*.log` - Logs locales
- `*.md` - Documentación
- `firebase-service-account.json` - Credenciales locales
- `deploy.ps1` - Scripts de deploy

### Firebase Service Account

Si usas Firebase, configúralo en el `.env` con las variables `FIREBASE_*`.

---

## 🎯 Checklist Post-Deploy

- [ ] Health endpoint responde OK
- [ ] Database status: "connected"
- [ ] Login funciona correctamente
- [ ] Endpoints críticos probados
- [ ] App Flutter conecta sin errores
- [ ] No hay errores en logs remotos

---

## 📚 Recursos

- [Documentación API](README.md)
- [Mejoras de Red](API_IMPROVEMENTS.md)
- [Health Endpoint](api/health.php)
- [Convenciones del Proyecto](../../../memories/conventions.md)

---

## 💡 Tips

### Deploy Rápido en Desarrollo

Durante desarrollo activo, usar quick-deploy para archivos individuales:

```powershell
# Solo actualizar login.php
.\quick-deploy.ps1 -Files "api/login.php"

# Actualizar varios archivos
.\quick-deploy.ps1 -Files "api/login.php","api/health.php","includes/functions.php"
```

### Rollback Rápido

Si algo sale mal:

```bash
# Listar backups
ssh -p 65002 u413241405@srv1268.hstgr.io "ls -lh ~/public_html/backups/"

# Restaurar backup
ssh -p 65002 u413241405@srv1268.hstgr.io \
  "cd ~/public_html/api_t_cuida && \
   tar -xzf ../backups/backup_FECHA.tar.gz"
```

### Monitorear Logs en Tiempo Real

```bash
ssh -p 65002 u413241405@srv1268.hstgr.io "tail -f ~/public_html/api_t_cuida/error.log"
```

---

**¡Importante!**: Siempre probar el health endpoint después del deploy para asegurar que todo funciona correctamente.
