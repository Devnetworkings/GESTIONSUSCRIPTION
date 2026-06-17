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
        <button class="btn-nuevo" onclick="toggleFormularioCliente()">+ Nuevo Cliente</button>
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
        <button class="btn-nuevo" id="btn-nuevo-pago" onclick="toggleFormularioPago()">+ Nuevo Pago</button>
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
                
                <div class="input-group full-width">
                    <input type="text" name="nombre_cliente" placeholder="Nombre del cliente" required />
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
    
    <script src="lg.js"></script>
</body>
</html>
