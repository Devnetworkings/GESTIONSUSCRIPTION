// --- FUNCIONES PRINCIPALES Y FORMULARIOS ---

window.pagoPendienteForm = null;
let paginaActualClientes = 1;
let paginaActualPagosLista = 1;
let clientePerfilActual = null;
let usuarioTemaActual = '';
let tasaBcvPagoActual = null;
let paginaActualAuditoria = 1;
let configuracionTecnicaEditable = false;
let perfilPagosEsAdmin = false;

function establecerFechaPagoHoy() {
    const input = document.getElementById('fecha-pago');
    if (!input) return;
    const partes = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'America/Caracas',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    }).formatToParts(new Date()).reduce((valores, parte) => {
        if (parte.type !== 'literal') valores[parte.type] = parte.value;
        return valores;
    }, {});
    const hoy = `${partes.year}-${partes.month}-${partes.day}`;
    input.max = hoy;
    input.value = hoy;
}

function formatearFechaVenezolana(fecha) {
    const coincidencia = String(fecha || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    return coincidencia ? `${coincidencia[3]}/${coincidencia[2]}/${coincidencia[1]}` : (fecha || 'Sin fecha');
}

function formatearFechaHoraVenezolana(fecha) {
    const coincidencia = String(fecha || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return coincidencia
        ? `${coincidencia[3]}/${coincidencia[2]}/${coincidencia[1]} ${coincidencia[4]}:${coincidencia[5]} VET`
        : 'Sin registro';
}

function fetchConCsrf(url, options = {}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) {
        return Promise.reject(new Error('No se pudo validar la sesión. Recarga la página.'));
    }

    if (options.body instanceof FormData && !options.body.has('csrf_token')) {
        options.body.append('csrf_token', token);
    }

    const headers = new Headers(options.headers || {});
    headers.set('X-CSRF-Token', token);
    return fetch(url, { ...options, headers });
}

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
        const rawResponse = await fetchConCsrf(url, { method: 'POST', body: formData }).then(r => r.text());

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
                window.pagoPendienteForm.set('tipo_documento', String(formData.get('tipo_documento') || ''));
                window.pagoPendienteForm.set('cedula_identidad', String(formData.get('ci') || '').trim());
            }

            if (url === 'api/auth.php') {
                sessionStorage.setItem('sesion_activa', 'true');
                if (res.operador) sessionStorage.setItem('actorVIP', res.operador);
                if (res.role) {
                    sessionStorage.setItem('operador_role', res.role);
                    configurarPestanasRol(res.role);
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
                    fetchConCsrf('api/registrar_pago.php', { method: 'POST', body: pagoPendiente })
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
                                establecerFechaPagoHoy();
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

                const cedulaFallida = form.querySelector('input[name="cedula_identidad"]').value;
                const docFallido = form.querySelector('select[name="tipo_documento"]').value;
                if (cedulaFallida) {
                    document.querySelector('#form-cliente select[name="tipo_documento"]').value = docFallido;
                    document.querySelector('#form-cliente input[name="ci"]').value = cedulaFallida;
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
    const vistaConfigTi = document.getElementById('vista-config-tecnica');
    const vistaCuenta = document.getElementById('vista-cuenta');
    const vistaSoporte = document.getElementById('vista-soporte');
    const role = sessionStorage.getItem('operador_role');
    document.querySelectorAll('.tabs-navigation .tab-btn').forEach(button => {
        const activa = button.getAttribute('onclick') === `cargarVista('${nombreVista}')`;
        button.classList.toggle('activa', activa);
        button.setAttribute('aria-pressed', String(activa));
    });

    if (vistaClientes) vistaClientes.style.display = 'none';
    if (vistaPagos) vistaPagos.style.display = 'none';
    if (vistaConfig) vistaConfig.style.display = 'none';
    if (vistaConfigTi) vistaConfigTi.style.display = 'none';
    if (vistaCuenta) vistaCuenta.style.display = 'none';
    if (vistaSoporte) vistaSoporte.style.display = 'none';

    if (nombreVista === 'clientes' && vistaClientes) {
        vistaClientes.style.display = 'flex';
        cargarTablaClientes();
    }
    else if (nombreVista === 'pagos' && vistaPagos) {
        vistaPagos.style.display = 'flex';
        cargarTablaPagos();
        if (role !== 'soporte_ti') cargarPlanesPago();
    } else if (nombreVista === 'config' && vistaConfig && sessionStorage.getItem('operador_role') === 'admin') {
        vistaConfig.style.display = 'flex';
        cargarConfiguracion();
    } else if (nombreVista === 'config-ti' && vistaConfigTi && role === 'soporte_ti' && configuracionTecnicaEditable) {
        vistaConfigTi.style.display = 'block';
        cargarConfiguracionTecnica();
    } else if (nombreVista === 'cuenta' && vistaCuenta) {
        vistaCuenta.style.display = 'block';
        cargarPerfilOperador();
    } else if (nombreVista === 'soporte' && vistaSoporte && sessionStorage.getItem('operador_role') === 'soporte_ti') {
        vistaSoporte.style.display = 'block';
        cargarAuditoria(1);
    }
}

function configurarPestanasRol(role) {
    document.querySelectorAll('.tabs-navigation .tab-btn').forEach(button => {
        const vista = button.getAttribute('onclick')?.match(/cargarVista\('([^']+)'\)/)?.[1];
        button.style.display = vista === 'config' ? (role === 'admin' ? '' : 'none')
            : vista === 'config-ti' ? 'none'
            : vista === 'soporte' ? (role === 'soporte_ti' ? '' : 'none')
                : vista === 'cuenta' ? '' : '';
    });

    configuracionTecnicaEditable = false;
    actualizarDisponibilidadConfiguracionTecnica(role);

    const esTi = role === 'soporte_ti';
    const controlesEdicion = [
        ['btn-nuevo-cliente', ''],
        ['btn-nuevo-pago', ''],
        ['form-cliente', 'none'],
        ['addpay', 'none'],
        ['btn-editar-cliente', ''],
        ['form-editar-cliente', 'none']
    ];
    controlesEdicion.forEach(([id, display]) => {
        const control = document.getElementById(id);
        if (!control) return;
        control.hidden = esTi;
        control.style.display = esTi ? 'none' : display;
        if (control.matches('form')) {
            control.querySelectorAll('input, select, button').forEach(input => {
                input.disabled = esTi;
            });
        }
    });
    const avisoSoloLectura = document.getElementById('aviso-solo-lectura');
    if (avisoSoloLectura) avisoSoloLectura.style.display = esTi ? 'block' : 'none';
}

async function actualizarDisponibilidadConfiguracionTecnica(role) {
    const tab = document.getElementById('tab-config-tecnica');
    if (!tab || role !== 'soporte_ti') return;

    try {
        const response = await fetch('api/configuracion_tecnica.php');
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'No se pudo validar el acceso a la configuración técnica.');
        }

        configuracionTecnicaEditable = data.editable === true;
        tab.style.display = configuracionTecnicaEditable ? '' : 'none';
    } catch (error) {
        configuracionTecnicaEditable = false;
        tab.style.display = 'none';
        console.error('No se pudo validar la configuración técnica:', error);
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
        const response = await fetchConCsrf('api/solicitar_recuperacion.php', { method: 'POST', body: new FormData(form) });
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
    const boton = document.getElementById('btn-nuevo-cliente');

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
    document.querySelectorAll('.summary-filter').forEach(button => {
        const activa = button.dataset.filter === estatus;
        button.classList.toggle('is-active', activa);
        button.setAttribute('aria-pressed', String(activa));
    });

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

function filtrarClientesDesdeResumen(estatus) {
    const selectEstatus = document.getElementById('filtro-estatus');
    if (!selectEstatus) return;

    selectEstatus.value = selectEstatus.value === estatus ? '' : estatus;
    cargarVista('clientes');
}

function limpiarFiltrosClientes() {
    const inputBusqueda = document.getElementById('input-busqueda');
    const selectEstatus = document.getElementById('filtro-estatus');
    if (inputBusqueda) inputBusqueda.value = '';
    if (selectEstatus) selectEstatus.value = '';
    cargarTablaClientes();
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
            const esAdmin = data.es_admin === true;
            document.querySelectorAll('.th-pago-admin').forEach(th => {
                th.style.display = esAdmin ? '' : 'none';
            });

            if (data.data.length > 0) {
                data.data.forEach(pago => {
                    const tr = document.createElement('tr');

                    const fechaEfectiva = formatearFechaVenezolana(pago.fecha_pago);
                    const fechaRegistro = formatearFechaHoraVenezolana(pago.fecha_registro);

                    const operadorNombre = pago.nombre_operador ? pago.nombre_operador : 'DESCONOCIDO';

                    const values = [
                        `${pago.nombres || ''} ${pago.apellidos || ''} (C.I: ${pago.ci || ''})`,
                        fechaEfectiva,
                        pago.reference || '',
                        formatearNumeroVenezolano(pago.monto),
                        pago.coin,
                        pago.tasa_bcv ? `${formatearNumeroVenezolano(pago.tasa_bcv, 8)} Bs/USD · Fecha valor ${formatearFechaVenezolana(pago.fecha_valor_bcv)}` : '—',
                        pago.metodo_pago || ''
                    ];
                    if (esAdmin) {
                        values.push(fechaRegistro, operadorNombre);
                    }
                    values.forEach(value => {
                        const td = document.createElement('td');
                        td.textContent = value ?? '';
                        tr.appendChild(td);
                    });
                    tbody.appendChild(tr);
                });
            } else {
                const columnasVisibles = esAdmin ? 9 : 7;
                tbody.innerHTML = `<tr><td colspan="${columnasVisibles}" style="text-align:center; padding: 20px;">No hay transacciones que coincidan con la búsqueda.</td></tr>`;
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
                    div.textContent = `👤 ${cliente.ci} - ${cliente.nombres} ${cliente.apellidos}`;

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

    if (selectTipoDoc) selectTipoDoc.value = cliente.tipo_documento || '';
    if (inputCedula) inputCedula.value = cliente.ci_codigo || cliente.ci;

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
                ['id', 'nombres', 'apellidos', 'tlf', 'email', 'direccion', 'estatus', 'fecha_vencimiento'].forEach(campo => {
                    if (formEditar.elements[campo]) formEditar.elements[campo].value = c[campo] || '';
                });
                formEditar.elements.tipo_documento.value = c.tipo_documento || '';
                formEditar.elements.ci.value = c.ci || '';
                formEditar.style.display = 'none';

                document.getElementById('perfil-nombre').innerText = `👤 ${c.nombres} ${c.apellidos}`;
                document.getElementById('perfil-ci').innerText = c.ci_completa || c.ci;
                document.getElementById('perfil-tlf').innerText = c.tlf || 'No registrado';
                document.getElementById('perfil-email').innerText = c.email || 'No registrado';
                document.getElementById('perfil-direccion').innerText = c.direccion || 'No registrada';

                const estatusEl = document.getElementById('perfil-estatus');
                estatusEl.innerText = c.estatus_calculado;
                estatusEl.className = claseEstatusCliente(c.estatus_calculado);

                document.getElementById('perfil-vencimiento').innerText = c.fecha_vencimiento || 'Sin registro';

                pagosActualesPerfil = data.pagos;
                perfilPagosEsAdmin = data.es_admin === true;
                document.querySelectorAll('.th-perfil-pago-admin').forEach(th => {
                    th.style.display = perfilPagosEsAdmin ? '' : 'none';
                });
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
        const columnasVisibles = perfilPagosEsAdmin ? 7 : 5;
        tbody.innerHTML = `<tr><td colspan="${columnasVisibles}" style="text-align:center; padding: 15px;">Este talento no tiene pagos registrados.</td></tr>`;
    } else {
        pagosDeEstaPagina.forEach(p => {
            const tr = document.createElement('tr');

            const fechaEfectiva = formatearFechaVenezolana(p.fecha_pago);
            const fechaRegistro = formatearFechaHoraVenezolana(p.fecha_registro);

            const values = [
                fechaEfectiva,
                p.reference,
                `${formatearNumeroVenezolano(p.monto)} ${p.coin}`,
                p.tasa_bcv ? `${formatearNumeroVenezolano(p.tasa_bcv, 8)} Bs/USD · Fecha valor ${formatearFechaVenezolana(p.fecha_valor_bcv)}` : '—',
                p.metodo_pago || ''
            ];
            if (perfilPagosEsAdmin) {
                values.push(fechaRegistro, p.operador || 'DESCONOCIDO');
            }
            values.forEach(value => {
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
            const row = document.createElement('article');
            row.className = 'operator-card';
            const status = operador.operator_status || (Number(operador.activo) === 1 ? 'Activo' : 'Inactivo');
            const isSelf = String(operador.id) === String(data.current_operator_id);
            const heading = document.createElement('div');
            heading.className = 'operator-heading';
            const name = document.createElement('strong');
            name.textContent = `${operador.username} — ${operador.nombres} ${operador.apellidos}`;
            const badge = document.createElement('span');
            badge.className = `operator-status status-${status.toLowerCase()}`;
            badge.textContent = status;
            heading.append(name, badge);
            row.appendChild(heading);

            const roleForm = document.createElement('form');
            roleForm.className = 'operator-controls';
            roleForm.onsubmit = event => actualizarRolOperador(event);
            agregarCampoOculto(roleForm, 'id', operador.id);
            const role = document.createElement('select');
            role.name = 'role';
            role.add(new Option('Operador', 'operador'));
            role.add(new Option('Administrador', 'admin'));
            role.add(new Option('Soporte de TI', 'soporte_ti'));
            role.value = operador.operator_role;
            role.setAttribute('aria-label', `Rol de ${operador.username}`);
            roleForm.appendChild(role);
            const roleButton = document.createElement('button');
            roleButton.type = 'submit';
            roleButton.textContent = 'Guardar rol';
            roleForm.appendChild(roleButton);
            row.appendChild(roleForm);

            const contact = document.createElement('div');
            contact.className = 'operator-controls';
            const email = document.createElement('input');
            email.type = 'email';
            email.maxLength = 254;
            email.required = true;
            email.value = operador.contacto || '';
            email.placeholder = 'Correo de recuperación';
            email.setAttribute('aria-label', `Correo de ${operador.username}`);
            contact.appendChild(email);
            const saveEmail = document.createElement('button');
            saveEmail.type = 'button';
            saveEmail.textContent = 'Guardar correo';
            saveEmail.onclick = () => guardarEmailOperador(operador.id, email.value);
            contact.appendChild(saveEmail);
            if (!operador.contacto_verified_at) {
                const verify = document.createElement('button');
                verify.type = 'button';
                verify.textContent = 'Enviar verificación';
                verify.onclick = () => enviarVerificacionCorreo(operador.id);
                contact.appendChild(verify);
            }
            row.appendChild(contact);

            const stateControls = document.createElement('div');
            stateControls.className = 'operator-controls';
            const stateSelect = document.createElement('select');
            stateSelect.setAttribute('aria-label', `Estado de ${operador.username}`);
            ['Activo', 'Inactivo', 'Vacaciones', 'Despedido'].forEach(value => stateSelect.add(new Option(value, value)));
            stateSelect.value = status;
            stateSelect.disabled = isSelf;
            const vacationStart = document.createElement('input');
            vacationStart.type = 'date';
            vacationStart.value = operador.vacation_start || '';
            vacationStart.setAttribute('aria-label', 'Inicio de vacaciones');
            const vacationEnd = document.createElement('input');
            vacationEnd.type = 'date';
            vacationEnd.value = operador.vacation_end || '';
            vacationEnd.setAttribute('aria-label', 'Fin de vacaciones');
            vacationStart.hidden = vacationEnd.hidden = stateSelect.value !== 'Vacaciones';
            stateSelect.onchange = () => {
                vacationStart.hidden = vacationEnd.hidden = stateSelect.value !== 'Vacaciones';
            };
            const saveStatus = document.createElement('button');
            saveStatus.type = 'button';
            saveStatus.textContent = 'Guardar estado';
            saveStatus.disabled = isSelf;
            saveStatus.title = isSelf ? 'No puedes cambiar el estado de tu propia cuenta.' : '';
            saveStatus.onclick = () => guardarEstadoOperador(operador.id, stateSelect.value, vacationStart.value, vacationEnd.value);
            stateControls.append(stateSelect, vacationStart, vacationEnd, saveStatus);
            row.appendChild(stateControls);

            const details = document.createElement('details');
            const summary = document.createElement('summary');
            summary.textContent = 'Editar datos del operador';
            details.appendChild(summary);
            const profileForm = document.createElement('form');
            profileForm.className = 'operator-profile-form';
            profileForm.onsubmit = event => guardarDatosOperador(event);
            agregarCampoOculto(profileForm, 'id', operador.id);
            [
                ['username', 'Usuario', operador.username],
                ['nombres', 'Nombres', operador.nombres],
                ['apellidos', 'Apellidos', operador.apellidos],
                ['ci', 'Cédula', operador.ci],
                ['tlf', 'Teléfono', operador.tlf],
                ['contacto', 'Correo', operador.contacto],
                ['direccion', 'Dirección', operador.direccion]
            ].forEach(([field, label, value]) => {
                const input = document.createElement('input');
                input.name = field;
                input.value = value || '';
                input.placeholder = label;
                input.setAttribute('aria-label', label);
                input.required = ['username', 'nombres', 'ci', 'tlf', 'contacto'].includes(field);
                if (field === 'contacto') input.type = 'email';
                if (field === 'tlf') input.type = 'tel';
                if (field === 'ci') {
                    input.inputMode = 'numeric';
                    input.pattern = '(?:[VEJGP]-?)?[0-9]{6,9}';
                    input.maxLength = 11;
                }
                profileForm.appendChild(input);
            });
            const saveProfile = document.createElement('button');
            saveProfile.type = 'submit';
            saveProfile.textContent = 'Guardar datos';
            profileForm.appendChild(saveProfile);
            details.appendChild(profileForm);
            row.appendChild(details);
            listaOperadores.appendChild(row);
        });
    } catch (error) {
        alert(error.message);
    }
}

async function cargarConfiguracionTecnica() {
    const form = document.getElementById('form-config-tecnica');
    const message = document.getElementById('mensaje-config-tecnica');
    if (!form || !message) return;

    try {
        const response = await fetch('api/configuracion_tecnica.php');
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo cargar la configuración técnica.');
        configuracionTecnicaEditable = data.editable === true;
        if (!configuracionTecnicaEditable) {
            const tab = document.getElementById('tab-config-tecnica');
            if (tab) tab.style.display = 'none';
            cargarVista('clientes');
            return;
        }

        Object.entries(data.config).forEach(([key, value]) => {
            if (form.elements[key]) form.elements[key].value = value ?? '';
        });
        form.elements.csrf_token.value = data.csrf_token;
        form.elements.DB_PASSWORD.value = '';
        form.elements.SMTP_PASSWORD.value = '';
        form.elements.current_password.value = '';
        form.elements.clear_db_password.checked = false;
        form.elements.clear_smtp_password.checked = false;
        form.querySelectorAll('input, select, button').forEach(control => {
            control.disabled = !data.editable;
        });
        message.textContent = data.message || 'Guarda con tu contraseña actual. Los secretos no se muestran; en blanco se conservan.';
        message.className = data.editable ? '' : 'notice error';
    } catch (error) {
        message.textContent = error.message;
        message.className = 'notice error';
    }
}

async function guardarConfiguracionTecnica(event) {
    event.preventDefault();
    const form = event.target;
    const message = document.getElementById('mensaje-config-tecnica');

    try {
        const response = await fetchConCsrf('api/configuracion_tecnica.php', {
            method: 'POST',
            body: new FormData(form)
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo guardar la configuración técnica.');

        form.elements.current_password.value = '';
        form.elements.DB_PASSWORD.value = '';
        form.elements.SMTP_PASSWORD.value = '';
        form.elements.clear_db_password.checked = false;
        form.elements.clear_smtp_password.checked = false;
        message.textContent = data.message;
        message.className = data.restart_required ? 'notice' : 'notice success';
    } catch (error) {
        message.textContent = error.message;
        message.className = 'notice error';
    }
}

function agregarCampoOculto(form, name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
}

async function guardarConfigForm(event, action) {
    event.preventDefault();
    const formData = new FormData(event.target);
    formData.set('action', action);
    const response = await fetchConCsrf('api/configuracion_api.php', { method: 'POST', body: formData });
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
        const response = await fetchConCsrf('api/configuracion_api.php', { method: 'POST', body: formData });
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
        const response = await fetchConCsrf('api/configuracion_api.php', { method: 'POST', body: formData });
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
        const response = await fetchConCsrf('api/configuracion_api.php', { method: 'POST', body: formData });
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

async function guardarEstadoOperador(id, estado, inicio, fin) {
    if (!window.confirm(`¿Quieres cambiar el estado de esta cuenta a "${estado}"?`)) return;
    const formData = new FormData();
    formData.set('action', 'estado_operador');
    formData.set('id', id);
    formData.set('estado', estado);
    formData.set('vacation_start', inicio);
    formData.set('vacation_end', fin);
    try {
        const response = await fetchConCsrf('api/configuracion_api.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo cambiar el estado de la cuenta.');
        await cargarConfiguracion();
    } catch (error) {
        alert(error.message);
    }
}

async function guardarDatosOperador(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    formData.set('action', 'datos_operador');
    try {
        const response = await fetchConCsrf('api/configuracion_api.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudieron guardar los datos.');
        await cargarConfiguracion();
        alert('Datos del operador actualizados.');
    } catch (error) {
        alert(error.message);
    }
}

async function cargarPerfilOperador() {
    try {
        const response = await fetch('api/perfil_operador.php');
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo cargar tu perfil.');
        if (!data.perfil) throw new Error('No se encontró tu perfil.');
        const perfil = data.perfil;
        const form = document.getElementById('form-perfil-operador');
        form.elements.contacto.value = perfil.contacto || '';
        form.elements.tlf.value = perfil.tlf || '';

        const datosLectura = {
            'perfil-operador-nombres': perfil.nombres,
            'perfil-operador-apellidos': perfil.apellidos,
            'perfil-operador-ci': perfil.ci,
            'perfil-operador-direccion': perfil.direccion,
            'perfil-operador-username': perfil.username,
            'perfil-operador-rol': ({
                admin: 'Administrador',
                operador: 'Operador',
                soporte_ti: 'Soporte de TI'
            })[perfil.operator_role] || perfil.operator_role,
            'perfil-operador-estado': perfil.operator_status || (Number(perfil.activo) === 1 ? 'Activo' : 'Inactivo'),
            'perfil-operador-vacaciones': perfil.vacation_start || perfil.vacation_end
                ? `${perfil.vacation_start || '—'} a ${perfil.vacation_end || '—'}`
                : 'No registradas'
        };
        Object.entries(datosLectura).forEach(([id, valor]) => {
            document.getElementById(id).textContent = valor || '—';
        });

        document.getElementById('estado-verificacion-correo').textContent = perfil.contacto_verificado
            ? 'verificado'
            : 'pendiente de verificación';
        document.querySelector('#form-cambio-clave [name="username"]').value = perfil.username || '';
    } catch (error) {
        alert(error.message);
    }
}

async function guardarPerfilOperador(event) {
    event.preventDefault();
    try {
        const response = await fetchConCsrf('api/perfil_operador.php', { method: 'POST', body: new FormData(event.target) });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo actualizar tu perfil.');
        alert(data.message);
        await cargarPerfilOperador();
    } catch (error) {
        alert(error.message);
    }
}

async function solicitarCambioClave(event) {
    event.preventDefault();
    const form = event.target;
    const boton = form.querySelector('button[type="submit"]');
    const estado = document.getElementById('estado-cambio-clave');
    boton.disabled = true;
    estado.hidden = false;
    estado.setAttribute('role', 'status');
    estado.textContent = '';

    try {
        const response = await fetchConCsrf('api/solicitar_recuperacion.php', {
            method: 'POST',
            body: new FormData(form)
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'No se pudo solicitar el enlace.');
        }
        estado.textContent = data.message;
    } catch (error) {
        estado.setAttribute('role', 'alert');
        estado.textContent = error.message || 'No se pudo procesar la solicitud.';
    } finally {
        boton.disabled = false;
    }
}

async function cargarAuditoria(pagina) {
    paginaActualAuditoria = pagina;
    try {
        const response = await fetch(`api/auditoria.php?pagina=${pagina}`);
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo cargar el historial.');
        const tbody = document.querySelector('#tabla-auditoria tbody');
        tbody.replaceChildren();
        data.data.forEach(log => {
            const row = document.createElement('tr');
            [log.created_at, log.actor_username, log.action, `${log.entity_type}${log.entity_id ? ` #${log.entity_id}` : ''}`, JSON.stringify(log.changes || {})].forEach(value => {
                const cell = document.createElement('td');
                cell.textContent = value || '';
                row.appendChild(cell);
            });
            tbody.appendChild(row);
        });
        if (!data.data.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 5;
            cell.textContent = 'Aún no hay actividad registrada.';
            row.appendChild(cell);
            tbody.appendChild(row);
        }
        const pagination = document.getElementById('paginacion-auditoria');
        pagination.replaceChildren();
        const previous = document.createElement('button');
        previous.type = 'button';
        previous.textContent = 'Anterior';
        previous.disabled = pagina <= 1;
        previous.onclick = () => cargarAuditoria(pagina - 1);
        const label = document.createElement('span');
        label.textContent = `Página ${data.pagina} de ${Math.max(1, data.paginas)}`;
        const next = document.createElement('button');
        next.type = 'button';
        next.textContent = 'Siguiente';
        next.disabled = pagina >= data.paginas;
        next.onclick = () => cargarAuditoria(pagina + 1);
        pagination.append(previous, label, next);
    } catch (error) {
        alert(error.message);
    }
}

async function registrarOperador(event) {
    event.preventDefault();
    try {
        const response = await fetchConCsrf('api/register.php', { method: 'POST', body: new FormData(event.target) });
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
        const response = await fetchConCsrf('api/actualizar_cliente.php', { method: 'POST', body: new FormData(event.target) });
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
    establecerFechaPagoHoy();
    moverControlTema(false);
    establecerTemaUsuario('');
    document.getElementById('theme-toggle')?.addEventListener('click', alternarTema);

    fetch('api/check_session.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const loginPage = document.getElementById('loginpage');
                const dashboard = document.getElementById('dashboard');
                const nombreActor = document.getElementById('nombre-actor');

                if (loginPage) loginPage.style.display = 'none';
                if (dashboard) dashboard.style.display = 'block';
                sessionStorage.setItem('sesion_activa', 'true');
                establecerTemaUsuario(data.operador);
                moverControlTema(true);

                if (nombreActor) {
                    const actorGuardado = sessionStorage.getItem('actorVIP');
                    nombreActor.innerText = actorGuardado ? actorGuardado.toUpperCase() : (data.operador ? data.operador.toUpperCase() : 'SISTEMA');
                }
                const role = data.role || 'operador';
                sessionStorage.setItem('operador_role', role);
                configurarPestanasRol(role);
                cargarVista('clientes');
                cargarResumenDashboard();
            } else {
                sessionStorage.removeItem('sesion_activa');
                moverControlTema(false);
                toggleLogin('login');
            }
        })
        .catch(error => {
            console.error('Error validando la sesión:', error);
            moverControlTema(false);
            toggleLogin('login');
        });
});

async function cerrarSesionEscenario() {
    try {
        const response = await fetchConCsrf('api/logout.php', { method: 'POST' });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'No se pudo cerrar la sesión.');
        }
    } catch (error) {
        alert(error.message || 'No se pudo cerrar la sesión en el servidor.');
        return;
    }

    sessionStorage.removeItem('sesion_activa');
    sessionStorage.removeItem('operador_role');
    sessionStorage.removeItem('actorVIP');
    window.location.href = 'index.php';
}