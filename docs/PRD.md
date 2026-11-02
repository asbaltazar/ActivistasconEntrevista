# PRD — Plataforma "Red Ciudadana de Talentos"

> Versión 1.2 · Noviembre 2026 · Documento fuente para Antigravity IDE

**Decisiones confirmadas:** alcance **nacional** (32 entidades) · dominio **activistas.solytecmx.com** · **sin rol Coordinador** · avisos a Talentos por **SMS vía Twilio** · **verificación OTP solo del teléfono del Activista** (no del Talento) · volumen ≈ **2,500 Talentos** · hosting **Hostinger Premium** con **MariaDB 10.5**.

---

## 1. Resumen

Plataforma para que **Activistas** registren en campo, con su celular y **también sin conexión**, una red ciudadana de **Talentos** (simpatizantes/ciudadanos). Los datos se capturan desde la credencial INE (con OCR) más una selfie y un teléfono, se sincronizan a un backend central y se administran desde un **Panel de Control** con metas, mapas, reportes, exportaciones y CRUD.

| Componente | Tecnología |
|---|---|
| App Activista (PWA móvil, offline-first) | Vue 3 + Vite + Pinia + vite-plugin-pwa (Workbox) + Dexie.js (IndexedDB) |
| Panel de Control (web) | Vue 3 + Vite + Pinia + Vue Router |
| UI / CSS | **PrimeVue 4** (tablas, formularios, diálogos) + **Tailwind CSS 4** para layout |
| Backend / API REST | PHP 8.2+ · **CodeIgniter 4.5+** · CodeIgniter Shield (auth/tokens) |
| Base de datos | **MariaDB 10.5** (InnoDB, utf8mb4_unicode_ci; coordenadas en `lat`/`lng DECIMAL(10,7)`) |
| Mapas / Geocodificación | Google Maps Platform (Maps JS, Geocoding, Places Autocomplete) |
| OCR de INE | Google Cloud Vision API (server-side) + parser propio de campos INE |
| SMS / OTP | **Twilio** (Programmable Messaging + Twilio Verify) — SDK `twilio/sdk` |
| Hosting | Hostinger **Premium** (PHP 8.2+, MariaDB 10.5, SSL, cron, SSH) |

---

## 2. Objetivos y métricas

- Registrar un Talento completo en **< 90 segundos** en campo.
- **0 % pérdida de registros** capturados offline.
- ≥ 85 % de campos INE autocompletados correctamente por OCR.
- ≥ 95 % de domicilios geocodificados con precisión ROOFTOP/RANGE_INTERPOLATED.
- Monitoreo en tiempo casi real (≤ 5 min) del avance de metas por Activista.

---

## 3. Roles y permisos

| Rol | Alcance |
|---|---|
| **Super Admin** | Todo: usuarios admin, configuración, auditoría, borrado definitivo. |
| **Capturista/Consulta** (opcional) | Solo lectura y exportación limitada. |
| **Activista** | Solo App móvil. Crea y edita **sus** Talentos; ve su avance de meta. |

Jerarquía: Super Admin → Activista → Talentos (n). No hay rol Coordinador.

---

## 4. Requisitos funcionales

### 4.1 App Activista (PWA)

**RF-A1 Inicio de sesión**: usuario + contraseña entregados por el panel; cambio obligatorio en el primer acceso. Token de larga duración (30 días) + PIN local de 4–6 dígitos para desbloquear la app offline.

**RF-A2 Tablero personal**: meta (n), registrados, pendientes de sincronizar, rechazados/observados, barra de progreso.

**RF-A3 Alta de Talento (asistente por pasos)**:
1. **Aviso de privacidad y consentimiento** del Talento (checkbox + firma en pantalla opcional). *Obligatorio por LFPDPPP.*
2. **Foto frente INE**: cámara trasera con guía de encuadre, detección de nitidez, recorte y compresión (WebP/JPEG ≤ 400 KB, 1600 px).
3. **(Opcional) Reverso INE**: permite leer el código MRZ/QR → mayor exactitud de clave de elector y CIC.
4. **Selfie**: cámara frontal, ≤ 200 KB.
5. **Datos extraídos (editables)**: nombre, apellido paterno, apellido materno, domicilio (calle y número, colonia), código postal, estado, municipio, localidad, sección, vigencia, clave de elector, *CURP, fecha de nacimiento, sexo, año de registro* (extra, recomendados).
6. **Teléfono celular** (10 dígitos, validación MX) + opcional correo, WhatsApp sí/no.
7. **Ubicación**: geocodificación del domicilio + mapa con marcador **arrastrable** para corrección; se guarda además la **GPS del dispositivo** al momento de captura (evidencia de campo).
8. **Revisión y guardar** → se guarda en IndexedDB y se encola para sincronización.

**RF-A4 OCR**:
- Online: imagen → `POST /api/v1/ocr/ine` → Cloud Vision → parser (regex por etiquetas "NOMBRE", "DOMICILIO", "CLAVE DE ELECTOR", "CURP", "SECCIÓN", "VIGENCIA") → JSON con nivel de confianza por campo (campos de baja confianza se resaltan).
- Offline: se permite captura manual; el OCR se ejecuta al sincronizar y el Activista/Coordinador revisa diferencias. *(Opcional fase 2: Tesseract.js on-device.)*
- Validaciones: clave de elector 18 caracteres `^[A-Z]{6}\d{8}[HM]\d{3}$`; CURP 18; sección 1–4 dígitos; vigencia ≥ año actual (alerta si vencida); CP 5 dígitos.

**RF-A5 Offline-first y sincronización**:
- App shell precacheada (Service Worker). Catálogos (estados, municipios, CPs de su zona) descargados al iniciar sesión.
- Cada registro tiene `uuid` generado en el cliente (UUID v7) → **idempotencia**: reenvíos no duplican.
- Cola en IndexedDB con estados: `borrador → pendiente → enviando → sincronizado | error`.
- Sincronización automática al detectar conexión (evento `online` + Background Sync donde exista) y botón manual "Sincronizar ahora".
- Envío por registro: datos JSON + imágenes multipart; reintentos con backoff exponencial.
- Advertencia antes de cerrar sesión si hay pendientes; nunca borrar locales hasta confirmación `201/200` del servidor.
- Solicitar `navigator.storage.persist()` para evitar desalojo de IndexedDB.
- Datos locales cifrados (AES-GCM vía WebCrypto, llave derivada del PIN).

**RF-A6 Mis Talentos**: lista, búsqueda, edición (si no está bloqueado por validación), mapa de mis Talentos.

**RF-A7 Detección de duplicados**: aviso si la clave de elector/CURP/teléfono ya existe (local y servidor). El servidor rechaza duplicado por clave de elector con `409`.

### 4.2 Panel de Control

**RF-P1 Gestión de Activistas (CRUD)**: datos personales, teléfono, estado/municipio de operación (informativo), **meta n**, fecha límite, estado (activo/suspendido). Generación de usuario y contraseña temporal; envío por SMS (Twilio)/WhatsApp/copia; restablecer contraseña; revocar sesiones/dispositivos.

**RF-P1.1 Verificación del teléfono del Activista (OTP)**:
- Al dar de alta (o cambiar el teléfono de) un Activista, el panel envía un código de 6 dígitos vía **Twilio Verify** al celular capturado.
- El administrador captura el código que el Activista le dicta (alta presencial) **o** el Activista lo ingresa en su primer inicio de sesión en la app.
- Hasta verificarse, el Activista queda en estado `pendiente_verificacion` y **no puede sincronizar Talentos**.
- Código válido 10 min, máx. 5 intentos, reenvío cada 60 s; se guarda `telefono_verificado_at`.
- El teléfono del Activista es único y sirve también para recuperar contraseña por SMS.
- **El teléfono del Talento NO se verifica** (solo validación de formato a 10 dígitos).

**RF-P2 Gestión de Talentos (CRUD)**: tabla con filtros (activista, estado, municipio, localidad, sección, CP, fecha, estatus de validación, vigencia), vista detalle con selfie e INE, edición, validación/observación, eliminación lógica, fusión de duplicados.

**RF-P3 Monitoreo de metas**: dashboard con KPIs (total, hoy, semana, % meta global), ranking de activistas, avance vs. tiempo, semáforo (verde ≥ ritmo esperado, amarillo, rojo), activistas sin sincronizar > 48 h.

**RF-P4 Mapas**: Google Maps con **MarkerClusterer**, mapa de calor, filtros, colores por activista; *opcional*: capa de polígonos de secciones electorales (cartografía INE en GeoJSON) para ver cobertura por sección.

**RF-P5 Reportes**: por Activista, por estado/municipio/localidad, por sección, por CP, por fecha, cobertura de secciones, vigencias vencidas, duplicados. Gráficas (Chart.js / vue-chartjs).

**RF-P6 Exportación**: Excel (PhpSpreadsheet), CSV, PDF (Dompdf), KML/GeoJSON para mapas. Exportaciones grandes en segundo plano (cron) con enlace de descarga temporal. Toda exportación queda registrada en auditoría.

**RF-P7 Importación** (opcional): carga masiva de activistas por Excel.

**RF-P8 Catálogos**: estados, municipios, localidades, secciones, códigos postales (SEPOMEX).

**RF-P9 Auditoría**: bitácora de inicio de sesión, CRUD, exportaciones, vistas de imágenes INE.

**RF-P11 Avisos SMS a Talentos**:
- SMS automático de bienvenida/confirmación al registrarse (incluye enlace corto al aviso de privacidad y opción de baja).
- Envío masivo segmentado desde el panel (por estado, municipio, sección, activista) con plantillas y variables (`{nombre}`, `{seccion}`).
- Registro de cada envío (estado de entrega vía *status callback* de Twilio), lista de exclusión (STOP/BAJA, Twilio Advanced Opt-Out) y límites de envío por día.
- El consentimiento del Talento debe incluir explícitamente la aceptación de recibir SMS.

**RF-P10 Configuración**: textos de aviso de privacidad, parámetros de meta, llaves de API (en `.env`, no en BD).

---

## 5. Requisitos no funcionales

- **Seguridad**: HTTPS obligatorio; contraseñas Argon2id/bcrypt; tokens Bearer (Shield); rate limiting (Throttler de CI4); CORS restringido a los dominios propios; validación server-side de todo; protección CSRF en el panel si usa sesión.
- **Imágenes**: almacenadas **fuera de `public_html`** (`writable/uploads/...`) y servidas por endpoint autenticado o URL firmada temporal. Nunca públicas.
- **Cifrado en reposo** de campos sensibles (clave de elector, CURP, domicilio) con `Encryption` de CI4 + columna hash (SHA-256 con pepper) para búsquedas/unicidad.
- **Rendimiento**: listados paginados server-side; índices por sección, municipio, activista; < 500 ms por consulta típica con 500 k registros.
- **Disponibilidad**: respaldos diarios de BD e imágenes (Hostinger + copia externa semanal).
- **Compatibilidad**: Android 9+ Chrome, iOS 16.4+ Safari (PWA instalable).
- **Accesibilidad / usabilidad**: botones grandes, alto contraste para uso en exterior, español MX.

---

## 6. Cumplimiento legal (IMPORTANTE)

- **LFPDPPP** (Ley Federal de Protección de Datos Personales en Posesión de los Particulares, 2025): los datos de la INE y la selfie (dato biométrico) son datos personales sensibles → **aviso de privacidad integral**, **consentimiento expreso** registrado (fecha, hora, versión del aviso, activista, GPS), mecanismo de derechos **ARCO** y revocación.
- **No usar la INE para fines distintos** al aviso; no transferir a terceros sin consentimiento.
- Revisar la normatividad electoral (LGIPE / lineamientos INE) sobre uso de datos del padrón y periodos de campaña; validar con área jurídica.
- Política de retención y destrucción de datos al finalizar el proyecto.

---

## 7. Modelo de datos (MariaDB 10.5)

> Notas MariaDB 10.5: no existe tipo `UUID` nativo (→ `CHAR(36)`), `JSON` es alias de `LONGTEXT` con `CHECK (JSON_VALID(...))`, no hay `SRID` en columnas espaciales → se usan `lat`/`lng DECIMAL(10,7)` con índice compuesto (suficiente para ~2,500 registros; distancias con Haversine en SQL/PHP). Las migraciones de CI4 no deben usar funciones exclusivas de MySQL 8 (CTE recursivas sí están soportadas; `ST_Distance_Sphere` no).

```sql
users            (id, username, email, password_hash, role ENUM('superadmin','consulta','activista'),
                  status, must_change_password, last_login_at, created_at, updated_at, deleted_at)
activists        (id, user_id FK, nombre, ap_paterno, ap_materno, telefono CHAR(10) UNIQUE, telefono_verificado_at DATETIME NULL,
                  estado_id, municipio_id, meta INT, fecha_limite DATE,
                  status ENUM('pendiente_verificacion','activo','suspendido'), created_at, updated_at, deleted_at)
talents          (id BIGINT, uuid CHAR(36) UNIQUE, activist_id FK,
                  nombre, ap_paterno, ap_materno,
                  calle_numero, colonia, codigo_postal CHAR(5), estado_id, municipio_id, localidad_id, localidad_texto,
                  seccion SMALLINT, vigencia SMALLINT, clave_elector_enc, clave_elector_hash CHAR(64) UNIQUE,
                  curp_enc, curp_hash, fecha_nacimiento, sexo, telefono CHAR(10),
                  lat DECIMAL(10,7) NOT NULL, lng DECIMAL(10,7) NOT NULL, geocode_precision, geocode_source ENUM('google','manual','gps'),
                  captura_lat DECIMAL(10,7) NULL, captura_lng DECIMAL(10,7) NULL, captura_at DATETIME,
                  ocr_confianza JSON, consentimiento_at, aviso_version,
                  validacion ENUM('pendiente','validado','observado','rechazado'), observaciones,
                  created_at, updated_at, deleted_at,
                  INDEX(lat, lng), INDEX(seccion), INDEX(municipio_id), INDEX(activist_id))
talent_files     (id, talent_id FK, tipo ENUM('selfie','ine_frente','ine_reverso','firma'), path, mime, size, sha256)
devices          (id, activist_id, device_uuid, user_agent, last_sync_at, revoked_at)
sync_log         (id, activist_id, device_id, talent_uuid, status, error, created_at)
sms_messages     (id, talent_id FK NULL, telefono, plantilla, cuerpo, campaign_id, provider_id, status, error, sent_at, delivered_at)
sms_campaigns    (id, user_id, nombre, filtros JSON, total, enviados, status, created_at)
sms_optouts      (telefono PK, created_at)
cat_estados / cat_municipios / cat_localidades / cat_secciones / cat_cp
audit_log        (id, user_id, action, entity, entity_id, payload JSON, ip, created_at)
exports          (id, user_id, filtros JSON, formato, path, status, expires_at)
```

---

## 8. API REST (CodeIgniter 4) — `/api/v1`

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/auth/login` · `/auth/logout` · `/auth/password` | Autenticación |
| GET | `/me` · `/me/progress` | Perfil y avance del activista |
| GET | `/catalogs?scope=zona` | Catálogos para offline |
| POST | `/ocr/ine` | OCR de credencial |
| POST | `/geocode` | Geocodificación proxy (cache en BD) |
| POST | `/sync/talents` | Alta/actualización idempotente por `uuid` (lote) |
| POST | `/talents/{uuid}/files` | Subida de imágenes |
| GET/POST/PUT/DELETE | `/admin/activists[/{id}]` | CRUD activistas |
| POST | `/admin/activists/{id}/reset-password` | Credenciales |
| GET/PUT/DELETE | `/admin/talents[/{id}]` | CRUD talentos |
| GET | `/admin/files/{id}` | Imagen autenticada / URL firmada |
| GET | `/admin/dashboard` · `/admin/reports/{tipo}` | KPIs y reportes |
| GET | `/admin/map/points?bbox=&filters=` | Puntos para mapa (por bounding box) |
| POST | `/admin/exports` · GET `/admin/exports/{id}` | Exportaciones |
| GET | `/admin/audit` | Bitácora |
| POST | `/admin/sms/campaigns` · GET `/admin/sms/campaigns[/{id}]` | Campañas SMS |
| POST | `/admin/activists/{id}/verify-phone` · `/admin/activists/{id}/verify-phone/check` | OTP del Activista (Twilio Verify) |
| POST | `/auth/verify-phone` · `/auth/verify-phone/check` | OTP en primer inicio de sesión del Activista |
| POST | `/webhooks/twilio/status` · `/webhooks/twilio/inbound` | Estado de entrega / respuestas STOP-BAJA (validar firma `X-Twilio-Signature`) |

Respuestas JSON estándar `{ data, meta, errors }`; códigos 200/201/409/422/401/403.

---

## 9. Google Maps Platform

- APIs a habilitar en el proyecto **cobranza46138**: Maps JavaScript API, Geocoding API, Places API (Autocomplete), Cloud Vision API.
- > ⚠️ **La llave compartida en el chat debe considerarse expuesta.** Recomendación: **rotarla** y crear **dos llaves**:
  - **Llave navegador** (Maps JS / Places): restringida por *HTTP referrer* a los dominios de la app y el panel.
  - **Llave servidor** (Geocoding / Vision): restringida por *IP* del servidor Hostinger, guardada solo en `.env`.
- Nunca versionar llaves en Git (`.env` en `.gitignore`).
- Configurar **cuotas y alertas de facturación** en Google Cloud.
- Cache de geocodificación en BD para reducir costos; respetar términos de uso de Google.
- Geocodificación con `components=country:MX|postal_code:XXXXX` para mejorar precisión.

### 9.1 SMS con Twilio

- **Programmable Messaging** para confirmaciones y campañas; **Messaging Service** con número/remitente para México (+52).
- **Twilio Verify** para el OTP del Activista (gestiona códigos, expiración y antifraude).
- Variables en `.env`: `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN` (o API Key/Secret), `TWILIO_MESSAGING_SERVICE_SID`, `TWILIO_VERIFY_SERVICE_SID`.
- Webhooks validados con `X-Twilio-Signature`.
- Interfaz `SmsProviderInterface` + `TwilioSmsProvider` (permite cambiar de proveedor a futuro).
- Campañas procesadas por cron en lotes (p. ej. 50/min); mensajes ≤ 160 caracteres GSM-7 (evitar acentos/emojis para no pasar a UCS-2 y duplicar costo).
- Presupuesto orientativo: ~2,500 confirmaciones + campañas; configurar alertas de uso en la consola de Twilio.

---

## 10. Arquitectura y despliegue en Hostinger

```
domains/activistas.solytecmx.com/
 ├── public_html/
 │    ├── index.html, assets/ → PWA Activista   → https://activistas.solytecmx.com/
 │    ├── panel/              → Panel de Control → https://activistas.solytecmx.com/panel/
 │    └── api/                → /public de CI4  → https://activistas.solytecmx.com/api/v1/...
 └── ci4/                     → app, writable (uploads), vendor, .env (fuera de public_html)
```

- **Un solo dominio** con rutas `/`, `/panel/` y `/api/` → sin problemas de CORS; Service Worker de la PWA con `scope: /` excluyendo `/panel` y `/api`.
- Builds de Vite con `base: '/'` (app) y `base: '/panel/'` (panel); `.htaccess` con fallback a `index.html` para Vue Router en modo history.
- **Hostinger Premium** es suficiente para ~2,500 talentos (~2,500 × ~600 KB de imágenes ≈ 1.5 GB). Verificar en hPanel: PHP 8.2+, SSH habilitado y cron. BD: **MariaDB 10.5** (ver §7).
- Sin workers persistentes: tareas en segundo plano (exportaciones, SMS masivos, OCR/geocode diferido) se ejecutan con **cron cada minuto** vía `php spark`.
- SSL Let's Encrypt (Hostinger). PHP 8.2+, extensiones `intl`, `mbstring`, `gd/imagick`, `curl`, `mysqlnd`.
- **Cron** (hPanel): cola de exportaciones, OCR diferido, geocodificación pendiente, limpieza de archivos temporales, respaldos.
- Despliegue: GitHub Actions → build Vue + `composer install --no-dev` → SFTP/SSH (rsync) a Hostinger → `php spark migrate`.

---

## 11. Herramientas de desarrollo

| Área | Herramienta |
|---|---|
| Local | XAMPP (PHP 8.2+, **MariaDB 10.5** — igualar versión de producción) o Docker `mariadb:10.5`; Node 20 LTS; Composer 2 |
| Backend | CodeIgniter 4, CI Shield, PhpSpreadsheet, Dompdf, google/cloud-vision, ramsey/uuid, twilio/sdk |
| Calidad PHP | PHPUnit, PHPStan (nivel 6), PHP-CS-Fixer (PSR-12) |
| Frontend | Vue 3, Vite, Pinia, Vue Router, PrimeVue 4, Tailwind 4, Axios, Dexie.js, vite-plugin-pwa, @googlemaps/js-api-loader, @googlemaps/markerclusterer, vue-chartjs, browser-image-compression, VeeValidate + Zod |
| Calidad JS | ESLint, Prettier, Vitest, Playwright (E2E incl. modo offline) |
| API | OpenAPI 3 (`docs/openapi.yaml`), Postman/Bruno |
| Repositorio | Git + GitHub, ramas `main`/`develop`, Conventional Commits |
| Monitoreo | Logs CI4, Sentry (opcional), UptimeRobot |

Estructura de repositorio (monorepo):
```
/backend      CodeIgniter 4
/app-activista Vue PWA
/panel        Vue panel
/docs         PRD.md, openapi.yaml, aviso-privacidad.md
/.agents      AGENTS.md (reglas para Antigravity)
```

---

## 12. Fases / Roadmap

| Fase | Entregables | Duración est. |
|---|---|---|
| 0 | Repo, entornos, BD, migraciones, auth, catálogos | 1 sem |
| 1 | Panel: CRUD activistas, credenciales, metas | 1.5 sem |
| 2 | PWA: captura, cámara, offline, sincronización | 2.5 sem |
| 3 | OCR INE + geocodificación + mapas | 1.5 sem |
| 4 | Dashboard, reportes, exportaciones, auditoría | 1.5 sem |
| 5 | Seguridad, pruebas de campo, despliegue Hostinger | 1 sem |

---

## 13. Criterios de aceptación clave

- Capturar 20 talentos en modo avión, reactivar red → los 20 aparecen en el panel sin duplicados.
- OCR rellena ≥ 8 de 11 campos en una INE legible; todos editables.
- Clave de elector duplicada es rechazada con mensaje claro.
- Mapa del panel muestra los ~2,500 puntos nacionales con clustering fluido.
- Un Activista no puede sincronizar hasta verificar su celular con el código de Twilio Verify.
- Un Talento registrado recibe SMS de confirmación; una respuesta STOP/BAJA lo excluye de envíos futuros.
- Exportación Excel por sección coincide con el conteo del reporte.
- Imágenes INE no accesibles sin autenticación.

---

## 14. Detalles que faltaban (incorporados)

1. Aviso de privacidad, consentimiento y derechos ARCO (LFPDPPP).
2. Avisos **SMS vía Twilio** (confirmación, campañas segmentadas, lista de baja) y **OTP del Activista**.
3. Detección de duplicados (clave de elector / CURP / teléfono).
4. Reverso de INE opcional, CURP, fecha de nacimiento, sexo.
5. GPS del dispositivo como evidencia + marcador ajustable.
6. Cifrado de datos sensibles y almacenamiento privado de imágenes.
7. PIN offline, cifrado local, persistencia de almacenamiento.
8. Gestión de dispositivos y revocación de sesiones.
9. Auditoría y bitácora de exportaciones.
10. Rotación y restricción de llaves de Google; alertas de facturación.
11. Catálogos SEPOMEX / secciones electorales y capa de polígonos.
12. Respaldos, cron y pipeline de despliegue.

## 15. Preguntas abiertas

- Ninguna bloqueante. Pendiente: alta de cuenta Twilio con remitente para México y rotación de la llave de Google Maps.
