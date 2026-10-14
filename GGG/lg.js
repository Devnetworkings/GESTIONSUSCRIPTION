// --- FUNCIONES PRINCIPALES Y FORMULARIOS ---

async function procesarFormulario(event, url, callbackExito) {
    event.preventDefault();

    const form = event.target;

    // --- NUEVAS VALIDACIONES DE ESTRELLA (Cédula, Email, Teléfono) ---
    if (form.id === 'form-cliente' || form.id === 'form_cliente') {
        let emailInput = form.querySelector('input[name="email"]');
        let telefonoInput = form.querySelector('input[name="telefono"]');
        let cedulaInput = form.querySelector('input[name="cedula"]');

        let email = emailInput ? emailInput.value : '';
        let telefono = telefonoInput ? telefonoInput.value : '';
        let cedula = cedulaInput ? cedulaInput.value : '';

        // Validación de Cédula (V- o E- seguido de números)
        let cedulaRegex = /^[VEJGP]-\d{6,9}$/i;
        if (cedula !== "" && !cedula.match(/^\d+$/) && !cedulaRegex.test(cedula)) {
            alert("¡Corte! Formato de Cédula inválido. (Ej: V-12345678)");
            return;
        }

        // Validación de email solo si no está vacío
        if (email !== "") {
            let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert("¡Corte! El correo electrónico no tiene un formato válido.");
                return;
            }
        }

        // Validación de teléfono (solo números, mínimo 10 dígitos) solo si no está vacío
        if (telefono !== "") {
            let telRegex = /^\d{10,15}$/;
            if (!telRegex.test(telefono.replace(/\D/g, ''))) {
                alert("¡Corte! El número de teléfono/celular no es válido.");
                return;
            }
        }
    }
    // -------------------------------------------------------------

    const boton = form.querySelector('input[type="submit"], button');
    const textoOriginal = boton ? (boton.value || boton.innerText) : '';

    if (boton) {
        boton.disabled = true;
        boton.style.opacity = '0.7';
        if (boton.value) boton.value = "Procesando... 🎬";
        else boton.innerText = "Procesando... 🎬";
    }

    try {
        const rawResponse = await fetch(url, { method: 'POST', body: new FormData(form) }).then(r => r.text());

        let res;
        try {
            res = JSON.parse(rawResponse.trim());
        } catch (parseError) {
            console.error("🔥 BASURA INVISIBLE EN PHP 🔥:\n", rawResponse);
            alert("¡Corte! PHP devolvió algo que no es JSON puro. Revisa la consola.");
            return;
        }

        if (res.success) {

            // ⭐ EL TOQUE MÁGICO: Guardamos el pase VIP en la pestaña ⭐
            if (url === 'auth.php' || url === 'login.php') {
                sessionStorage.setItem('sesion_activa', 'true');
            }

            callbackExito(res);

            // Solo reseteamos si NO es el login, para no interrumpir la redirección
            if (url !== 'auth.php' && url !== 'login.php') {
                form.reset();
            }

            if (url === 'registrar_pago.php' && typeof adaptarFormularioPago === 'function') {
                adaptarFormularioPago('');
            }
        } else {
            alert("Error en el set: " + res.message);
        }

    } catch (error) {
        console.error("Error de Red o Ejecución:", error);
        alert("¡Fallo crítico en el rodaje!");
    } finally {
        if (boton) {
            boton.disabled = false;
            boton.style.opacity = '1';
            if (boton.value) boton.value = textoOriginal;
            else boton.innerText = textoOriginal;
        }
    }
}

// --- VISTAS Y NAVEGACIÓN ---

function cargarVista(nombreVista) {
    const vistaClientes = document.getElementById('vista-clientes');
    const vistaPagos = document.getElementById('vista-pagos');

    if (vistaClientes) vistaClientes.style.display = 'none';
    if (vistaPagos) vistaPagos.style.display = 'none';

    if (nombreVista === 'clientes' && vistaClientes) {
        vistaClientes.style.display = 'flex';
        cargarTablaClientes();
    }
    else if (nombreVista === 'pagos' && vistaPagos) {
        vistaPagos.style.display = 'flex';
        cargarTablaPagos();
    }
}

// Actualizada para manejar Login, Registro y la NUEVA vista de Recuperar Clave de forma segura
function toggleLogin(vista) {
    const formLogin = document.getElementById('formu');
    const formRegister = document.getElementById('form-register');
    const formRecovery = document.getElementById('form-recovery');

    if (formLogin) formLogin.style.display = 'none';
    if (formRegister) formRegister.style.display = 'none';
    if (formRecovery) formRecovery.style.display = 'none';

    if (vista === 'register' && formRegister) {
        formRegister.style.display = 'flex';
    } else if (vista === 'recovery' && formRecovery) {
        formRecovery.style.display = 'flex';
    } else if (formLogin) {
        formLogin.style.display = 'flex';
    }
}

// --- NUEVO: FUNCIÓN PARA RECUPERAR CLAVE ---

async function recuperarClave(event) {
    event.preventDefault();
    const form = event.target;
    const emailInput = form.querySelector('input[type="email"]');
    const boton = form.querySelector('button[type="submit"]');
    const textoOriginal = boton ? boton.innerText : '';

    if (!emailInput || !emailInput.value) {
        alert("¡Corte! Necesitamos el correo electrónico para enviarte la recuperación.");
        return;
    }

    if (boton) {
        boton.disabled = true;
        boton.style.opacity = '0.7';
        boton.innerText = "Enviando correo... ✉️🎬";
    }

    try {
        const rawResponse = await fetch('recuperar_clave.php', { method: 'POST', body: new FormData(form) }).then(r => r.text());

        let res;
        try {
            res = JSON.parse(rawResponse.trim());
        } catch (e) {
            console.error("🔥 Error en la matrix (PHP) al recuperar clave 🔥:\n", rawResponse);
            alert("Error de efectos especiales. Revisa la consola.");
            return;
        }

        if (res.success) {
            alert("¡Acción! Revisa tu bandeja de entrada. Te hemos enviado las instrucciones de recuperación.");
            form.reset();
            toggleLogin('login'); // Volvemos al inicio
        } else {
            alert("Corte: " + res.message);
        }
    } catch (error) {
        console.error("Error de conexión:", error);
        alert("¡Fallo en el servicio de correo!");
    } finally {
        if (boton) {
            boton.disabled = false;
            boton.style.opacity = '1';
            boton.innerText = textoOriginal;
        }
    }
}

// --- GESTIÓN DE CLIENTES ---

function toggleFormularioCliente() {
    const form = document.getElementById('form-cliente');
    const boton = document.querySelector('.btn-nuevo');

    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'grid';
        if (boton) {
            boton.innerHTML = '- Cerrar Formulario';
            boton.classList.add('btn-cerrar');
        }
    } else {
        form.style.display = 'none';
        if (boton) {
            boton.innerHTML = '+ Nuevo Cliente';
            boton.classList.remove('btn-cerrar');
        }
    }
}

function cargarTablaClientes() {
    const inputBusqueda = document.getElementById('input-busqueda');
    const selectEstatus = document.getElementById('filtro-estatus');

    const query = inputBusqueda ? inputBusqueda.value : '';
    const estatus = selectEstatus ? selectEstatus.value : '';

    const tbody = document.querySelector('#tabla-clientes tbody');
    if (!tbody) return;

    fetch(`buscar_clientes.php?q=${encodeURIComponent(query)}&estatus=${encodeURIComponent(estatus)}`)
        .then(response => response.json())
        .then(data => {
            tbody.innerHTML = '';

            if (data.success && data.data.length > 0) {
                data.data.forEach(cliente => {
                    const tr = document.createElement('tr');

                    tr.style.cursor = 'pointer';
                    tr.onmouseover = () => tr.style.backgroundColor = '#f1f5f9';
                    tr.onmouseout = () => tr.style.backgroundColor = 'transparent';
                    tr.onclick = () => abrirPerfilCliente(cliente.id);

                    tr.innerHTML = `
                        <td>${cliente.ci}</td>
                        <td>${cliente.nombres} ${cliente.apellidos}</td>
                        <td>${cliente.tlf}</td>
                        <td style="font-weight: bold; color: ${cliente.estatus === 'Activo' ? 'green' :
                            (cliente.estatus === 'Deuda' ? 'red' : 'gray')
                        };">
                            ${cliente.estatus}
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 20px;">No hay talentos que coincidan con la búsqueda. 🎬</td></tr>';
            }
        })
        .catch(error => console.error("Error de conexión en el set:", error));
}

// --- GESTIÓN DE PAGOS ---

function toggleFormularioPago() {
    const formContainer = document.getElementById('addpay');
    const boton = document.getElementById('btn-nuevo-pago');

    if (formContainer.style.display === 'none' || formContainer.style.display === '') {
        formContainer.style.display = 'block';
        if (boton) {
            boton.innerHTML = '- Cancelar Pago';
            boton.classList.add('btn-cerrar');
        }
    } else {
        formContainer.style.display = 'none';
        if (boton) {
            boton.innerHTML = '+ Nuevo Pago';
            boton.classList.remove('btn-cerrar');
        }
    }
}

function adaptarFormularioPago(metodo) {
    const contenedor = document.getElementById('contenedor-dinamico');
    const input = document.getElementById('input-dinamico');
    if (!contenedor || !input) return;

    if (metodo === 'divisa') {
        contenedor.style.display = 'block';
        input.placeholder = 'Número de Serie del Billete (o "EFECTIVO")';
        input.required = true;
    } else if (metodo === 'pagomovil' || metodo === 'transferencia') {
        contenedor.style.display = 'block';
        input.placeholder = 'Número de Referencia Bancaria';
        input.required = true;
    } else {
        contenedor.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}

function cargarTablaPagos() {
    const inputBusqueda = document.getElementById('input-busqueda-pagos');
    const query = inputBusqueda ? inputBusqueda.value : '';
    const tbody = document.querySelector('#tabla-pagos tbody');
    if (!tbody) return;

    fetch(`buscar_pagos.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            tbody.innerHTML = '';

            if (!data.success) {
                alert("Error de dirección: " + data.message);
                return;
            }

            if (data.data.length > 0) {
                data.data.forEach(pago => {
                    const tr = document.createElement('tr');

                    const fechaAmigable = pago.fecha ? new Date(pago.fecha).toLocaleString('es-VE', {
                        day: '2-digit', month: '2-digit', year: 'numeric',
                        hour: '2-digit', minute: '2-digit'
                    }) : 'Sin fecha';

                    const operadorNombre = pago.nombre_operador ? pago.nombre_operador : 'DESCONOCIDO';

                    tr.innerHTML = `
                        <td>
                            ${pago.nombres} ${pago.apellidos}<br>
                            <small style="color: gray;">C.I: ${pago.ci}</small>
                        </td>
                        <td>
                            <span style="font-weight: bold;">${pago.reference}</span><br>
                            <small style="color: #555;">${fechaAmigable}</small>
                        </td>
                        <td style="font-weight: bold;">${pago.monto}</td>
                        <td style="color: ${pago.coin === 'USD' ? 'green' : '#0056b3'}; font-weight: bold;">
                            ${pago.coin}
                        </td>
                        <td style="text-transform: uppercase; font-size: 0.9em;">
                            👤 ${operadorNombre}
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding: 20px;">No hay transacciones que coincidan con la búsqueda.</td></tr>';
            }
        })
        .catch(error => console.error("Error en el set de pagos:", error));
}
// --- BÚSQUEDA Y AUTOCOMPLETADO DE CLIENTES PARA PAGOS ---

function sugerirClientes(query) {
    const caja = document.getElementById('sugerencias-clientes');
    if (!caja) return;

    if (query.length < 2) {
        caja.style.display = 'none';
        return;
    }

    fetch(`buscar_clientes.php?q=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
            caja.innerHTML = '';
            if (data.success && data.data.length > 0) {
                data.data.forEach(cliente => {
                    const div = document.createElement('div');
                    div.style.padding = '10px';
                    div.style.cursor = 'pointer';
                    div.style.borderBottom = '1px solid #eee';
                    div.textContent = `🎭 ${cliente.ci} - ${cliente.nombres} ${cliente.apellidos}`;

                    div.onclick = () => seleccionarClienteParaPago(cliente);
                    caja.appendChild(div);
                });
                caja.style.display = 'block';
            } else {
                caja.style.display = 'none';
            }
        });
}

function seleccionarClienteParaPago(cliente) {
    document.getElementById('buscador-cliente-pago').value = `${cliente.nombres} ${cliente.apellidos}`;

    const inputCedula = document.querySelector('input[name="cedula_identidad"]');
    const selectTipoDoc = document.querySelector('select[name="tipo_documento"]');

    if (cliente.ci.includes('-')) {
        const partes = cliente.ci.split('-');
        if (selectTipoDoc) selectTipoDoc.value = partes[0];
        if (inputCedula) inputCedula.value = partes[1];
    } else {
        if (inputCedula) inputCedula.value = cliente.ci;
    }

    const inputTlf = document.querySelector('input[name="telefono"]');
    const selectCod = document.querySelector('select[name="codigo_operadora"]');

    if (cliente.tlf) {
        let tlfLimpio = cliente.tlf.replace(/\D/g, '');

        if (tlfLimpio.length >= 11) {
            let prefijo = tlfLimpio.substring(0, 4);
            let numero = tlfLimpio.substring(4);

            let existePrefijo = false;
            if (selectCod) {
                existePrefijo = Array.from(selectCod.options).some(opt => opt.value === prefijo);
            }

            if (existePrefijo && selectCod) {
                selectCod.value = prefijo;
                if (inputTlf) inputTlf.value = numero;
            } else {
                if (selectCod) selectCod.value = "";
                if (inputTlf) inputTlf.value = tlfLimpio;
            }
        } else {
            if (selectCod) selectCod.value = "";
            if (inputTlf) inputTlf.value = tlfLimpio;
        }
    } else {
        if (selectCod) selectCod.value = "";
        if (inputTlf) inputTlf.value = "";
    }

    const caja = document.getElementById('sugerencias-clientes');
    if (caja) caja.style.display = 'none';
}

// --- SISTEMA DE EXPEDIENTES Y PAGINACIÓN ---

let pagosActualesPerfil = [];
let paginaActualPagos = 1;
const limitePorPagina = 10;

function abrirPerfilCliente(idCliente) {
    fetch(`perfil_cliente.php?id=${idCliente}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const c = data.cliente;

                document.getElementById('perfil-nombre').innerText = `🎭 ${c.nombres} ${c.apellidos}`;
                document.getElementById('perfil-ci').innerText = c.ci;
                document.getElementById('perfil-tlf').innerText = c.tlf || 'No registrado';
                document.getElementById('perfil-email').innerText = c.email || 'No registrado';
                document.getElementById('perfil-direccion').innerText = c.direccion || 'No registrada';

                const estatusEl = document.getElementById('perfil-estatus');
                estatusEl.innerText = c.estatus;
                if (c.estatus === 'Activo') estatusEl.style.color = 'green';
                else if (c.estatus === 'Deuda') estatusEl.style.color = 'red';
                else estatusEl.style.color = 'gray';

                document.getElementById('perfil-vencimiento').innerText = c.fecha_vencimiento || 'Sin registro';

                pagosActualesPerfil = data.pagos;
                paginaActualPagos = 1;
                renderizarTablaPagosPerfil();

                document.getElementById('modal-perfil-cliente').style.display = 'flex';
            } else {
                alert(data.message);
            }
        })
        .catch(err => console.error("Error cargando el expediente:", err));
}

function cerrarPerfilCliente() {
    const modal = document.getElementById('modal-perfil-cliente');
    if (modal) modal.style.display = 'none';
}

function renderizarTablaPagosPerfil() {
    const tbody = document.querySelector('#tabla-perfil-pagos tbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    const inicio = (paginaActualPagos - 1) * limitePorPagina;
    const fin = inicio + limitePorPagina;
    const pagosDeEstaPagina = pagosActualesPerfil.slice(inicio, fin);

    if (pagosDeEstaPagina.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 15px;">Este talento no tiene pagos registrados.</td></tr>';
    } else {
        pagosDeEstaPagina.forEach(p => {
            const tr = document.createElement('tr');

            const fechaAmigable = new Date(p.fecha).toLocaleString('es-VE', {
                day: '2-digit', month: '2-digit', year: 'numeric'
            });

            tr.innerHTML = `
                <td style="padding: 10px; border-bottom: 1px solid #eee;">${fechaAmigable}</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">${p.reference}</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; color: ${p.coin === 'USD' ? 'green' : '#0056b3'}; font-weight: bold;">
                    ${p.monto} ${p.coin}
                </td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-transform: uppercase;">${p.operador}</td>
            `;
            tbody.appendChild(tr);
        });
    }
    dibujarBotonesPaginacion();
}

function dibujarBotonesPaginacion() {
    const contenedor = document.getElementById('paginacion-pagos');
    if (!contenedor) return;

    contenedor.innerHTML = '';

    const totalPaginas = Math.ceil(pagosActualesPerfil.length / limitePorPagina);
    if (totalPaginas <= 1) return;

    for (let i = 1; i <= totalPaginas; i++) {
        const btn = document.createElement('button');
        btn.innerText = i;
        btn.style.padding = '5px 12px';
        btn.style.border = '1px solid #cbd5e1';
        btn.style.borderRadius = '4px';
        btn.style.cursor = 'pointer';

        if (i === paginaActualPagos) {
            btn.style.background = '#3b82f6';
            btn.style.color = 'white';
            btn.style.borderColor = '#3b82f6';
        } else {
            btn.style.background = 'white';
            btn.style.color = '#334155';
        }

        btn.onclick = () => {
            paginaActualPagos = i;
            renderizarTablaPagosPerfil();
        };
        contenedor.appendChild(btn);
    }
}

// --- ⭐ CONTROL MAESTRO DE LA ESCENA (SESIONES Y RECARGAS) ⭐ ---

// --- ⭐ CONTROL MAESTRO DE LA ESCENA (SESIONES Y RECARGAS) ⭐ ---

document.addEventListener('DOMContentLoaded', () => {
    // Verificamos si el actor tiene su pase VIP en esta pestaña específica
    if (!sessionStorage.getItem('sesion_activa')) {
        fetch('logout.php').catch(() => { });
        toggleLogin('login');
    } else {
        // Si recargó, verificamos con PHP
        fetch('check_session.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // ¡ENCENDEMOS LAS LUCES DEL SET!
                    const loginPage = document.getElementById('loginpage');
                    const dashboard = document.getElementById('dashboard');
                    const nombreActor = document.getElementById('nombre-actor');

                    if (loginPage) loginPage.style.display = 'none';
                    if (dashboard) dashboard.style.display = 'block';

                    // Colocamos el nombre en la barra superior (si el backend lo envía)
                    if (nombreActor) {
                        nombreActor.innerText = data.operador ? data.operador.toUpperCase() : 'SISTEMA';
                    }

                    // Cargamos la tabla principal
                    cargarVista('clientes');
                } else {
                    sessionStorage.removeItem('sesion_activa');
                    toggleLogin('login');
                }
            })
            .catch(error => {
                console.error("Error validando el pase VIP:", error);
                toggleLogin('login');
            });
    }
});

function cerrarSesionEscenario() {
    sessionStorage.removeItem('sesion_activa');
    window.location.href = 'logout.php';
}