# Guía de uso y operación

## Acceso y roles

Inicia sesión con el usuario y contraseña del operador. El primer operador registrado cuando la tabla de operadores está vacía recibe el rol de administrador; después, solo los administradores pueden registrar operadores o administrar la configuración. Si la base ya contiene operadores, un responsable debe comprobar que exista al menos un administrador activo antes de habilitar el acceso.

Los operadores trabajan con clientes y pagos. Solo los administradores ven **Configuración**, donde pueden gestionar planes, cuentas y reportes financieros. Desactivar una cuenta impide que inicie sesión, pero conserva su información y la atribución histórica de sus pagos. No se puede desactivar la propia cuenta ni dejar el sistema sin un administrador activo.

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

Al pagar en efectivo divisa, se cobra el precio del plan en USD. Pago móvil y transferencia se cobran en VES, calculando `precio del plan en USD × tasa BCV USD` y redondeando el total a dos decimales. El precio se obtiene del servidor y no se acepta como autoridad un monto enviado por el navegador. Cada pago renueva la membresía por un mes.

Para registrar un pago:

1. Abre **Pagos** y selecciona el cliente.
2. Selecciona el plan y el método de pago. El formulario presenta el precio en USD o su equivalente en VES según corresponda.
3. Ingresa la referencia del comprobante. Para efectivo en divisa, el sistema genera una referencia si se deja vacía.
4. Confirma el pago. Si se guarda correctamente, se renueva el vencimiento por un mes y el movimiento aparece en el historial.

Las referencias no se pueden repetir. Si la cédula no corresponde a un cliente registrado, registra primero el cliente; el formulario puede continuar con el pago pendiente.

## Tasa de cambio del BCV

La aplicación consulta `https://www.bcv.org.ve/` desde el servidor, lee el valor USD publicado y su **Fecha Valor**. La consulta se intenta como máximo cada 15 minutos; la tasa y la hora de consulta se guardan en `app_settings`. Se requiere que PHP tenga activas las extensiones `curl`, `dom` y `mbstring`, además de conexión HTTPS saliente al BCV.

- Si el BCV no responde, se permite reutilizar una tasa oficial guardada únicamente si la consulta exitosa ocurrió en la fecha actual de Venezuela (`America/Caracas`).
- Una tasa oficial guardada de un día anterior no se usa automáticamente para nuevos pagos en VES.
- Si no hay una tasa oficial válida del día, Configuración permite al administrador establecer una tasa manual. La interfaz identifica esa tasa como manual; una consulta oficial exitosa posterior la reemplaza.
- Sin tasa oficial válida del día o tasa manual, se bloquean nuevos pagos en VES. Los pagos en USD siguen disponibles.
- Cada nuevo pago guarda el precio del plan en USD, el monto efectivamente cobrado y, cuando fue en VES, la tasa y Fecha Valor utilizadas. El expediente y el historial financiero muestran esos datos.

Los pagos anteriores a la migración se conservan con su moneda y monto originales. No se inventa ni recalcula una tasa retroactiva para pagos históricos en VES.

## Configuración administrativa

Desde **Configuración**, el administrador puede:

- Añadir planes indicando nombre y precio en USD; editar esos datos, quitarlos de la oferta (desactivarlos) y reactivarlos. Todos los planes duran un mes.
- Consultar el precio convertido a bolívares con la tasa válida del BCV al momento de registrar el pago. Los pagos en divisas conservan el precio USD.
- Consultar el estado de la tasa BCV o fijar una tasa manual cuando no haya una tasa oficial vigente del día.
- Definir el aviso de vencimiento entre 0 y 90 días.
- Crear operadores, cambiar roles y activar o desactivar cuentas.
- Actualizar el correo de un operador y enviar un enlace de verificación.
- Consultar los ingresos para un rango de fechas.

Para que la verificación y recuperación de correo funcionen, PHP debe poder enviar correo y `APP_BASE_URL` debe apuntar al lugar desde el que se abrirá el enlace. La aplicación solo considera verificado un correo cuando se completa el flujo mediante el enlace recibido.

## Consultar y exportar ingresos

En **Configuración → Resumen de ingresos**, selecciona las fechas **Desde** y **Hasta**. **Consultar** muestra los totales agrupados por moneda, método de pago y operador. Los importes de monedas diferentes se presentan por separado; no se convierten ni se suman entre monedas.

**Exportar Excel** descarga un archivo `.xls` compatible con Excel para el mismo período, con estas hojas:

| Hoja | Contenido |
| --- | --- |
| Resumen | Cantidad e ingresos agrupados por moneda, método de pago y operador. |
| Pagos | Detalle de cada pago: cliente, cédula, referencia, plan, monto, moneda, método y operador. |
| Totales diarios | Cantidad e ingresos agrupados por fecha y moneda. |

El reporte financiero y la descarga requieren una sesión activa de administrador. La hoja de pagos exportados incluye el monto cobrado, precio del plan en USD, tasa y Fecha Valor BCV cuando existan. Los pagos históricos se conservan en su moneda y monto originales, sin convertirlos retroactivamente.

## Apariencia y preferencias

El control del encabezado alterna entre tema claro y oscuro. La selección se guarda en el almacenamiento local del navegador por nombre de operador, por lo que no se comparte automáticamente entre dispositivos ni perfiles de navegador. La barra superior del dashboard usa azul marino en ambos temas.

## Mantenimiento

- Haz una copia de seguridad antes de ejecutar migraciones o cambios en el servidor.
- No vuelvas a aplicar migraciones ya ejecutadas: no todas las operaciones de alteración de columnas son repetibles.
- Antes de ejecutar `database_migration_bcv.sql`, exporta una copia de seguridad: la migración reemplaza el catálogo anterior por el plan inicial Básico, conserva snapshots de los planes asociados a pagos existentes y no debe repetirse.
- No guardes contraseñas de base de datos o de correo en el repositorio.
- Si los datos no cargan, revisa la conexión MySQL configurada en `conexion.php` y los registros de errores de PHP. Si el resumen de tarjetas falla, la interfaz muestra un aviso y la causa técnica queda en el registro de PHP.
