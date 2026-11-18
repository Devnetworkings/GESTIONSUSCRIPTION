# Sistema de gestión GGG

Aplicación web en PHP y MySQL para administrar clientes de un gimnasio, membresías, pagos y operadores. La interfaz está en español y funciona en Laragon/Windows o en un servidor PHP con MySQL.

## Requisitos

- PHP con PDO y los controladores/extensiones `pdo_mysql`, `curl`, `dom` y `mbstring`.
- MySQL/MariaDB.
- Un esquema de base de datos con las tablas existentes `clientes`, `pagos` y `operator`.
- Para verificación y recuperación por correo: envío de correo saliente configurado en PHP y acceso al buzón registrado.

## Instalación

1. Haz una copia de seguridad de la base de datos antes de actualizar el sistema.
2. En producción, puedes definir `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` y `APP_ENV=production` en el panel del hosting. Si el hosting entrega la conexión en `includes/conexion.prod.php`, colócala allí en el paquete que subas y define `APP_ENV=production`; el archivo está excluido de Git para proteger las credenciales. En Laragon, los valores predeterminados son `127.0.0.1`, base `ggg`, usuario `root` y contraseña vacía.
3. En una base que aún no tenga estas mejoras, ejecuta **una sola vez y en este orden**:
   - `database/migrations/database_migration.sql`
   - `database/migrations/database_migration_validaciones.sql`
   - `database/migrations/database_migration_operadores_activos.sql`
   - `database/migrations/database_migration_bcv.sql`
   - `database/migrations/database_migration_cedula_separada.sql`
4. La migración BCV conserva los nombres y duración de planes históricos en cada pago, reemplaza el catálogo anterior y crea el plan inicial **Básico** a 15 USD. Haz una copia de seguridad antes de ejecutarla; esta operación no se debe repetir.
5. La migración `database_migration_cedula_separada.sql` también se ejecuta una sola vez. Separa los prefijos reconocidos; las cédulas anteriores que solo contienen números quedan sin tipo para no inventar ese dato.
6. Si ya ejecutaste estas migraciones en una instalación, no vuelvas a aplicarlas allí. Asegúrate de tener al menos un operador activo con rol de administrador antes de habilitar el acceso.
7. El sistema calcula `APP_BASE_URL` con la URL pública de la petición. Puedes configurarla con la variable de entorno `APP_BASE_URL` (sin barra final).
8. Abre la aplicación, inicia sesión y revisa **Configuración**.

> Las migraciones amplían un esquema existente; no crean desde cero las tablas base `clientes`, `pagos` y `operator`. Verifica que esas tablas y sus columnas requeridas estén presentes antes de instalarlas en otro entorno.

## Funcionalidades principales

- Directorio de clientes con búsqueda, filtros, paginación y expediente individual.
- Cédulas de clientes almacenadas con tipo y número separados; se muestran unidas fuera del formulario de edición.
- Estados de membresía calculados usando la fecha de vencimiento y el plazo de aviso configurado.
- Gestión administrativa de varios planes mensuales, con nombres y precios definidos en USD.
- Registro de pagos en USD o en VES convertido automáticamente usando la tasa USD publicada por el BCV.
- Tasa BCV, Fecha Valor y precio base en USD guardados en cada nuevo pago en bolívares.
- Renovación automática de la fecha de vencimiento al guardar un pago.
- Gestión administrativa de planes, operadores, roles, cuentas activas y plazo de aviso.
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
