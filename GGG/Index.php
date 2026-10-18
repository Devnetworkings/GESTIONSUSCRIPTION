<?php

session_start();

$display_login = 'grid';
$display_dashboard = 'none';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Sistema de gestión</title>
    <link rel="stylesheet" href="app.css?v=20261018-monthlyplans2" />
</head>
<body>

  <div id="loginpage" style="display: <?php echo $display_login; ?>;">
        <section class="login-brand">
            <img class="gym-logo login-gym-logo" src="assets/gym-logo.jpg" alt="Logo del gimnasio">
            <p class="brand-caption">Sistema de gestión</p>
        </section>
        <div class="login-forms">
    <form id="formu" method="POST" onsubmit="procesarFormulario(event, 'auth.php', (res) => { 
    sessionStorage.setItem('actorVIP', res.operador);
    establecerTemaUsuario(res.operador);
    document.getElementById('dashboard-header-actions').appendChild(document.getElementById('theme-toggle'));
    document.getElementById('loginpage').style.display = 'none';
    document.getElementById('dashboard').style.display = 'block';
    document.getElementById('nombre-actor').innerText = res.operador.toUpperCase();
    const botonConfig = Array.from(document.querySelectorAll('.tabs-navigation button')).find(button => button.textContent.includes('Configuración'));
    if (botonConfig) botonConfig.style.display = res.role === 'admin' ? '' : 'none';
    cargarVista('clientes'); 
})">
    <div class="login-card-heading">
        <p class="login-eyebrow">ACCESO DE OPERADORES</p>
    <h3>Iniciar sesión</h3>
        <p class="login-subtitle">Ingresa con tu usuario y contraseña.</p>
    </div>
    <label class="login-field">Usuario
    <input type="text" placeholder="OPERADOR" name="operator" required>
    </label>
    <label class="login-field">Contraseña
    <input type="password" placeholder="PASSWORD" name="password" required>
    </label>
    <input type="submit" id="send" value="Iniciar sesión"/> 
    <div id="create-recovery"> 
            <input type="button" value="Registrar operador" onclick="toggleLogin('register')" />
            <input type="button" value="Olvidé mi contraseña" onclick="toggleLogin('recovery')" />
    </div>
</form>

            <form id="form-recovery" style="display: none;" onsubmit="solicitarRecuperacion(event)">
                <div class="login-card-heading">
                    <p class="login-eyebrow">ACCESO SEGURO</p>
                <h3>RECUPERAR CONTRASEÑA</h3>
                    <p class="login-subtitle">Te enviaremos instrucciones a tu correo verificado.</p>
                </div>
                <label class="login-field">Usuario
                <input type="text" name="username" placeholder="Usuario del operador" required>
                </label>
                <input type="submit" value="Enviar enlace">
                <button type="button" class="btn-volver" onclick="toggleLogin('login')">Volver</button>
            </form>

        <form id="form-register" style="display: none;" onsubmit="procesarFormulario(event, 'register.php', (res) => { 
            alert('Operador registrado con éxito.');
            toggleLogin('login'); 
            document.getElementById('form-register').reset(); 
        })">
            <div class="login-card-heading">
                <p class="login-eyebrow">GESTIÓN DE EQUIPO</p>
            <h3>NUEVO OPERADOR</h3>
                <p class="login-subtitle">Crea un acceso para un nuevo integrante.</p>
            </div>
            
            <div class="register-grid">
                <input type="text" name="nombres" placeholder="Nombres" required>
                <input type="text" name="apellidos" placeholder="Apellidos" required>
                
                <div style="display: flex; gap: 5px; grid-column: span 1; margin: 0;">
                    <select name="tipo_doc" style="width: 30%; border-radius: 8px; border: none; padding: 14px; background-color: #f8fafc; font-weight: 600; color: #334155; outline: none;">
                        <option value="V">V</option>
                        <option value="E">E</option>
                    </select>
                    <input type="text" inputmode="numeric" name="ci" placeholder="Cédula" pattern="[0-9]{6,9}" minlength="6" maxlength="9" required style="width: 70%;">
                </div>
                
                <input type="tel" name="tlf" placeholder="Teléfono, ej. 0412-1234567" pattern="04[0-9]{2}-?[0-9]{7}" maxlength="12" required>
                
                <input type="email" name="contacto" maxlength="254" placeholder="Correo electrónico" style="grid-column: span 2;" required>
                <input type="text" name="direccion" placeholder="Dirección de Habitación" style="grid-column: span 2;">

                <hr class="divider" style="grid-column: span 2;">

                <input type="text" name="username" placeholder="Usuario para el Sistema" required>
                <input type="password" name="pw" placeholder="Contraseña (10 a 72 caracteres)" minlength="10" maxlength="72" required>
            </div>

            <input type="submit" id="send-register" value="Registrar Operador">
            <button type="button" class="btn-volver" onclick="toggleLogin('login')">Volver a la Taquilla</button>
        </form>

        </div>
    </div>

    <div id="dashboard" style="display: <?php echo $display_dashboard; ?>;">
        <div id="header">
            <div class="dashboard-brand">
                <img class="gym-logo dashboard-gym-logo" src="assets/gym-logo.jpg" alt="Logo del gimnasio">
                <span class="dashboard-brand-name">Sistema de gestión</span>
            </div>
            <div class="header-actions" id="dashboard-header-actions">
                <span class="operator-label">OPERADOR</span>
                <span id="nombre-actor"></span>
                <button type="button" id="theme-toggle" class="theme-toggle" aria-label="Cambiar a modo oscuro" aria-pressed="false">
                    <span class="theme-toggle-icon" aria-hidden="true">☾</span>
                    <span class="theme-toggle-text">Modo oscuro</span>
                </button>
                <a href="#" onclick="cerrarSesionEscenario()" class="logout-link">Cerrar sesión</a>
            </div>
        </div>

        <section id="panel-resumen" class="dashboard-summary" aria-live="polite">
            <article><strong id="resumen-activos">—</strong><span> Activas</span></article>
            <article><strong id="resumen-por-vencer">—</strong><span> Por vencer</span></article>
            <article><strong id="resumen-vencidos">—</strong><span> Membresías vencidas</span></article>
            <article><strong id="resumen-inactivos">—</strong><span> Inactivas</span></article>
            <article><strong id="resumen-deuda">—</strong><span> Con deuda</span></article>
            <p id="resumen-error" class="summary-error" role="status" hidden></p>
        </section>
        
        <nav class="tabs-navigation">
            <button class="tab-btn" onclick="cargarVista('clientes')">Clientes</button>
            <button class="tab-btn" onclick="cargarVista('pagos')">Pagos</button>
            <button class="tab-btn" onclick="cargarVista('config')">Configuración</button>
        </nav>
        
        <div id="content">
    <div id="vista-clientes">
   <div class="clientes-header">
        <h2>Directorio de Clientes</h2>
        
        <!-- Controles del Director de Casting -->
        <div style="display: flex; gap: 10px; align-items: center;">
            <!-- Fíjate que aquí llamamos a tu función al soltar una tecla -->
            <input type="text" id="input-busqueda" placeholder="🔍 Buscar cédula, nombre, tlf..." 
                   onkeyup="cargarTablaClientes()" 
                   style="padding: 8px; border-radius: 5px; border: 1px solid #ccc; width: 250px;">
            
            <!-- Y aquí la llamamos al cambiar el filtro -->
            <select id="filtro-estatus" onchange="cargarTablaClientes()" 
                    style="padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                <option value="">Todos los estatus</option>
                <option value="Activo">Activos</option>
                <option value="Por vencer">Por vencer</option>
                <option value="Vencido">Vencidos</option>
                <option value="Inactivo">Inactivos</option>
                <option value="Deuda">Deuda</option>
            </select>

            <button class="btn-nuevo" onclick="toggleFormularioCliente()">+ Nuevo Cliente</button>
        </div>
    </div>
    
    <div class="clientes-layout">
        <div class="tabla-container">
            <table class="celtable" id="tabla-clientes">
                <thead>
                    <tr>
                        <th>Cédula</th>
                        <th>Nombres</th>
                        <th>Teléfono</th>
                        <th>Estatus</th>
                    </tr>
                </thead>
                <tbody>
                    </tbody>
            </table>
            <div id="paginacion-clientes" class="pagination-controls"></div>
        </div>

        <form id="form-cliente" class="modo-lateral" style="display:none;" onsubmit="procesarFormulario(event, 'registrar_cliente.php', (res) => { 
            alert('¡Cliente registrado!'); 
            cargarTablaClientes(); /* Recarga la tabla mágicamente */
            document.getElementById('form-cliente').reset();
        })">
            <h3>Registro Rápido</h3>
            
            <div class="input-group full-width">
                <input type="text" name="ci" placeholder="Cédula, ej. 30830476" pattern="[VEJGPvejgp]?-?[0-9]{6,9}" maxlength="11" title="6 a 9 dígitos, con prefijo opcional" required />
            </div>
            <div class="input-group-row">
                <input type="text" name="nombres" placeholder="Nombres" required />
                <input type="text" name="apellidos" placeholder="Apellidos" required />
            </div>
            <div class="input-group full-width">
                <input type="tel" name="tlf" placeholder="Teléfono, ej. 0412-1234567" pattern="04[0-9]{2}-?[0-9]{7}" maxlength="12" title="Formato 04xx-xxxxxxx" required />
            </div>
            <div class="input-group full-width">
                <input type="email" name="email" maxlength="254" placeholder="Correo Electrónico (opcional)" />
            </div>
            <div class="input-group full-width">
                <input type="text" name="direccion" placeholder="Dirección" />
            </div>
            <input type="submit" value="Guardar Cliente" class="full-width">
        </form>
    </div>
</div>
 

   <div id="vista-pagos" style="display: none; flex-direction: column; gap: 20px;">
  <div class="clientes-header">
        <h2>Historial de Transacciones</h2>
        
        <!-- Controles de Auditoría para Pagos -->
        <div style="display: flex; gap: 10px; align-items: center;">
            <!-- Llamamos a la función al soltar la tecla -->
            <input type="text" id="input-busqueda-pagos" placeholder="🔍 Buscar ref, cédula o nombre..." 
                   onkeyup="cargarTablaPagos()" 
                   style="padding: 8px; border-radius: 5px; border: 1px solid #ccc; width: 250px;">
            <input type="date" id="filtro-pago-desde" onchange="cargarTablaPagos()" aria-label="Desde">
            <input type="date" id="filtro-pago-hasta" onchange="cargarTablaPagos()" aria-label="Hasta">
            <select id="filtro-pago-metodo" onchange="cargarTablaPagos()">
                <option value="">Todos los métodos</option>
                <option value="pagomovil">Pago móvil</option>
                <option value="transferencia">Transferencia</option>
                <option value="divisa">Divisa</option>
            </select>
            <button class="btn-nuevo" id="btn-nuevo-pago" onclick="toggleFormularioPago()">+ Nuevo Pago</button>
        </div>
    </div>
    
    <div class="clientes-layout">
        
        <div class="tabla-container">
            <table class="celtable" id="tabla-pagos">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Referencia / Serie</th>
                        <th>Monto</th>
                        <th>Moneda</th>
                        <th>Tasa BCV</th>
                        <th>Método</th>
                        <th>Operador</th>
                    </tr>
                </thead>
                <tbody>
                    </tbody>
            </table>
            <div id="paginacion-pagos-lista" class="pagination-controls"></div>
        </div>

        <div id="addpay" class="modo-lateral" style="display:none;">
            <form id="payform" onsubmit="procesarFormulario(event, 'registrar_pago.php', (res) => { 
                alert('¡Transacción procesada con éxito!'); 
                cargarTablaPagos(); /* Recargamos la tabla al instante */
                document.getElementById('payform').reset();
                adaptarFormularioPago(''); /* Limpiamos el campo dinámico */
            })">
                <h3>Registrar Nuevo Pago</h3>
                
                <div class="input-group full-width" style="position: relative;">
    <input type="text" id="buscador-cliente-pago" placeholder="Escribe el nombre o cédula para buscar..." autocomplete="off" onkeyup="sugerirClientes(this.value)" required />
    
    <!-- Aquí aparecerán las sugerencias flotantes -->
    <div id="sugerencias-clientes" style="position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ccc; max-height: 150px; overflow-y: auto; z-index: 1000; display: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></div>
</div>
                
                <div class="input-group-row">
                    <select name="tipo_documento" required>
                        <option value="V">V</option>
                        <option value="E">E</option>
                        <option value="J">J</option>
                        <option value="G">G</option>
                        <option value="P">P</option>
                    </select>
                    <input type="text" inputmode="numeric" name="cedula_identidad" placeholder="Cédula (6 a 9 dígitos)" pattern="[0-9]{6,9}" minlength="6" maxlength="9" required />
                </div>

                <div class="input-group-row">
                    <select name="codigo_operadora" required>
                        <option value="" disabled selected>Código...</option>
                        <optgroup label="Líneas Móviles">
                            <option value="0412">0412</option>
                            <option value="0414">0414</option>
                            <option value="0424">0424</option>
                            <option value="0416">0416</option>
                            <option value="0426">0426</option>
                        </optgroup>
                    </select>
                    <input type="text" inputmode="numeric" name="telefono" placeholder="7 dígitos" pattern="[0-9]{7}" minlength="7" maxlength="7" required />
                </div>
                
                <div class="input-group">
                    <select name="metodo_pago" id="metodo_pago" onchange="adaptarFormularioPago(this.value)" required> 
                        <option value="" disabled selected>Método de pago</option>
                        <option value="pagomovil">Pago Móvil</option>
                        <option value="divisa">Divisa</option>
                        <option value="transferencia">Transferencia</option>
                    </select>        
                </div>

                <div class="input-group">
                    <select name="id_plan" id="pago-plan" required>
                        <option value="">Cargando planes…</option>
                    </select>
                </div>

                <div class="input-group">
                    <output id="pago-monto-mostrado" class="payment-amount">Selecciona un plan</output>
                </div>
                <p id="tasa-bcv-pago" class="full-width" role="status"></p>

                <div class="input-group full-width" id="contenedor-dinamico" style="display: none;">
                    <input type="text" id="input-dinamico" name="reference" placeholder="" />
                </div>
                
                <input type="submit" value="Procesar Transacción" class="full-width" />
            </form>
        </div>
    </div>
</div>
</div>

<div id="vista-config" style="display:none; flex-direction:column; gap:20px; padding:20px;">
    <section class="config-card">
        <h2>Planes de membresía</h2>
        <form id="form-plan" onsubmit="guardarPlan(event)">
            <label>Nombre
                <input type="text" name="nombre" maxlength="80" placeholder="Nombre del plan" required>
            </label>
            <label>Precio (USD)
                <input type="number" name="precio" min="0.01" step="0.01" placeholder="Precio en USD" required>
            </label>
            <button type="submit" class="btn-nuevo">Añadir plan</button>
        </form>
        <div id="lista-planes"></div>
        <p>Todos los planes duran un mes y sus precios se definen en USD. Los pagos en bolívares se convierten con la tasa USD del BCV vigente al registrar el pago. Los planes quitados se desactivan para preservar el historial.</p>
    </section>
    <section class="config-card">
        <h2>Tasa de cambio BCV</h2>
        <p id="tasa-bcv-admin" role="status">Consultando tasa oficial…</p>
        <a href="https://www.bcv.org.ve/" target="_blank" rel="noopener noreferrer">Ver tasa publicada por el BCV</a>
        <form id="form-tasa-manual" onsubmit="guardarTasaManual(event)" hidden>
            <label>Tasa manual (Bs por USD)
                <input type="number" name="tasa_bcv" min="0.00000001" step="0.00000001" required>
            </label>
            <button type="submit" class="btn-nuevo">Guardar tasa manual</button>
        </form>
        <p>Si no se puede consultar al BCV, solo se reutiliza una consulta exitosa del mismo día. Si no existe, un administrador puede ingresar una tasa manual; la próxima consulta exitosa la reemplaza.</p>
    </section>
    <section class="config-card">
        <h2>Vencimientos y operadores</h2>
        <form id="form-aviso" onsubmit="guardarAviso(event)">
            <label>Días de anticipación para avisar
                <input type="number" name="dias_aviso" min="0" max="90" required>
            </label>
            <button type="submit" class="btn-nuevo">Guardar aviso</button>
        </form>
        <div id="lista-operadores"></div>
    </section>
    <section class="config-card">
        <h2>Resumen de ingresos</h2>
        <form id="form-reporte" onsubmit="cargarReporte(event)">
            <label>Desde <input type="date" name="desde" required></label>
            <label>Hasta <input type="date" name="hasta" required></label>
            <button type="submit" class="btn-nuevo">Consultar</button>
            <button type="button" class="btn-nuevo" onclick="exportarReporteExcel()">Exportar Excel</button>
        </form>
        <div id="resultado-reporte"></div>
    </section>
    <section class="config-card">
        <h2>Registrar operador</h2>
        <form id="form-nuevo-operador" onsubmit="registrarOperador(event)">
            <input type="text" name="nombres" placeholder="Nombres" required>
            <input type="text" name="apellidos" placeholder="Apellidos" required>
            <select name="tipo_doc"><option value="V">V</option><option value="E">E</option></select>
            <input type="text" inputmode="numeric" name="ci" placeholder="Cédula (6 a 9 dígitos)" pattern="[0-9]{6,9}" minlength="6" maxlength="9" required>
            <input type="tel" name="tlf" placeholder="Teléfono, ej. 0412-1234567" pattern="04[0-9]{2}-?[0-9]{7}" maxlength="12" required>
            <input type="email" name="contacto" maxlength="254" placeholder="Correo de recuperación" required>
            <input type="text" name="direccion" placeholder="Dirección">
            <input type="text" name="username" placeholder="Usuario" required>
            <input type="password" name="pw" placeholder="Contraseña (10 a 72 caracteres)" minlength="10" maxlength="72" required>
            <select name="operator_role">
                <option value="operador">Rol: operador</option>
                <option value="admin">Rol: administrador</option>
            </select>
            <button type="submit" class="btn-nuevo">Crear operador</button>
        </form>
    </section>
</div>
    </div>
    
    <div id="footer">
        <h2>Desarrollado por Devnetworking</h2>
    </div>
    
    <!-- MODAL: Expediente del Cliente -->
    <div id="modal-perfil-cliente" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(17, 24, 39, 0.8); z-index: 2000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
        
        <div style="background: white; padding: 25px; border-radius: 12px; width: 85%; max-width: 800px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
            
            <!-- Cabecera del Perfil -->
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px;">
                <h2 id="perfil-nombre" style="margin: 0; color: #1e293b; font-size: 1.5rem;"></h2>
                <button onclick="cerrarPerfilCliente()" style="background: #ef4444; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: bold;">Cerrar </button>
            </div>

            <!-- Datos Completos -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #e2e8f0;">
                <p style="margin: 0;"><strong>Cédula:</strong> <span id="perfil-ci" style="color: #475569;"></span></p>
                <p style="margin: 0;"><strong>Teléfono:</strong> <span id="perfil-tlf" style="color: #475569;"></span></p>
                <p style="margin: 0;"><strong>Email:</strong> <span id="perfil-email" style="color: #475569;"></span></p>
                <p style="margin: 0;"><strong>Estatus:</strong> <span id="perfil-estatus" style="font-weight: bold;"></span></p>
                <p style="margin: 0;"><strong>Vencimiento:</strong> <span id="perfil-vencimiento" style="color: #475569;"></span></p>
                <p style="margin: 0; grid-column: 1 / -1;"><strong>Dirección:</strong> <span id="perfil-direccion" style="color: #475569;"></span></p>
            </div>

            <form id="form-editar-cliente" class="config-card" style="display:none;" onsubmit="guardarEdicionCliente(event)">
                <input type="hidden" name="id">
                    <input type="text" name="ci" placeholder="Cédula" pattern="[VEJGPvejgp]?-?[0-9]{6,9}" maxlength="11" required>
                <input type="text" name="nombres" placeholder="Nombres" required>
                <input type="text" name="apellidos" placeholder="Apellidos">
                <input type="tel" name="tlf" placeholder="Teléfono 04xx-xxxxxxx" pattern="04[0-9]{2}-?[0-9]{7}" maxlength="12" required>
                <input type="email" name="email" maxlength="254" placeholder="Correo (opcional)">
                <input type="text" name="direccion" placeholder="Dirección">
                <label>Vencimiento <input type="date" name="fecha_vencimiento"></label>
                <select name="estatus" required>
                    <option value="Activo">Activo</option>
                    <option value="Inactivo">Inactivo</option>
                    <option value="Deuda">Deuda</option>
                </select>
                <button type="submit" class="btn-nuevo">Guardar cambios</button>
            </form>
            <button id="btn-editar-cliente" class="btn-nuevo" type="button" onclick="alternarEdicionCliente()">Editar cliente</button>

            <!-- Tabla de Pagos -->
            <h3 style="color: #1e293b; margin-bottom: 15px;">Historial de Transacciones</h3>
            <table class="celtable" id="tabla-perfil-pagos" style="width: 100%; border-collapse: collapse;">
                <thead style="background-color: #f1f5f9;">
                    <tr>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #cbd5e1;">Fecha</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #cbd5e1;">Referencia</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #cbd5e1;">Monto</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #cbd5e1;">Tasa BCV</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #cbd5e1;">Método</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #cbd5e1;">Operador</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            
            <!-- Controles de Paginación -->
            <div id="paginacion-pagos" style="display: flex; justify-content: center; gap: 8px; margin-top: 20px;"></div>
        </div>
    </div>

    <script src="lg.js?v=20261018-veformat3"></script>
</body>
</html>
