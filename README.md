# Sistema de gestión GGG

Aplicación web en PHP y MySQL para administrar clientes de un gimnasio, membresías, pagos y operadores. La interfaz está en español y funciona en Laragon/Windows o en un servidor PHP con MySQL.

## Requisitos

- PHP con PDO y los controladores/extensiones `pdo_mysql`, `curl`, `dom` y `mbstring`.
- Composer para instalar PHPMailer.
- MySQL/MariaDB.
- Para verificación y recuperación por correo: una cuenta SMTP y acceso al buzón registrado (opcional para instalar).

## Instalación

1. Crea una base de datos y un usuario desde Laragon/phpMyAdmin o el panel del hosting.
2. Importa `database/schema.sql` en la base vacía.
3. Configura la conexión desde las variables del hosting, un archivo `.env` basado en `.env.example` o `includes/conexion.prod.php`. Consulta la [guía de uso](docs/GUIA_DE_USO.md) para la configuración manual.
4. Ejecuta `composer install --no-dev --optimize-autoloader` localmente y sube también `vendor/` al hosting.
5. Mantén el sitio privado mientras registras el primer operador; esa primera cuenta recibe el rol de administrador.
6. Configura SMTP si usarás verificación y recuperación por correo.

`.env` no se sube a Git. Las variables del servidor conservan prioridad sobre los valores del archivo. `includes/conexion.prod.php`, cuando se usa en producción, conserva prioridad sobre ambos.

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
- La guía de uso describe la instalación manual, los permisos, el flujo BCV y la exportación actualizados.

## Documentación

Consulta la [guía de uso y operación](docs/GUIA_DE_USO.md) para conocer los estados de clientes, los flujos de pagos, la tasa BCV, la exportación de ingresos, los permisos y recomendaciones de operación.

## Estructura del proyecto

- `api/`: endpoints JSON y descargas usadas por la interfaz.
- `includes/`: conexión, clases y lógica PHP compartida.
- `pages/`: páginas públicas de verificación y recuperación de contraseña.
- `assets/`: hojas de estilo, JavaScript e imágenes.
- `database/schema.sql`: esquema completo para una instalación nueva.
