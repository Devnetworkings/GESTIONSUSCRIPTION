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
            cargarVista('vacio'); 
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

        <form id="form-register" method="POST" style="display:none;" onsubmit="procesarFormulario(event, 'register.php', () => { 
            alert('¡Operador registrado!'); 
            toggleLogin('login'); 
        })">
            <h3>NUEVO OPERADOR</h3>
            <div class="register-grid">
                <input type="text" placeholder="NOMBRES" name="nombres" required>
                <input type="text" placeholder="APELLIDOS" name="apellidos" required>
                <input type="text" placeholder="CÉDULA" name="ci" required>
                <input type="text" placeholder="TELÉFONO" name="tlf" required>
                <input type="text" placeholder="DIRECCIÓN" name="direccion" required>
            </div>
            <input type="submit" id="send-register" value="Registrar Operador" /> 
            <input type="button" value="Volver al Login" onclick="toggleLogin('login')" class="btn-volver" />
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
    <form id="form-cliente" class="payform-style" style="display:none;" onsubmit="procesarFormulario(event, 'registrar_cliente.php', (res) => { 
    alert('¡Cliente registrado!'); 
    cargarVista('clientes'); 
})">
    <h3>Registro de Cliente</h3>
    
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

    <div id="addpay" style="display:none;">
    <form id="payform" onsubmit="procesarFormulario(event, 'registrar_pago.php', (res) => { 
        alert('¡Transacción procesada con éxito!'); 
        cargarVista('pagos'); 
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
            <select name="metodo_pago" required> 
                <option value="" disabled selected>Método de pago</option>
                <option value="pagomovil">Pago Móvil</option>
                <option value="divisa">Divisa</option>
                <option value="transferencia">Transferencia</option>
            </select>        
        </div>

        <div class="input-group">
            <input type="number" name="monto" placeholder="Monto... $" step="0.01" required />
        </div>
        
        <input type="submit" value="Procesar Transacción" class="full-width" />
    </form>
</div>
</div>
    </div>
    
    <div id="footer">
        <h2>Desarrollado por Devnetworking</h2>
    </div>
    
    <script src="lg.js"></script>
</body>
</html>
