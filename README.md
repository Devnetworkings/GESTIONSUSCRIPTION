# Sistema de gestión GGG

Aplicación web en PHP y MySQL para administrar clientes de un gimnasio, membresías, pagos y operadores. La interfaz está en español y funciona en Laragon/Windows o en un servidor PHP con MySQL.

## Requisitos

- PHP con PDO y los controladores/extensiones `pdo_mysql`, `curl`, `dom` y `mbstring`.
- Composer para instalar PHPMailer y phpdotenv.
- MySQL/MariaDB.
- Para verificación y recuperación por correo: una cuenta SMTP y acceso al buzón registrado (opcional para instalar).

## Instalación nueva

1. Crea una base de datos vacía desde Laragon/phpMyAdmin o el panel del hosting. El instalador crea las tablas, pero no crea el servidor ni la base de datos.
2. Ejecuta `composer install --no-dev --optimize-autoloader`.
3. Abre la aplicación en el navegador. Si no existe una instalación configurada, el sistema te redirige automáticamente a `/setup/` sin requerir credenciales. El asistente prueba la conexión, solicita la URL pública y los datos básicos del administrador, crea el esquema actual y guarda las credenciales en `.env`.
4. Al finalizar, el asistente elimina cualquier marcador temporal y bloquea la configuración inicial en la base. Reduce los permisos de escritura del directorio del proyecto después de instalar.
5. Configura el SMTP en `.env` si vas a usar verificación y recuperación por correo. Las instrucciones para una opción gratuita con Gmail están en la [guía de uso](docs/GUIA_DE_USO.md).
6. Inicia sesión y completa desde el sistema cualquier dato operativo pendiente.

El asistente requiere una base vacía y se bloquea si detecta `.env`, `includes/conexion.prod.php` o tablas de la aplicación en la base configurada. No modifica instalaciones existentes. Protege `.env` con las reglas del `.htaccess` incluido. En Nginx, configura también el bloqueo de archivos ocultos, por ejemplo `location ~ /\.(?!well-known).* { deny all; }`.

### Instalación manual

Si no quieres usar el asistente, puedes copiar `.env.example` a `.env` y editarlo, definir las variables directamente en el servidor o usar `includes/conexion.prod.php` en producción. Para una base nueva, importa `database/schema.sql` y registra el primer operador desde el acceso mientras el sitio aún no sea público; ese primer operador recibe el rol de administrador.

`.env` no se sube a Git. Las variables del servidor conservan prioridad sobre los valores del archivo. `includes/conexion.prod.php`, cuando se usa en producción, conserva prioridad sobre ambos.

## Actualización de instalaciones existentes

Haz una copia de seguridad antes de actualizar. Las migraciones modifican el esquema o los datos de una base que ya tiene las tablas `clientes`, `pagos` y `operator`; **no** son parte de la instalación nueva y varias no se pueden repetir. Si esa base aún no tiene estas mejoras, ejecuta una sola vez y en este orden:

- `database/migrations/database_migration.sql`
- `database/migrations/database_migration_validaciones.sql`
- `database/migrations/database_migration_operadores_activos.sql`
- `database/migrations/database_migration_bcv.sql`
- `database/migrations/database_migration_cedula_separada.sql`
- `database/migrations/database_migration_operadores_auditoria.sql`
- `database/migrations/database_migration_recuperacion_contrasena.sql`
- `database/migrations/database_migration_bloqueo_acceso.sql`

La migración BCV reemplaza el catálogo de planes, conserva snapshots de pagos históricos y crea el plan inicial **Básico** a 15 USD; no la repitas. La migración de cédula separa el prefijo del documento. Si ya ejecutaste las migraciones anteriores, aplica solo una migración nueva cuando la guía de esa versión lo indique.

Las migraciones amplían un esquema existente; no crean desde cero las tablas base. Para empezar de cero usa el asistente o importa `database/schema.sql`, no ejecutes la secuencia de actualización.

## Funcionalidades principales

- Directorio de clientes con búsqueda, filtros, paginación y expediente individual.
- Cédulas de clientes almacenadas con tipo y número separados; se muestran unidas fuera del formulario de edición.
- Estados de membresía calculados usando la fecha de vencimiento y el plazo de aviso configurado.
- Gestión administrativa de varios planes mensuales, con nombres y precios definidos en USD.
- Registro de pagos en USD o en VES convertido automáticamente usando la tasa USD publicada por el BCV.
- Tasa BCV, Fecha Valor y precio base en USD guardados en cada nuevo pago en bolívares.
- Renovación automática de la fecha de vencimiento al guardar un pago.
- Gestión administrativa de planes, operadores, roles y estados (activo, inactivo, vacaciones y despedido); la configuración técnica de DB/SMTP está disponible solo para Soporte de TI cuando el servidor permite editarla.
- Actualización personal de correo/teléfono; Soporte de TI consulta clientes y pagos sin modificarlos y puede acceder al historial de auditoría.
- Registro de actividad de inicio de sesión, cambios de cuentas, clientes, pagos y configuración, sin guardar contraseñas ni tokens.
- Resumen de ingresos consultable por período y exportable a un libro Excel `.xlsx` con columnas ajustadas, resumen, cada pago y sus datos de cliente/operador, totales diarios y balance bruto por moneda con gráficos.
- Preferencia de tema claro/oscuro guardada por operador en el navegador.

## Cambios frente a la versión anterior

- Los planes ahora se administran desde la aplicación con nombre y precio en USD; conservan duración mensual fija, en lugar de depender de un catálogo estático en una sola moneda.
- Los pagos en bolívares usan la tasa USD/VES publicada por el BCV, y guardan en el pago la tasa, la Fecha Valor y el precio base en USD para conservar el cálculo histórico. También se puede fijar la tasa manualmente cuando la tasa oficial vigente no está disponible.
- El resumen financiero ahora se descarga como un libro `.xlsx` detallado: incluye cada pago con cliente y operador, totales diarios, un balance que mantiene las monedas separadas y gráficos legibles.
- La guía de uso y las instrucciones de instalación describen las migraciones, los permisos, el flujo BCV y la exportación actualizados.

## Documentación

Consulta la [guía de uso y operación](docs/GUIA_DE_USO.md) para conocer los estados de clientes, los flujos de pagos, la tasa BCV, la exportación de ingresos, los permisos y recomendaciones de operación.

## Estructura del proyecto

- `api/`: endpoints JSON y descargas usadas por la interfaz.
- `includes/`: conexión, clases y lógica PHP compartida.
- `pages/`: páginas públicas de verificación y recuperación de contraseña.
- `assets/`: hojas de estilo, JavaScript e imágenes.
- `database/migrations/`: migraciones SQL.
- `database/schema.sql`: esquema completo para una instalación nueva.
- `setup/`: asistente temporal para crear la configuración y el primer administrador.
