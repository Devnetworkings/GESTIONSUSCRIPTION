# Guía de uso y operación

## Instalación nueva y configuración

1. Crea una base MySQL/MariaDB vacía en Laragon/phpMyAdmin o en el panel del hosting.
2. Importa `database/schema.sql` en esa base.
3. Configura las credenciales mediante variables del hosting, `.env` basado en `.env.example` o `includes/conexion.prod.php`.
4. Ejecuta `composer install --no-dev --optimize-autoloader` localmente y sube `vendor/` junto con la aplicación.
5. Mantén el sitio privado, abre el acceso y registra al primer operador; esa cuenta recibe el rol de administrador.
6. Si usarás recuperación de contraseña o verificación de correo, configura SMTP y envía el enlace de verificación al administrador.

Para configurar la base manualmente sin `.env`, crea `includes/conexion.prod.php` con las credenciales que proporciona el hosting:

```php
<?php
$pdo = new PDO(
    'mysql:host=DB_HOST;port=3306;dbname=DB_NAME;charset=utf8mb4',
    'DB_USER',
    'DB_PASSWORD',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]
);
```

Reemplaza los valores de ejemplo por los datos del hosting. El archivo está excluido de Git; Apache bloquea su acceso directo mediante `includes/.htaccess`. En Nginx, bloquea el acceso web a `includes/`. No publiques ni compartas las credenciales. Si usas `.env`, el lector PHP integrado lo carga sin dependencias de Composer. En Nginx, bloquea también los archivos ocultos, por ejemplo `location ~ /\.(?!well-known).* { deny all; }`.

`.env` contempla:

| Variables | Uso |
| --- | --- |
| `APP_ENV`, `APP_BASE_URL` | Entorno y URL pública necesaria para enlaces de correo. |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Conexión MySQL/MariaDB. |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME` | Envío opcional de verificación y recuperación por SMTP. |

## Acceso y roles

Inicia sesión con el usuario y contraseña de tu cuenta. La primera persona que se registra crea la cuenta de administrador; no hay contraseña predeterminada compartida. Si la base ya contiene operadores, un responsable debe comprobar que exista al menos un administrador activo antes de habilitar el acceso.

**Operador** puede consultar y gestionar clientes y pagos. Cada cuenta activa puede actualizar su propio correo y teléfono en **Mi cuenta**. **Admin** tiene las facultades de operador y además administra operadores, planes, tasa, avisos y reportes financieros; puede corregir los demás datos de las cuentas. **Soporte de TI** puede consultar clientes y pagos en modo de solo lectura, ver el historial de auditoría y editar la configuración técnica cuando el servidor permite hacerlo. No puede crear ni modificar clientes o pagos ni administrar roles, estados, planes o reportes financieros.

En **Mi cuenta** también se muestran, solo para consulta, los nombres, apellidos, cédula, dirección, usuario, rol, estado y período de vacaciones. Si algún dato de solo lectura está incorrecto, comunícalo al administrador. La contraseña nunca se muestra: solicita desde esa pantalla un enlace de un solo uso enviado al correo verificado para definir una nueva. La recuperación por correo requiere que el correo esté verificado y que el servidor tenga SMTP configurado.

La interfaz oculta las opciones según el rol, pero esa visibilidad no es una barrera de seguridad: cada endpoint vuelve a validar la sesión activa y el rol en el servidor. Las solicitudes que modifican datos requieren además un token CSRF asociado a la sesión.

Para guardar cambios técnicos se exige la contraseña actual. Los secretos no se muestran; dejar sus campos vacíos conserva el valor y hay una opción explícita para quitarlo. La nueva conexión se prueba antes de guardarse y debe contener el esquema y la misma cuenta activa. Si la conexión se administra con variables del hosting o `includes/conexion.prod.php`, el panel informa que debes cambiarla desde ese entorno. Después de guardar `.env`, reinicia PHP/Apache para que todos los procesos apliquen los cambios.

Los estados de cuenta son **Activo** (verde), **Inactivo** (gris), **Vacaciones** (morado) y **Despedido** (rojo). Solo las cuentas activas pueden iniciar sesión. Al terminar el período de vacaciones, la cuenta se reactiva automáticamente en la siguiente petición al sistema. Los cambios conservan los registros históricos y no se puede dejar el sistema sin un administrador activo.

## Clientes y membresías

El directorio permite buscar por cédula, nombre, correo o teléfono, filtrar por estado y abrir el expediente del cliente. Desde el expediente se pueden revisar los datos, el vencimiento y el historial de pagos.

Los estados visibles se calculan de esta forma:

| Estado | Regla |
| --- | --- |
| Activo | El cliente está activo y su vencimiento queda después del plazo de aviso. |
| Por vencer | El cliente no está inactivo y vence desde hoy hasta el número de días de aviso configurado. |
| Vencido | El cliente no está inactivo y su vencimiento ya pasó. |
| Inactivo | Baja manual; no se cambia automáticamente por la fecha. |
| Deuda | Estado guardado para clientes con deuda. La tarjeta cuenta ese estado guardado; en el directorio, una fecha vencida puede mostrarse como «Vencido» por el cálculo de vencimiento. |

Las tarjetas del dashboard presentan los totales de activos, por vencer, vencidos, inactivos y con deuda. El resumen se actualiza al iniciar sesión y después de registrar pagos o modificar información que afecta los estados.

## Planes y pagos

El catálogo inicial trae el plan **Básico**, pero el administrador puede añadir los planes que necesite, cambiar su nombre y precio, y quitar o reactivar planes. Todos los planes duran siempre un mes y sus precios se administran en USD; no se fija un precio en bolívares.

Al pagar en efectivo divisa, se cobra el precio del plan en USD. Pago móvil y transferencia se cobran en VES, calculando `precio del plan en USD × tasa BCV USD` y redondeando el total a dos decimales. Cuando hay una tasa BCV vigente disponible, también se guarda en pagos en USD junto con su Fecha Valor, sin que su indisponibilidad impida registrar pagos en divisas. El precio se obtiene del servidor y no se acepta como autoridad un monto enviado por el navegador. Cada pago renueva la membresía por un mes.

Para registrar un pago:

1. Abre **Pagos** y selecciona el cliente.
2. Selecciona el plan y el método de pago. El formulario presenta el precio en USD o su equivalente en VES según corresponda.
3. Ingresa la referencia del comprobante. Para efectivo en divisa, el sistema genera una referencia si se deja vacía.
4. Confirma el pago. Si se guarda correctamente, se renueva el vencimiento por un mes y el movimiento aparece en el historial.

Al seleccionar un cliente existente, el formulario completa el tipo y el número de documento guardados. La fecha efectiva se inicia con la fecha actual de Venezuela y se puede cambiar si el pago se registra después de haber sido realizado.
La fecha efectiva indica cuándo se realizó el pago. Solo los administradores ven también la fecha y hora en que se guardó en el sistema (hora de Venezuela) y el operador que lo registró.

Las referencias no se pueden repetir. Si la cédula no corresponde a un cliente registrado, registra primero el cliente; el formulario puede continuar con el pago pendiente.

Los formularios de clientes y pagos desactivan las sugerencias guardadas por el navegador para evitar reutilizar datos o referencias anteriores. El selector de clientes en el formulario de pago conserva las sugerencias propias del sistema para elegir un cliente existente.

## Tasa de cambio del BCV

La aplicación consulta `https://www.bcv.org.ve/` desde el servidor, lee el valor USD publicado y su **Fecha Valor**. La consulta se intenta como máximo cada 15 minutos; la tasa y la hora de consulta se guardan en `app_settings`. Se requiere que PHP tenga activas las extensiones `curl`, `dom` y `mbstring`, además de conexión HTTPS saliente al BCV.

- Si el BCV no responde, se permite reutilizar una tasa oficial guardada únicamente si la consulta exitosa ocurrió en la fecha actual de Venezuela (`America/Caracas`).
- Una tasa oficial guardada de un día anterior no se usa automáticamente para nuevos pagos en VES.
- Si no hay una tasa oficial válida del día, Configuración permite al administrador establecer una tasa manual. La interfaz identifica esa tasa como manual; una consulta oficial exitosa posterior la reemplaza.
- Sin tasa oficial válida del día o tasa manual, se bloquean nuevos pagos en VES. Los pagos en USD siguen disponibles.
- Cada nuevo pago guarda el precio del plan en USD, el monto efectivamente cobrado y, cuando fue en VES, la tasa y Fecha Valor utilizadas. El expediente y el historial financiero muestran esos datos.
- Si un pago histórico no tiene una tasa BCV guardada, el historial puede mostrar la tasa vigente de hoy como referencial; esto no modifica el registro ni presenta esa tasa como la histórica.

Los pagos se conservan con su moneda y monto originales. No se inventa ni recalcula una tasa retroactiva para pagos históricos en VES.

## Configuración administrativa

Desde **Configuración**, el administrador puede:

- Añadir planes indicando nombre y precio en USD; editar esos datos, quitarlos de la oferta (desactivarlos) y reactivarlos. Todos los planes duran un mes.
- Consultar el precio convertido a bolívares con la tasa válida del BCV al momento de registrar el pago. Los pagos en divisas conservan el precio USD.
- Consultar el estado de la tasa BCV o fijar una tasa manual cuando no haya una tasa oficial vigente del día.
- Definir por separado el aviso de vencimiento entre 0 y 90 días.
- Crear operadores y asignarles los roles operador, administrador o Soporte de TI.
- Editar los datos de cualquier operador, excepto su contraseña.
- Cambiar los estados de operadores y definir las fechas de vacaciones.
- Actualizar el correo de un operador y enviar un enlace de verificación.
- Consultar los ingresos para un rango de fechas.

El historial de actividad registra inicios y cierres de sesión, cambios de cuentas, clientes, pagos y configuración. No guarda contraseñas, tokens ni secretos. Solo las cuentas con rol **Soporte de TI** pueden consultarlo.

La pestaña **Configuración técnica** solo se muestra a Soporte de TI cuando los ajustes pueden editarse desde el panel. Si la conexión se administra con `includes/conexion.prod.php` o con variables externas, el panel no muestra esa pestaña; esos valores se cambian desde el servidor. El endpoint vuelve a validar el rol y exige token CSRF y contraseña actual al guardar.

### Recuperar contraseña y verificar correo

En el inicio de sesión, selecciona **Olvidé mi contraseña**, escribe tu usuario y abre el enlace que llegue al correo verificado. El enlace de recuperación vence en una hora y solo se puede usar una vez. Si el correo todavía no está verificado, un administrador debe enviar el enlace desde **Configuración** y el operador debe confirmarlo primero.

Después de tres contraseñas incorrectas consecutivas, la cuenta queda bloqueada durante 15 minutos. Un inicio de sesión correcto reinicia el contador. Si necesitas entrar antes, solicita el cambio de contraseña con **Olvidé mi contraseña**; al establecer una nueva clave se elimina el bloqueo. El esquema actual crea las columnas necesarias.

El envío usa PHPMailer por SMTP. Composer instala la biblioteca con `composer install --no-dev --optimize-autoloader`; en servidores de producción, incluye `vendor/` o ejecuta Composer durante el despliegue. Una opción sin costo para comenzar es Gmail SMTP, sujeto a los límites de envío de Google:

| Variable de entorno | Ejemplo para Gmail |
| --- | --- |
| `SMTP_HOST` | `smtp.gmail.com` |
| `SMTP_PORT` | `587` |
| `SMTP_SECURE` | `tls` |
| `SMTP_USERNAME` | Dirección Gmail completa |
| `SMTP_PASSWORD` | Contraseña de aplicación de Google, no la contraseña normal |
| `SMTP_FROM_EMAIL` | La misma dirección Gmail |
| `SMTP_FROM_NAME` | `Sistema de gestión GGG` |
| `APP_BASE_URL` | URL pública de la aplicación, sin barra final |

Para obtener la contraseña de aplicación, activa la verificación en dos pasos de la cuenta Google y crea una contraseña de aplicación. Configura estos valores en `.env` o como variables de entorno disponibles para PHP/Apache o en el panel del hosting; reinicia el servidor si corresponde. `APP_BASE_URL` debe ser el origen público correcto, preferiblemente con HTTPS; no se infiere de la petición para evitar que enlaces de recuperación dependan del encabezado Host. No guardes contraseñas SMTP en el repositorio. Para otra cuenta de correo, solicita a su proveedor el host, puerto y modo TLS/SSL. Solo se permite `tls` o `ssl`.

## Consultar y exportar ingresos

En **Configuración → Resumen de ingresos**, selecciona las fechas **Desde** y **Hasta**. **Consultar** muestra únicamente los ingresos totales del período, separados entre VES y USD; si no hubo pagos en el lapso, muestra «Lapso de tiempo vacío». La información detallada de pagos, métodos y operadores está disponible solo en la exportación.

**Exportar pagos y balance (Excel)** descarga un libro `.xlsx` para el mismo período, con columnas dimensionadas para facilitar la lectura, filtros en las tablas y estas hojas:

| Hoja | Contenido |
| --- | --- |
| Pagos | Primera hoja al abrir el archivo: una fila por transacción con fecha y hora, datos del cliente (nombre, cédula, teléfono y correo), referencia, plan, monto, moneda, tasa BCV y operador. |
| Resumen | Cantidad e ingresos agrupados por moneda, método de pago y operador. |
| Totales diarios | Cantidad e ingresos agrupados por fecha y moneda. |
| Balance | Cantidad de pagos, total y promedio por moneda, gráficos de ingresos diarios separados por moneda y composición de pagos por cantidad. |

Los gráficos diarios aparecen apilados y con el mismo ancho para facilitar la comparación; se eliminan leyendas y valores repetidos, y las etiquetas de fecha se espacian según el período para evitar que se encimen.

El reporte financiero y la descarga requieren una sesión activa de administrador. El balance refleja ingresos brutos: no descuenta gastos, porque el sistema no registra egresos. Los totales de USD y VES se presentan por separado y no se suman entre sí. La hoja de pagos exportados incluye el monto cobrado, precio del plan en USD, tasa y Fecha Valor BCV cuando existan. Los pagos históricos se conservan en su moneda y monto originales, sin convertirlos retroactivamente.

## Apariencia y preferencias

El control del encabezado alterna entre tema claro y oscuro. La selección se guarda en el almacenamiento local del navegador por nombre de operador, por lo que no se comparte automáticamente entre dispositivos ni perfiles de navegador. La barra superior del dashboard usa azul marino en ambos temas.

## Mantenimiento

- El código y el esquema de base se despliegan por separado. Antes de subir cambios PHP/API, revisa si requieren tablas o columnas nuevas y comunica el SQL necesario; `database/schema.sql` instala bases vacías, pero no altera tablas existentes.
- En bases existentes que aún no tienen el bloqueo de acceso, ejecuta una sola vez:

```sql
ALTER TABLE `operator`
    ADD COLUMN `login_failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN `login_locked_until` DATETIME NULL;
```

- Antes de reinstalar o reemplazar la base, exporta una copia de seguridad; importar `database/schema.sql` crea el esquema, pero no conserva los datos existentes.
- `database/schema.sql` es el único esquema mantenido para instalaciones nuevas. No se incluyen migraciones para actualizar bases de versiones anteriores.
- No guardes contraseñas de base de datos o de correo en el repositorio.
- Si los datos no cargan, revisa la conexión MySQL configurada en `includes/conexion.php` y los registros de errores de PHP. Si el resumen de tarjetas falla, la interfaz muestra un aviso y la causa técnica queda en el registro de PHP.
