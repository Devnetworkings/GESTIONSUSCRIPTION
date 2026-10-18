# Sistema de gestión GGG

Aplicación web en PHP y MySQL para administrar clientes de un gimnasio, membresías, pagos y operadores. La interfaz está en español y funciona en Laragon/Windows o en un servidor PHP con MySQL.

## Requisitos

- PHP con PDO y los controladores/extensiones `pdo_mysql`, `curl`, `dom` y `mbstring`.
- MySQL/MariaDB.
- Un esquema de base de datos con las tablas existentes `clientes`, `pagos` y `operator`.
- Para verificación y recuperación por correo: envío de correo saliente configurado en PHP y acceso al buzón registrado.

## Instalación

1. Haz una copia de seguridad de la base de datos antes de actualizar el sistema.
2. Configura host, nombre de base de datos, usuario y contraseña en `conexion.php`. No subas credenciales de producción al repositorio.
3. En una base que aún no tenga estas mejoras, ejecuta **una sola vez y en este orden**:
   - `database_migration.sql`
   - `database_migration_validaciones.sql`
   - `database_migration_operadores_activos.sql`
   - `database_migration_bcv.sql`
4. La migración BCV conserva los nombres y duración de planes históricos en cada pago, reemplaza el catálogo anterior y crea el plan inicial **Básico** a 15 USD. Haz una copia de seguridad antes de ejecutarla; esta operación no se debe repetir.
5. Si ya ejecutaste estas migraciones en una instalación, no vuelvas a aplicarlas allí. Asegúrate de tener al menos un operador activo con rol de administrador antes de habilitar el acceso.
6. Configura el envío de correo de PHP y la variable `APP_BASE_URL` con la URL base de la aplicación, sin barra final. Ejemplos: `http://localhost/GGG` o `https://tu-dominio/ruta`. No necesitas crear un buzón nuevo, pero sí tener acceso al correo remitente y configurar SMTP o un relay.
7. Abre la aplicación, inicia sesión y revisa **Configuración**.

> Las migraciones amplían un esquema existente; no crean desde cero las tablas base `clientes`, `pagos` y `operator`. Verifica que esas tablas y sus columnas requeridas estén presentes antes de instalarlas en otro entorno.

## Funcionalidades principales

- Directorio de clientes con búsqueda, filtros, paginación y expediente individual.
- Estados de membresía calculados usando la fecha de vencimiento y el plazo de aviso configurado.
- Gestión administrativa de varios planes mensuales, con nombres y precios definidos en USD.
- Registro de pagos en USD o en VES convertido automáticamente usando la tasa USD publicada por el BCV.
- Tasa BCV, Fecha Valor y precio base en USD guardados en cada nuevo pago en bolívares.
- Renovación automática de la fecha de vencimiento al guardar un pago.
- Gestión administrativa de planes, operadores, roles, cuentas activas y plazo de aviso.
- Resumen de ingresos consultable por período y exportable a un libro Excel `.xlsx` con columnas ajustadas, resumen, cada pago y sus datos de cliente/operador, totales diarios y balance bruto por moneda con gráficos.
- Preferencia de tema claro/oscuro guardada por operador en el navegador.

## Documentación

Consulta la [guía de uso y operación](docs/GUIA_DE_USO.md) para conocer los estados de clientes, los flujos de pagos, la tasa BCV, la exportación de ingresos, los permisos y recomendaciones de operación.
