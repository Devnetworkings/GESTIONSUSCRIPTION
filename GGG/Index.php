<?php
// Sistema de Control Estricto
session_start();

// Si alguien entra a esta URL o presiona F5, quemamos sus credenciales inmediatamente.
session_unset();
session_destroy();

// El set siempre amanece cerrado bajo llave. Cero auto-logins.
$display_login = 'flex';
$display_dashboard = 'none';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>GESTIÓN LOGIN</title>
    <link rel="stylesheet" href="app.css" />
</head>
<body>

  <div id="loginpage" style="display: <?php echo $display_login; ?>;">
        
        <form id="formu" method="POST" onsubmit="procesarFormulario(event, 'auth.php', (res) => { 
            document.getElementById('loginpage').style.display = 'none';
            document.getElementById('dashboard').style.display = 'block';
            document.getElementById('nombre-actor').innerText = res.operador.toUpperCase();
            cargarVista('clientes'); 
        })">
            <h3>INICIAR SESIÓN</h3>
            <input type="text" placeholder="OPERADOR" name="operator" required>
            <input type="password" placeholder="PASSWORD" name="password" required>
            <input type="submit" id="send" value="Enviar"/> 
            <div id="create-recovery"> 
                <input type="button" value="C:O" onclick="toggleLogin('register')" />
                <input type="button" value="R:O" />
            </div>
        </form>

        <form id="form-register" style="display: none;" onsubmit="procesarFormulario(event, 'register.php', (res) => { 
            alert('¡Operador registrado con éxito! Bienvenido al equipo de The Grand Gym 3000.'); 
            toggleLogin('login'); 
            document.getElementById('form-register').reset(); 
        })">
            <h3>NUEVO OPERADOR</h3>
            
            <div class="register-grid">
                <input type="text" name="nombres" placeholder="Nombres" required>
                <input type="text" name="apellidos" placeholder="Apellidos" required>
                
                <div style="display: flex; gap: 5px; grid-column: span 1; margin: 0;">
                    <select name="tipo_doc" style="width: 30%; border-radius: 8px; border: none; padding: 14px; background-color: #f8fafc; font-weight: 600; color: #334155; outline: none;">
                        <option value="V">V</option>
                        <option value="E">E</option>
                    </select>
                    <input type="number" name="ci" placeholder="Cédula" required style="width: 70%;">
                </div>
                
                <input type="number" name="tlf" placeholder="Teléfono (Ej: 04141234567)" required>
                
                <input type="text" name="contacto" placeholder="Corre Electrónico" style="grid-column: span 2;">
                <input type="text" name="direccion" placeholder="Dirección de Habitación" style="grid-column: span 2;">

                <hr class="divider" style="grid-column: span 2;">

                <input type="text" name="username" placeholder="Usuario para el Sistema" required>
                <input type="password" name="pw" placeholder="Contraseña Segura" required>
            </div>

            <input type="submit" id="send-register" value="Registrar Operador">
            <button type="button" class="btn-volver" onclick="toggleLogin('login')">Volver a la Taquilla</button>
        </form>

    </div>

    <div id="dashboard" style="display: <?php echo $display_dashboard; ?>;">
        <div id="header">
            <p><span id="nombre-actor" style="color: #72C9FF;"></span></p>
            <button onclick="window.location.href='logout.php'">Logout</button>
        </div>
        
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
        </div>

        <form id="form-cliente" class="modo-lateral" style="display:none;" onsubmit="procesarFormulario(event, 'registrar_cliente.php', (res) => { 
            alert('¡Cliente registrado!'); 
            cargarTablaClientes(); /* Recarga la tabla mágicamente */
            document.getElementById('form-cliente').reset();
        })">
            <h3>Registro Rápido</h3>
            
            <div class="input-group full-width">
                <input type="text" name="ci" placeholder="Cédula / Identificación" required />
            </div>
            <div class="input-group-row">
                <input type="text" name="nombres" placeholder="Nombres" required />
                <input type="text" name="apellidos" placeholder="Apellidos" required />
            </div>
            <div class="input-group full-width">
                <input type="text" name="tlf" placeholder="Teléfono" required />
            </div>
            <div class="input-group full-width">
                <input type="email" name="email" placeholder="Correo Electrónico" />
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
                        <th>Operador</th>
                    </tr>
                </thead>
                <tbody>
                    </tbody>
            </table>
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
                        <option value="P">P</option>
                    </select>
                    <input type="number" name="cedula_identidad" placeholder="Cédula de identidad" required />
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
                        <optgroup label="Líneas Fijas">
                            <option value="0212">0212</option>
                        </optgroup>
                    </select>
                    <input type="number" name="telefono" placeholder="Número de Teléfono" required />
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
                    <input type="number" name="monto" placeholder="Monto... $" step="0.01" required />
                </div>

                <div class="input-group full-width" id="contenedor-dinamico" style="display: none;">
                    <input type="text" id="input-dinamico" name="reference" placeholder="" />
                </div>
                
                <input type="submit" value="Procesar Transacción" class="full-width" />
            </form>
        </div>
    </div>
</div>
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

            <!-- Tabla de Pagos -->
            <h3 style="color: #1e293b; margin-bottom: 15px;">Historial de Transacciones</h3>
            <table class="celtable" id="tabla-perfil-pagos" style="width: 100%; border-collapse: collapse;">
                <thead style="background-color: #f1f5f9;">
                    <tr>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #cbd5e1;">Fecha</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #cbd5e1;">Referencia</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #cbd5e1;">Monto</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #cbd5e1;">Operador</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            
            <!-- Controles de Paginación -->
            <div id="paginacion-pagos" style="display: flex; justify-content: center; gap: 8px; margin-top: 20px;"></div>
        </div>
    </div>

    <script src="lg.js"></script>
</body>
</html>
