// --- FUNCIONES PRINCIPALES Y FORMULARIOS ---

window.pagoPendienteForm = null;
let paginaActualClientes = 1;
let paginaActualPagosLista = 1;
let clientePerfilActual = null;
let usuarioTemaActual = '';
let tasaBcvPagoActual = null;

function claveTemaUsuario(usuario) {
    const identificador = String(usuario || '').trim().toLowerCase();
    return identificador ? `ggg-theme:${encodeURIComponent(identificador)}` : 'ggg-theme:guest';
}

function claseEstatusCliente(estatus) {
    if (estatus === 'Activo') return 'status-active';
    if (estatus === 'Por vencer') return 'status-soon';
    if (estatus === 'Vencido' || estatus === 'Deuda') return 'status-alert';
    return 'status-muted';
}

function aplicarTema(tema) {
    const esOscuro = tema === 'dark';
    document.body.dataset.theme = esOscuro ? 'dark' : 'light';
    const boton = document.getElementById('theme-toggle');
    if (!boton) return;
    boton.setAttribute('aria-pressed', String(esOscuro));
    boton.setAttribute('aria-label', esOscuro ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
    boton.querySelector('.theme-toggle-icon').textContent = esOscuro ? '☀' : '☾';
    boton.querySelector('.theme-toggle-text').textContent = esOscuro ? 'Modo claro' : 'Modo oscuro';
}

function establecerTemaUsuario(usuario) {
    usuarioTemaActual = String(usuario || '').trim().toLowerCase();
    const guardado = localStorage.getItem(claveTemaUsuario(usuarioTemaActual))
        || localStorage.getItem('ggg-theme:guest')
        || 'light';
    aplicarTema(guardado);
}

function alternarTema() {
    const nuevoTema = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem(claveTemaUsuario(usuarioTemaActual), nuevoTema);
    aplicarTema(nuevoTema);
}

function moverControlTema(conSesion) {
    const boton = document.getElementById('theme-toggle');
    const destino = conSesion
        ? document.getElementById('dashboard-header-actions')
        : document.getElementById('loginpage');
    if (boton && destino && boton.parentElement !== destino) destino.appendChild(boton);
}

function validarFormulario(form) {
    const campo = nombre => form.querySelector(`[name="${nombre}"]`);
    const cedula = campo('ci') || campo('cedula_identidad');
    if (cedula) {
        const valor = cedula.value.trim();
        const documento = campo('tipo_doc') || campo('tipo_documento');
        const regex = documento && valor.match(/^\d+$/)
            ? /^\d{6,9}$/
            : /^(?:[VEJGP]-?)?\d{6,9}$/i;
        if (!regex.test(valor)) {
            cedula.setCustomValidity('Ingresa una cédula de 6 a 9 dígitos, por ejemplo 30830476.');
            cedula.reportValidity();
            cedula.setCustomValidity('');
            return false;
        }
    }

    const telefono = campo('tlf');
    if (telefono && !/^04\d{2}-?\d{7}$/.test(telefono.value.replace(/\s/g, ''))) {
        telefono.setCustomValidity('Usa el formato venezolano 04xx-xxxxxxx, por ejemplo 0412-1234567.');
        telefono.reportValidity();
        telefono.setCustomValidity('');
        return false;
    }

    const codigo = campo('codigo_operadora');
    const telefonoPago = campo('telefono');
    if (codigo && telefonoPago && (!/^04\d{2}$/.test(codigo.value) || !/^\d{7}$/.test(telefonoPago.value))) {
        telefonoPago.setCustomValidity('Selecciona un código 04xx e ingresa exactamente 7 dígitos.');
        telefonoPago.reportValidity();
        telefonoPago.setCustomValidity('');
        return false;
    }

    const email = campo('email') || campo('contacto');
    if (email && (email.required || email.value.trim() !== '') && (!email.validity.valid || email.value.trim().length > 254)) {
        email.setCustomValidity('Escribe un correo con formato válido (máximo 254 caracteres).');
        email.reportValidity();
        email.setCustomValidity('');
        return false;
    }

    const password = campo('pw');
    if (password && (password.value.length < 10 || new TextEncoder().encode(password.value).length > 72)) {
        password.setCustomValidity('La contraseña debe tener entre 10 y 72 bytes.');
        password.reportValidity();
        password.setCustomValidity('');
        return false;
    }
    return true;
}

async function procesarFormulario(event, url, callbackExito) {
    event.preventDefault();
    const form = event.target;
    if (!validarFormulario(form)) return;

    const boton = form.querySelector('input[type="submit"], button');
    const textoOriginal = boton ? (boton.value || boton.innerText) : '';

    if (boton) {
        boton.disabled = true;
        boton.style.opacity = '0.7';
        if (boton.value) boton.value = "Procesando... 🎬";
        else boton.innerText = "Procesando... 🎬";
    }

    const formData = new FormData(form);

    try {
        const rawResponse = await fetch(url, { method: 'POST', body: formData }).then(r => r.text());

        let res;
        try {
            res = JSON.parse(rawResponse.trim());
        } catch (parseError) {
            console.error("🔥 BASURA INVISIBLE EN PHP 🔥:\n", rawResponse);
            alert("¡Corte! PHP devolvió algo que no es JSON puro. Revisa la consola.");
            return;
        }

        if (res.success) {
            if (url === 'api/registrar_cliente.php' && window.pagoPendienteForm) {
                const ciCliente = String(formData.get('ci') || '').trim();
                const partesCi = ciCliente.match(/^([VEJGP])-?(\d{6,9})$/i);
                if (partesCi) {
                    window.pagoPendienteForm.set('tipo_documento', partesCi[1].toUpperCase());
                    window.pagoPendienteForm.set('cedula_identidad', partesCi[2]);
                } else {
                    window.pagoPendienteForm.set('cedula_identidad', ciCliente);
                }
            }

            if (url === 'api/auth.php') {
                sessionStorage.setItem('sesion_activa', 'true');
                if (res.operador) sessionStorage.setItem('actorVIP', res.operador);
                if (res.role) {
                    sessionStorage.setItem('operador_role', res.role);
                    const botonConfig = Array.from(document.querySelectorAll('.tabs-navigation button')).find(button => button.textContent.includes('Configuración'));
                    if (botonConfig) botonConfig.style.display = res.role === 'admin' ? '' : 'none';
                }
            }

            callbackExito(res);

            if (url === 'api/auth.php') {
                cargarResumenDashboard();
            }

            if (url !== 'api/auth.php') {
                form.reset();
            }

            if (url === 'api/registrar_cliente.php') {
                cargarResumenDashboard();
            }

            if (url === 'api/registrar_pago.php' && typeof adaptarFormularioPago === 'function') {
                adaptarFormularioPago('');
                cargarResumenDashboard();
            }

            // ⭐ MAGIA SUPREMA: PLANO SECUENCIA (PAGO ENCADENADO) ⭐
            if (url === 'api/registrar_cliente.php') {
                const formCliente = document.getElementById('form-cliente');
                if (formCliente && formCliente.style.display !== 'none') {
                    toggleFormularioCliente(); // Cerramos el panel del cliente
                }

                // SI HAY UN PAGO EN PAUSA, LO LANZAMOS DE INMEDIATO
                if (window.pagoPendienteForm) {
                    alert("¡Talento registrado! Rodando la toma del pago pendiente... 🎬");

                    const pagoPendiente = window.pagoPendienteForm;
                    fetch('api/registrar_pago.php', { method: 'POST', body: pagoPendiente })
                        .then(r => r.text())
                        .then(txt => {
                            try { return JSON.parse(txt.trim()); }
                            catch (e) { return { success: false, message: 'Basura en PHP al encadenar el pago' }; }
                        })
                        .then(pagoRes => {
                            if (pagoRes.success) {
                                alert('¡Corte y queda! Transacción procesada con éxito. Cliente y Pago guardados perfectamente en una sola toma.');
                                window.pagoPendienteForm = null;
                                cargarTablaPagos();
                                cargarResumenDashboard();
                                document.getElementById('payform').reset();
                                adaptarFormularioPago('');
                            } else {
                                alert("El cliente se guardó espectacular, pero hubo un drama con el pago: " + pagoRes.message);
                            }
                        }).catch(e => {
                            console.error("Error en pago encadenado:", e);
                            alert('El cliente se guardó, pero no se pudo completar el pago. El pago sigue pendiente; revisa los datos e inténtalo de nuevo.');
                        });
                }
            }

        } else {
            // EL GIRO DE LA TRAMA: Si estamos en la vista de pagos y el cliente no existe
            if (url === 'api/registrar_pago.php' && res.message.includes('registrado')) {

                // ⭐ GUARDAMOS EL PAGO EN EL CAMERINO (MEMORIA) ⭐
                window.pagoPendienteForm = formData;

                alert("¡Corte! El cliente no está registrado. Completa su perfil y procesaremos el pago automáticamente en la misma toma.");

                cargarVista('clientes');

                const formCliente = document.getElementById('form-cliente');
                if (formCliente.style.display === 'none' || formCliente.style.display === '') {
                    toggleFormularioCliente();
                }

                // Inyectamos la cédula desde el pago
                const cedulaFallida = form.querySelector('input[name="cedula_identidad"]').value;
                const docFallido = form.querySelector('select[name="tipo_documento"]').value;
                if (cedulaFallida) {
                    // Ahora inyectamos la letra y el número juntos en el registro rápido
                    document.querySelector('#form-cliente input[name="ci"]').value = (docFallido ? docFallido + "-" : "") + cedulaFallida;
                }

                const codOperadora = form.querySelector('select[name="codigo_operadora"]').value;
                const numeroTlf = form.querySelector('input[name="telefono"]').value;
                if (codOperadora || numeroTlf) {
                    document.querySelector('#form-cliente input[name="tlf"]').value = (codOperadora || '') + (numeroTlf || '');
                }

                // LA GUILLOTINA PARA EL NOMBRE
                const nombreCompleto = document.getElementById('buscador-cliente-pago').value.trim();
                if (nombreCompleto) {
                    const palabras = nombreCompleto.split(/\s+/);
                    let nombresForm = "";
                    let apellidosForm = "";

                    if (palabras.length === 4) {
                        nombresForm = palabras[0] + " " + palabras[1];
                        apellidosForm = palabras[2] + " " + palabras[3];
                    } else if (palabras.length === 2) {
                        nombresForm = palabras[0];
                        apellidosForm = palabras[1];
                    } else if (palabras.length === 3) {
                        nombresForm = palabras[0] + " " + palabras[1];
                        apellidosForm = palabras[2];
                    } else if (palabras.length > 4) {
                        nombresForm = palabras[0] + " " + palabras[1];
                        apellidosForm = palabras.slice(2).join(" ");
                    } else {
                        nombresForm = palabras[0];
                    }

                    document.querySelector('#form-cliente input[name="nombres"]').value = nombresForm;
                    document.querySelector('#form-cliente input[name="apellidos"]').value = apellidosForm;
                }

            } else {
                alert("Error en el set: " + res.message);
            }
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
    const vistaConfig = document.getElementById('vista-config');
    document.querySelectorAll('.tabs-navigation .tab-btn').forEach(button => {
        const activa = button.getAttribute('onclick') === `cargarVista('${nombreVista}')`;
        button.classList.toggle('activa', activa);
        button.setAttribute('aria-pressed', String(activa));
    });

    if (vistaClientes) vistaClientes.style.display = 'none';
    if (vistaPagos) vistaPagos.style.display = 'none';
    if (vistaConfig) vistaConfig.style.display = 'none';

    if (nombreVista === 'clientes' && vistaClientes) {
        vistaClientes.style.display = 'flex';
        cargarTablaClientes();
    }
    else if (nombreVista === 'pagos' && vistaPagos) {
        vistaPagos.style.display = 'flex';
        cargarTablaPagos();
        cargarPlanesPago();
    } else if (nombreVista === 'config' && vistaConfig && sessionStorage.getItem('operador_role') === 'admin') {
        vistaConfig.style.display = 'flex';
        cargarConfiguracion();
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

async function solicitarRecuperacion(event) {
    event.preventDefault();
    const form = event.target;
    const boton = form.querySelector('input[type="submit"], button[type="submit"]');
    const textoOriginal = boton ? (boton.value || boton.innerText) : '';
    if (boton) boton.disabled = true;
    try {
        const response = await fetch('api/solicitar_recuperacion.php', { method: 'POST', body: new FormData(form) });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'No se pudo solicitar el enlace.');
        }
        alert(data.message);
        if (data.success) {
            form.reset();
            toggleLogin('login');
        }
    } catch (error) {
        alert(error.message || 'No se pudo procesar la solicitud.');
    } finally {
        if (boton) {
            boton.disabled = false;
            if (boton.value) boton.value = textoOriginal;
            else boton.innerText = textoOriginal;
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

function cargarTablaClientes(pagina = 1) {
    paginaActualClientes = pagina;
    const inputBusqueda = document.getElementById('input-busqueda');
    const selectEstatus = document.getElementById('filtro-estatus');

    const query = inputBusqueda ? inputBusqueda.value : '';
    const estatus = selectEstatus ? selectEstatus.value : '';

    const tbody = document.querySelector('#tabla-clientes tbody');
    if (!tbody) return;

    fetch(`api/buscar_clientes.php?q=${encodeURIComponent(query)}&estatus=${encodeURIComponent(estatus)}&pagina=${pagina}&por_pagina=20`)
        .then(response => response.json())
        .then(data => {
            tbody.innerHTML = '';

            if (data.success && data.data.length > 0) {
                data.data.forEach(cliente => {
                    const tr = document.createElement('tr');

                    tr.onclick = () => abrirPerfilCliente(cliente.id);

                    [cliente.ci, `${cliente.nombres} ${cliente.apellidos}`, cliente.tlf, cliente.estatus].forEach((value, index) => {
                        const td = document.createElement('td');
                        if (index === 3) {
                            const estatus = document.createElement('span');
                            estatus.className = `client-status ${claseEstatusCliente(cliente.estatus)}`;
                            estatus.textContent = value || '';
                            td.appendChild(estatus);
                        } else {
                            td.textContent = value || '';
                        }
                        tr.appendChild(td);
                    });
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 20px;">No hay talentos que coincidan con la búsqueda. 🎬</td></tr>';
            }
            dibujarPaginacionLista('paginacion-clientes', data.paginas || 1, pagina, cargarTablaClientes);
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
        input.placeholder = 'Número de Serie del Billete (Opcional)';
        input.required = false; // ¡MAGIA! Ya no es obligatorio
    } else if (metodo === 'pagomovil' || metodo === 'transferencia') {
        contenedor.style.display = 'block';
        input.placeholder = 'Número de Referencia Bancaria';
        input.required = true;
    } else {
        contenedor.style.display = 'none';
        input.required = false;
        input.value = '';
    }
    cargarPlanesPago();
}

function cargarTablaPagos(pagina = 1) {
    paginaActualPagosLista = pagina;
    const inputBusqueda = document.getElementById('input-busqueda-pagos');
    const query = inputBusqueda ? inputBusqueda.value : '';
    const tbody = document.querySelector('#tabla-pagos tbody');
    if (!tbody) return;
    const params = new URLSearchParams({
        q: query,
        pagina: String(pagina),
        por_pagina: '20',
        desde: document.getElementById('filtro-pago-desde')?.value || '',
        hasta: document.getElementById('filtro-pago-hasta')?.value || '',
        metodo: document.getElementById('filtro-pago-metodo')?.value || ''
    });

    fetch(`api/buscar_pagos.php?${params}`)
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

                    const values = [
                        `${pago.nombres || ''} ${pago.apellidos || ''} (C.I: ${pago.ci || ''})`,
                        `${pago.reference || ''} — ${fechaAmigable}`,
                        formatearNumeroVenezolano(pago.monto),
                        pago.coin,
                        pago.tasa_bcv ? `${formatearNumeroVenezolano(pago.tasa_bcv, 8)} Bs/USD · Fecha valor ${pago.fecha_valor_bcv}` : '—',
                        pago.metodo_pago || '',
                        operadorNombre
                    ];
                    values.forEach(value => {
                        const td = document.createElement('td');
                        td.textContent = value ?? '';
                        tr.appendChild(td);
                    });
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 20px;">No hay transacciones que coincidan con la búsqueda.</td></tr>';
            }
            dibujarPaginacionLista('paginacion-pagos-lista', data.paginas || 1, pagina, cargarTablaPagos);
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

    fetch(`api/buscar_clientes.php?q=${encodeURIComponent(query)}`)
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

async function abrirPerfilCliente(idCliente) {
    try {
        const response = await fetch(`api/perfil_cliente.php?id=${encodeURIComponent(idCliente)}`);
        const data = await response.json();
            if (data.success) {
                const c = data.cliente;
                clientePerfilActual = c;
                const formEditar = document.getElementById('form-editar-cliente');
                ['id', 'ci', 'nombres', 'apellidos', 'tlf', 'email', 'direccion', 'estatus', 'fecha_vencimiento'].forEach(campo => {
                    if (formEditar.elements[campo]) formEditar.elements[campo].value = c[campo] || '';
                });
                formEditar.style.display = 'none';

                document.getElementById('perfil-nombre').innerText = `🎭 ${c.nombres} ${c.apellidos}`;
                document.getElementById('perfil-ci').innerText = c.ci;
                document.getElementById('perfil-tlf').innerText = c.tlf || 'No registrado';
                document.getElementById('perfil-email').innerText = c.email || 'No registrado';
                document.getElementById('perfil-direccion').innerText = c.direccion || 'No registrada';

                const estatusEl = document.getElementById('perfil-estatus');
                estatusEl.innerText = c.estatus_calculado;
                estatusEl.className = claseEstatusCliente(c.estatus_calculado);

                document.getElementById('perfil-vencimiento').innerText = c.fecha_vencimiento || 'Sin registro';

                pagosActualesPerfil = data.pagos;
                paginaActualPagos = 1;
                renderizarTablaPagosPerfil();

                document.getElementById('modal-perfil-cliente').style.display = 'flex';
            } else {
                alert(data.message);
            }
    } catch (err) {
        console.error("Error cargando el expediente:", err);
        alert('No se pudo cargar el perfil del cliente.');
    }
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
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 15px;">Este talento no tiene pagos registrados.</td></tr>';
    } else {
        pagosDeEstaPagina.forEach(p => {
            const tr = document.createElement('tr');

            const fechaAmigable = new Date(p.fecha).toLocaleString('es-VE', {
                day: '2-digit', month: '2-digit', year: 'numeric'
            });

            [
                fechaAmigable,
                p.reference,
                `${formatearNumeroVenezolano(p.monto)} ${p.coin}`,
                p.tasa_bcv ? `${formatearNumeroVenezolano(p.tasa_bcv, 8)} Bs/USD · Fecha valor ${p.fecha_valor_bcv}` : '—',
                p.metodo_pago || '',
                p.operador
            ].forEach(value => {
                const td = document.createElement('td');
                td.textContent = value || '';
                td.style.padding = '10px';
                td.style.borderBottom = '1px solid #eee';
                tr.appendChild(td);
            });
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

function dibujarPaginacionLista(id, paginas, actual, cargar) {
    const contenedor = document.getElementById(id);
    if (!contenedor) return;
    contenedor.innerHTML = '';
    for (let pagina = 1; pagina <= paginas; pagina++) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.textContent = String(pagina);
        boton.disabled = pagina === actual;
        boton.onclick = () => cargar(pagina);
        contenedor.appendChild(boton);
    }
}

function formatoTasaBcv(tasa) {
    const valor = formatearNumeroVenezolano(tasa.rate, 8);
    const fuente = tasa.source === 'manual' ? 'Tasa manual' : 'BCV';
    return `${valor} Bs/USD · Fecha valor ${tasa.value_date} · ${fuente}`;
}

function formatearNumeroVenezolano(valor, maximoDecimales = 2) {
    return Number(valor).toLocaleString('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: maximoDecimales
    });
}

function actualizarMontoPago() {
    const select = document.getElementById('pago-plan');
    const option = select?.selectedOptions[0];
    const output = document.getElementById('pago-monto-mostrado');
    const metodo = document.getElementById('metodo_pago')?.value || '';
    const boton = document.querySelector('#payform input[type="submit"]');
    if (!option?.dataset.precio) {
        output.textContent = 'Selecciona un plan';
        if (boton) boton.disabled = true;
        return;
    }

    const precioUsd = Number(option.dataset.precio);
    if (metodo === 'divisa') {
        output.textContent = `${formatearNumeroVenezolano(precioUsd)} USD por 1 mes`;
        if (boton) boton.disabled = false;
    } else if (!metodo) {
        output.textContent = `${formatearNumeroVenezolano(precioUsd)} USD por 1 mes`;
        if (boton) boton.disabled = false;
    } else if (!tasaBcvPagoActual?.available) {
        output.textContent = 'No se puede calcular el monto en VES: falta una tasa válida del BCV.';
        if (boton) boton.disabled = true;
    } else {
        const montoVes = Math.round((precioUsd * Number(tasaBcvPagoActual.rate) + Number.EPSILON) * 100) / 100;
        output.textContent = `${formatearNumeroVenezolano(montoVes)} VES (${formatearNumeroVenezolano(precioUsd)} USD por 1 mes)`;
        if (boton) boton.disabled = false;
    }
}

async function cargarPlanesPago() {
    const select = document.getElementById('pago-plan');
    if (!select) return;
    try {
        const response = await fetch('api/planes_api.php');
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudieron cargar los planes.');
        tasaBcvPagoActual = data.tasa_bcv;
        const estadoTasa = document.getElementById('tasa-bcv-pago');
        estadoTasa.textContent = tasaBcvPagoActual?.available
            ? formatoTasaBcv(tasaBcvPagoActual)
            : tasaBcvPagoActual?.message || 'No se pudo obtener una tasa del BCV.';
        estadoTasa.dataset.available = String(Boolean(tasaBcvPagoActual?.available));
        select.innerHTML = '';
        data.planes.forEach(plan => {
            const option = document.createElement('option');
            option.value = plan.id;
            option.textContent = `${plan.nombre} — ${formatearNumeroVenezolano(plan.precio)} USD / 1 mes`;
            option.dataset.precio = plan.precio;
            select.appendChild(option);
        });
        if (select.options.length === 0) {
            select.add(new Option('No hay planes activos configurados', ''));
            document.getElementById('pago-monto-mostrado').textContent = 'Selecciona un plan';
            return;
        }
        select.onchange = actualizarMontoPago;
        actualizarMontoPago();
    } catch (error) {
        select.innerHTML = '';
        select.add(new Option('Error cargando planes', ''));
        alert(error.message);
    }
}

async function cargarResumenDashboard() {
    try {
        const response = await fetch('api/dashboard_summary.php');
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo cargar el resumen.');
        document.getElementById('resumen-activos').textContent = data.activos;
        document.getElementById('resumen-inactivos').textContent = data.inactivos;
        document.getElementById('resumen-deuda').textContent = data.con_deuda;
        document.getElementById('resumen-vencidos').textContent = data.vencidos;
        document.getElementById('resumen-por-vencer').textContent = data.por_vencer;
        const errorResumen = document.getElementById('resumen-error');
        errorResumen.hidden = true;
        errorResumen.textContent = '';
    } catch (error) {
        console.error(error);
        const errorResumen = document.getElementById('resumen-error');
        errorResumen.textContent = 'No se pudo cargar el resumen de clientes. Actualiza la página o vuelve a iniciar sesión.';
        errorResumen.hidden = false;
    }
}

async function cargarConfiguracion() {
    try {
        const response = await fetch('api/configuracion_api.php');
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo cargar configuración.');
        document.querySelector('#form-aviso [name="dias_aviso"]').value = data.dias_aviso;
        const hoy = new Date();
        const fechaHoy = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
        const primerDia = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-01`;
        document.querySelector('#form-reporte [name="desde"]').value = primerDia;
        document.querySelector('#form-reporte [name="hasta"]').value = fechaHoy;
        const listaPlanes = document.getElementById('lista-planes');
        listaPlanes.replaceChildren();
        data.planes.forEach(plan => {
            const form = document.createElement('form');
            form.className = 'config-row plan-row';
            form.onsubmit = event => guardarPlanFila(event);

            const id = document.createElement('input');
            id.type = 'hidden';
            id.name = 'id';
            id.value = plan.id;
            form.appendChild(id);

            const nombre = document.createElement('input');
            nombre.type = 'text';
            nombre.name = 'nombre';
            nombre.maxLength = 80;
            nombre.required = true;
            nombre.value = plan.nombre;
            nombre.setAttribute('aria-label', 'Nombre del plan');
            form.appendChild(nombre);

            const precio = document.createElement('input');
            precio.type = 'number';
            precio.name = 'precio';
            precio.min = '0.01';
            precio.step = '0.01';
            precio.required = true;
            precio.value = plan.precio;
            precio.setAttribute('aria-label', 'Precio en USD');
            form.appendChild(precio);

            const guardar = document.createElement('button');
            guardar.type = 'submit';
            guardar.textContent = 'Guardar cambios';
            form.appendChild(guardar);

            const estado = document.createElement('span');
            estado.textContent = Number(plan.activo) === 1 ? 'Activo' : 'Inactivo';
            form.appendChild(estado);

            const alternar = document.createElement('button');
            alternar.type = 'button';
            alternar.textContent = Number(plan.activo) === 1 ? 'Quitar' : 'Reactivar';
            alternar.onclick = () => cambiarEstadoPlan(plan.id, Number(plan.activo) !== 1);
            form.appendChild(alternar);

            listaPlanes.appendChild(form);
        });
        if (data.planes.length === 0) listaPlanes.textContent = 'No hay planes. Añade uno para habilitar el registro de pagos.';
        const estadoTasa = document.getElementById('tasa-bcv-admin');
        const tasa = data.tasa_bcv;
        estadoTasa.textContent = tasa.available
            ? `${formatoTasaBcv(tasa)} · Consultada ${tasa.fetched_at}`
            : tasa.message;
        const formTasaManual = document.getElementById('form-tasa-manual');
        formTasaManual.hidden = tasa.available && tasa.source !== 'manual';
        if (tasa.source === 'manual') {
            formTasaManual.elements.tasa_bcv.value = tasa.rate;
        } else {
            formTasaManual.elements.tasa_bcv.value = '';
        }

        const listaOperadores = document.getElementById('lista-operadores');
        listaOperadores.replaceChildren();
        data.operadores.forEach(operador => {
            const form = document.createElement('form');
            form.className = 'config-row';
            form.onsubmit = event => actualizarRolOperador(event);
            const id = document.createElement('input');
            id.type = 'hidden';
            id.name = 'id';
            id.value = operador.id;
            form.appendChild(id);
            const nombre = document.createElement('span');
            const estaActivo = Number(operador.activo) === 1;
            nombre.textContent = `${operador.username} — ${operador.nombres} ${operador.apellidos} — ${estaActivo ? 'cuenta activa' : 'cuenta desactivada'} — ${operador.contacto_verified_at ? 'correo verificado' : 'correo sin verificar'}`;
            form.appendChild(nombre);
            const email = document.createElement('input');
            email.type = 'email';
            email.name = 'contacto';
            email.maxLength = 254;
            email.required = true;
            email.value = operador.contacto || '';
            email.placeholder = 'Correo de recuperación';
            form.appendChild(email);
            const saveEmail = document.createElement('button');
            saveEmail.type = 'button';
            saveEmail.textContent = 'Guardar correo';
            saveEmail.onclick = () => guardarEmailOperador(operador.id, email.value);
            form.appendChild(saveEmail);
            const role = document.createElement('select');
            role.name = 'role';
            role.add(new Option('Operador', 'operador'));
            role.add(new Option('Administrador', 'admin'));
            role.value = operador.operator_role;
            form.appendChild(role);
            const button = document.createElement('button');
            button.type = 'submit';
            button.textContent = 'Guardar rol';
            form.appendChild(button);
            if (!operador.contacto_verified_at) {
                const verify = document.createElement('button');
                verify.type = 'button';
                verify.textContent = 'Enviar verificación';
                verify.onclick = () => enviarVerificacionCorreo(operador.id);
                form.appendChild(verify);
            }
            const status = document.createElement('button');
            status.type = 'button';
            status.textContent = estaActivo ? 'Desactivar acceso' : 'Reactivar acceso';
            status.disabled = operador.id === data.current_operator_id;
            status.title = status.disabled ? 'No puedes cambiar el estado de tu propia cuenta.' : '';
            status.onclick = () => cambiarEstadoOperador(operador.id, !estaActivo);
            form.appendChild(status);
            listaOperadores.appendChild(form);
        });
    } catch (error) {
        alert(error.message);
    }
}

async function guardarConfigForm(event, action) {
    event.preventDefault();
    const formData = new FormData(event.target);
    formData.set('action', action);
    const response = await fetch('api/configuracion_api.php', { method: 'POST', body: formData });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo guardar.');
    return data;
}

async function guardarPlan(event) {
    try {
        await guardarConfigForm(event, 'plan');
        event.target.reset();
        await cargarConfiguracion();
        await cargarPlanesPago();
        alert('Plan añadido.');
    } catch (error) {
        alert(error.message);
    }
}

async function cambiarEstadoPlan(id, activar) {
    const mensaje = activar
        ? '¿Quieres reactivar este plan? Volverá a estar disponible para registrar pagos.'
        : '¿Quieres quitar este plan? Se desactivará y dejará de estar disponible para nuevos pagos; el historial se conservará.';
    if (!window.confirm(mensaje)) return;

    const formData = new FormData();
    formData.set('action', 'plan_estado');
    formData.set('id', id);
    formData.set('activo', activar ? '1' : '0');
    try {
        const response = await fetch('api/configuracion_api.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo cambiar el estado del plan.');
        await cargarConfiguracion();
        await cargarPlanesPago();
    } catch (error) {
        alert(error.message);
    }
}

async function guardarTasaManual(event) {
    try {
        await guardarConfigForm(event, 'tasa_manual');
        await cargarConfiguracion();
        await cargarPlanesPago();
        alert('Tasa manual guardada. Se reemplazará cuando el BCV vuelva a responder.');
    } catch (error) {
        alert(error.message);
    }
}

async function guardarPlanFila(event) {
    try {
        await guardarConfigForm(event, 'plan');
        await cargarConfiguracion();
        await cargarPlanesPago();
    } catch (error) {
        alert(error.message);
    }
}

async function enviarVerificacionCorreo(id) {
    const formData = new FormData();
    formData.set('action', 'verificar_email');
    formData.set('id', id);
    try {
        const response = await fetch('api/configuracion_api.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo enviar el enlace.');
        alert(data.message || 'Enlace de verificación enviado.');
    } catch (error) {
        alert(error.message);
    }
}

async function guardarEmailOperador(id, contacto) {
    const formData = new FormData();
    formData.set('action', 'guardar_email');
    formData.set('id', id);
    formData.set('contacto', contacto.trim());
    try {
        const response = await fetch('api/configuracion_api.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo guardar el correo.');
        await cargarConfiguracion();
        alert('Correo guardado. Envía el enlace y confirma la dirección para que pueda usarse en recuperación.');
    } catch (error) {
        alert(error.message);
    }
}

async function guardarAviso(event) {
    try {
        await guardarConfigForm(event, 'aviso');
        alert('Aviso actualizado.');
        cargarResumenDashboard();
    } catch (error) {
        alert(error.message);
    }
}

async function actualizarRolOperador(event) {
    try {
        await guardarConfigForm(event, 'role');
        await cargarConfiguracion();
    } catch (error) {
        alert(error.message);
    }
}

async function cambiarEstadoOperador(id, activar) {
    const mensaje = activar
        ? '¿Quieres reactivar el acceso de esta cuenta?'
        : '¿Quieres desactivar esta cuenta? Sus registros históricos se conservarán.';
    if (!window.confirm(mensaje)) return;

    const formData = new FormData();
    formData.set('action', 'estado_operador');
    formData.set('id', id);
    formData.set('activo', activar ? '1' : '0');
    try {
        const response = await fetch('api/configuracion_api.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo cambiar el estado de la cuenta.');
        await cargarConfiguracion();
    } catch (error) {
        alert(error.message);
    }
}

async function registrarOperador(event) {
    event.preventDefault();
    try {
        const response = await fetch('api/register.php', { method: 'POST', body: new FormData(event.target) });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo crear el operador.');
        event.target.reset();
        await cargarConfiguracion();
        alert(data.message);
    } catch (error) {
        alert(error.message);
    }
}

async function cargarReporte(event) {
    event.preventDefault();
    const params = new URLSearchParams(new FormData(event.target));
    try {
        const response = await fetch(`api/reportes.php?${params}`);
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo generar el reporte.');
        const resultado = document.getElementById('resultado-reporte');
        resultado.replaceChildren();
        data.totales.forEach(total => {
            const item = document.createElement('p');
            item.textContent = `${total.coin} — ${total.metodo_pago} — ${total.operador || 'Operador desconocido'}: ${formatearNumeroVenezolano(total.total || 0)} (${total.cantidad} pagos)`;
            resultado.appendChild(item);
        });
        if (!data.totales.length) resultado.textContent = 'No hay pagos en ese período.';
    } catch (error) {
        alert(error.message);
    }
}

function exportarReporteExcel() {
    const form = document.getElementById('form-reporte');
    if (!form.reportValidity()) return;
    const params = new URLSearchParams(new FormData(form));
    params.set('exportar', 'excel');
    window.location.href = `api/reportes.php?${params}`;
}

function alternarEdicionCliente() {
    const form = document.getElementById('form-editar-cliente');
    form.style.display = form.style.display === 'none' ? 'grid' : 'none';
}

async function guardarEdicionCliente(event) {
    event.preventDefault();
    try {
        const response = await fetch('api/actualizar_cliente.php', { method: 'POST', body: new FormData(event.target) });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo guardar el cliente.');
        await abrirPerfilCliente(event.target.elements.id.value);
        cargarTablaClientes(paginaActualClientes);
        cargarResumenDashboard();
        alert('Datos del cliente actualizados.');
    } catch (error) {
        alert(error.message);
    }
}

// --- ⭐ CONTROL MAESTRO DE LA ESCENA (SESIONES Y RECARGAS) ⭐ ---

// --- ⭐ CONTROL MAESTRO DE LA ESCENA (SESIONES Y RECARGAS) ⭐ ---

document.addEventListener('DOMContentLoaded', () => {
    moverControlTema(false);
    establecerTemaUsuario('');
    document.getElementById('theme-toggle')?.addEventListener('click', alternarTema);

    // Verificamos si el actor tiene su pase VIP en esta pestaña específica
    if (!sessionStorage.getItem('sesion_activa')) {
        fetch('api/logout.php').catch(() => { });
        toggleLogin('login');
    } else {
        // Si recargó, verificamos con PHP
        fetch('api/check_session.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // ¡ENCENDEMOS LAS LUCES DEL SET!
                    const loginPage = document.getElementById('loginpage');
                    const dashboard = document.getElementById('dashboard');
                    const nombreActor = document.getElementById('nombre-actor');

                    if (loginPage) loginPage.style.display = 'none';
                    if (dashboard) dashboard.style.display = 'block';
                    establecerTemaUsuario(data.operador);
                    moverControlTema(true);

                    // ¡AQUÍ ESTÁ EL CORTE DEL DIRECTOR! Priorizamos la memoria local
                    if (nombreActor) {
                        const actorGuardado = sessionStorage.getItem('actorVIP');
                        nombreActor.innerText = actorGuardado ? actorGuardado.toUpperCase() : (data.operador ? data.operador.toUpperCase() : 'SISTEMA');
                    }
                    const role = data.role || 'operador';
                    sessionStorage.setItem('operador_role', role);
                    const botonConfig = Array.from(document.querySelectorAll('.tabs-navigation button')).find(button => button.textContent.includes('Configuración'));
                    if (botonConfig) botonConfig.style.display = role === 'admin' ? '' : 'none';

                    // Cargamos la tabla principal
                    cargarVista('clientes');
                    cargarResumenDashboard();
                } else {
                    sessionStorage.removeItem('sesion_activa');
                    moverControlTema(false);
                    toggleLogin('login');
                }
            })
            .catch(error => {
                console.error("Error validando el pase VIP:", error);
                moverControlTema(false);
                toggleLogin('login');
            });
    }
});

function cerrarSesionEscenario() {
    sessionStorage.removeItem('sesion_activa');
    sessionStorage.removeItem('operador_role');
    sessionStorage.removeItem('actorVIP');
    window.location.href = 'api/logout.php';
}