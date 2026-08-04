async function procesarFormulario(event, url, callbackExito) {
    event.preventDefault();

    const form = event.target;
    const boton = form.querySelector('input[type="submit"], button');
    const textoOriginal = boton ? (boton.value || boton.innerText) : '';

    if (boton) {
        boton.disabled = true;
        boton.style.opacity = '0.7';
        boton.value ? boton.value = "Procesando... 🎬" : boton.innerText = "Procesando... 🎬";
    }

    try {
        // 1. Traemos la respuesta cruda
        const rawResponse = await fetch(url, { method: 'POST', body: new FormData(form) }).then(r => r.text());

        let res;
        try {
            // 2. Limpiamos espacios fantasma con trim() antes de leer
            res = JSON.parse(rawResponse.trim());
        } catch (parseError) {
            console.error("🔥 BASURA INVISIBLE EN PHP 🔥:\n", rawResponse);
            alert("¡Corte! PHP devolvió algo que no es JSON puro. Revisa la consola.");
            return; // Detenemos la escena aquí mismo
        }

        // 3. Si llegamos aquí, el JSON es puro y perfecto
        if (res.success) {
            callbackExito(res);
            form.reset();
            // Ocultamos el campo dinámico de referencia/serie para que quede limpio
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
        // Restauramos al actor principal
        if (boton) {
            boton.disabled = false;
            boton.style.opacity = '1';
            boton.value ? boton.value = textoOriginal : boton.innerText = textoOriginal;
        }
    }
}

function cargarVista(nombreVista) {
    const vistaClientes = document.getElementById('vista-clientes');
    const vistaPagos = document.getElementById('vista-pagos'); // 🔥 NUEVO CONTENEDOR 🔥

    // Apagamos todas las luces
    if (vistaClientes) vistaClientes.style.display = 'none';
    if (vistaPagos) vistaPagos.style.display = 'none';

    // Encendemos solo el set solicitado
    if (nombreVista === 'clientes' && vistaClientes) {
        vistaClientes.style.display = 'flex';
        cargarTablaClientes();
    }
    else if (nombreVista === 'pagos' && vistaPagos) {
        vistaPagos.style.display = 'flex';
        cargarTablaPagos(); // ¡Llamamos a la nueva cartelera!
    }
}
function toggleLogin(vista) {
    document.getElementById('formu').style.display = (vista === 'register') ? 'none' : 'flex';
    document.getElementById('form-register').style.display = (vista === 'register') ? 'flex' : 'none';
}

function adaptarFormularioPago(metodo) {
    const contenedor = document.getElementById('contenedor-dinamico');
    const input = document.getElementById('input-dinamico');

    if (metodo === 'divisa') {
        // Transformación para efectivo extranjero
        contenedor.style.display = 'block';
        input.placeholder = 'Número de Serie del Billete (o "EFECTIVO")';
        input.required = true;
    } else if (metodo === 'pagomovil' || metodo === 'transferencia') {
        // Transformación para transacciones bancarias nacionales
        contenedor.style.display = 'block';
        input.placeholder = 'Número de Referencia Bancaria';
        input.required = true;
    } else {
        // Si no hay nada seleccionado, se esconde tras bambalinas
        contenedor.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}

//NUEVA FUNCION  para el boton de cliente nuevo 
// 🎬 NUEVA: Función para el botón que cambia de color y texto
function toggleFormularioCliente() {
    const form = document.getElementById('form-cliente');
    const boton = document.querySelector('.btn-nuevo'); // Atrapamos al actor principal

    if (form.style.display === 'none' || form.style.display === '') {
        // ACCIÓN: El formulario entra a escena
        form.style.display = 'grid';
        boton.innerHTML = '- Cerrar Formulario'; // Cambiamos el libreto
        boton.classList.add('btn-cerrar'); // Le ponemos el traje rojo
    } else {
        // CORTE: El formulario sale de escena
        form.style.display = 'none';
        boton.innerHTML = '+ Nuevo Cliente'; // Vuelve al libreto original
        boton.classList.remove('btn-cerrar'); // Le quitamos el traje rojo
    }
}

// 🎬 NUEVA: Función que inyecta EXACTAMENTE 10 filas
function cargarTablaClientes() {
    // 1. Intentamos leer qué está buscando el operador (si los campos existen en el HTML)
    const inputBusqueda = document.getElementById('input-busqueda');
    const selectEstatus = document.getElementById('filtro-estatus');

    // Si no han escrito nada o los campos aún no cargan, buscamos texto vacío
    const query = inputBusqueda ? inputBusqueda.value : '';
    const estatus = selectEstatus ? selectEstatus.value : '';

    const tbody = document.querySelector('#tabla-clientes tbody');

    // 2. ¡Llamamos a tu archivo PHP pasándole los filtros!
    fetch(`buscar_clientes.php?q=${encodeURIComponent(query)}&estatus=${encodeURIComponent(estatus)}`)
        .then(response => response.json())
        .then(data => {
            tbody.innerHTML = '';

            if (data.success && data.data.length > 0) {
                data.data.forEach(cliente => {
                    const tr = document.createElement('tr');

                    // --- EFECTOS ESPECIALES PARA EL CLIC ---
                    tr.style.cursor = 'pointer';
                    tr.onmouseover = () => tr.style.backgroundColor = '#f1f5f9';
                    tr.onmouseout = () => tr.style.backgroundColor = 'transparent';

                    // ¡Aquí conectamos la fila con el Modal del Expediente!
                    tr.onclick = () => abrirPerfilCliente(cliente.id);
                    // ----------------------------------------

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
// 🎬 NUEVA: Control del Botón de Pagos
function toggleFormularioPago() {
    const formContainer = document.getElementById('addpay');
    const boton = document.getElementById('btn-nuevo-pago');

    if (formContainer.style.display === 'none' || formContainer.style.display === '') {
        formContainer.style.display = 'block';
        boton.innerHTML = '- Cancelar Pago';
        boton.classList.add('btn-cerrar'); // Se pone el traje rojo
    } else {
        formContainer.style.display = 'none';
        boton.innerHTML = '+ Nuevo Pago';
        boton.classList.remove('btn-cerrar'); // Vuelve al verde
    }
}

// 🎬 NUEVA: Inyectar 10 filas de historial de pagos
// NUEVA VERSIÓN: Cargar la tabla de pagos con búsqueda dinámica
// NUEVA VERSIÓN: Cargar la tabla de pagos con búsqueda dinámica
function cargarTablaPagos() {
    const inputBusqueda = document.getElementById('input-busqueda-pagos');
    const query = inputBusqueda ? inputBusqueda.value : '';

    const tbody = document.querySelector('#tabla-pagos tbody');

    fetch(`buscar_pagos.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            tbody.innerHTML = '';

            // Si el backend falla, nos lo gritará en pantalla
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
                        <td style="font-size: 0.9em; color: #555;">${fechaAmigable}</td>
                        <td>
                            ${pago.nombres} ${pago.apellidos}<br>
                            <small style="color: gray;">C.I: ${pago.ci}</small>
                        </td>
                        <td style="font-weight: bold;">${pago.reference}</td>
                        <td style="color: ${pago.coin === 'USD' ? 'green' : '#0056b3'}; font-weight: bold;">
                            ${pago.monto} ${pago.coin}
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

// Muestra el menú desplegable mientras escribes
function sugerirClientes(query) {
    const caja = document.getElementById('sugerencias-clientes');

    // Si hay menos de 2 letras, ocultamos el menú
    if (query.length < 2) {
        caja.style.display = 'none';
        return;
    }

    // Buscamos a los actores en la base de datos
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

                    // Cuando le haces clic, llama a la función de relleno
                    div.onclick = () => seleccionarClienteParaPago(cliente);

                    caja.appendChild(div);
                });
                caja.style.display = 'block';
            } else {
                caja.style.display = 'none';
            }
        });
}

// VERSIÓN MEJORADA: Rellena todo el formulario automáticamente
function seleccionarClienteParaPago(cliente) {
    document.getElementById('buscador-cliente-pago').value = `${cliente.nombres} ${cliente.apellidos}`;

    // Autocompletamos la cédula
    const inputCedula = document.querySelector('input[name="cedula_identidad"]');
    const selectTipoDoc = document.querySelector('select[name="tipo_documento"]');

    if (cliente.ci.includes('-')) {
        const partes = cliente.ci.split('-');
        selectTipoDoc.value = partes[0];
        inputCedula.value = partes[1];
    } else {
        inputCedula.value = cliente.ci;
    }

    // EL ARREGLO DEL TELÉFONO: Limpiamos y aseguramos que el prefijo exista
    const inputTlf = document.querySelector('input[name="telefono"]');
    const selectCod = document.querySelector('select[name="codigo_operadora"]');

    if (cliente.tlf) {
        // Quitamos cualquier espacio, guion o letra atravesada
        let tlfLimpio = cliente.tlf.replace(/\D/g, '');

        if (tlfLimpio.length >= 11) {
            let prefijo = tlfLimpio.substring(0, 4);
            let numero = tlfLimpio.substring(4);

            // Verificamos si el prefijo realmente existe en tu select (ej: 0412, 0212)
            let existePrefijo = Array.from(selectCod.options).some(opt => opt.value === prefijo);

            if (existePrefijo) {
                selectCod.value = prefijo;
                inputTlf.value = numero;
            } else {
                // Si es un prefijo raro, lo dejamos vacío pero ponemos el número completo
                selectCod.value = "";
                inputTlf.value = tlfLimpio;
            }
        } else {
            selectCod.value = "";
            inputTlf.value = tlfLimpio;
        }
    } else {
        selectCod.value = "";
        inputTlf.value = "";
    }

    document.getElementById('sugerencias-clientes').style.display = 'none';
}

// NUEVA FUNCIÓN para cargar la tabla de pagos con Fecha y Operador
function cargarTablaPagos() {
    const tbody = document.querySelector('#tabla-pagos tbody');

    fetch('buscar_pagos.php')
        .then(response => response.json())
        .then(data => {
            tbody.innerHTML = '';
            if (data.success && data.data.length > 0) {
                data.data.forEach(pago => {
                    const tr = document.createElement('tr');

                    // Formateamos la fecha para que se vea hermosa (opcional)
                    const fechaAmigable = new Date(pago.fecha).toLocaleString('es-VE', {
                        day: '2-digit', month: '2-digit', year: 'numeric',
                        hour: '2-digit', minute: '2-digit'
                    });

                    tr.innerHTML = `
                        <td style="font-size: 0.9em; color: #555;">${fechaAmigable}</td>
                        <td>
                            ${pago.nombres} ${pago.apellidos}<br>
                            <small style="color: gray;">C.I: ${pago.ci}</small>
                        </td>
                        <td style="font-weight: bold;">${pago.reference}</td>
                        <td style="color: ${pago.coin === 'USD' ? 'green' : '#0056b3'}; font-weight: bold;">
                            ${pago.monto} ${pago.coin}
                        </td>
                        <td style="text-transform: uppercase; font-size: 0.9em;">
                            👤 ${pago.nombre_operador}
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No hay transacciones registradas.</td></tr>';
            }
        });
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

                // Rellenamos el guion del modal
                document.getElementById('perfil-nombre').innerText = `🎭 ${c.nombres} ${c.apellidos}`;
                document.getElementById('perfil-ci').innerText = c.ci;
                document.getElementById('perfil-tlf').innerText = c.tlf || 'No registrado';
                document.getElementById('perfil-email').innerText = c.email || 'No registrado';
                document.getElementById('perfil-direccion').innerText = c.direccion || 'No registrada';

                // Estatus con colores de semáforo
                const estatusEl = document.getElementById('perfil-estatus');
                estatusEl.innerText = c.estatus;
                if (c.estatus === 'Activo') estatusEl.style.color = 'green';
                else if (c.estatus === 'Deuda') estatusEl.style.color = 'red';
                else estatusEl.style.color = 'gray';

                // Fecha de vencimiento bonita
                document.getElementById('perfil-vencimiento').innerText = c.fecha_vencimiento || 'Sin registro';

                // Preparamos la paginación de pagos
                pagosActualesPerfil = data.pagos;
                paginaActualPagos = 1;
                renderizarTablaPagosPerfil();

                // ¡Luces, cámara, modal!
                document.getElementById('modal-perfil-cliente').style.display = 'flex';
            } else {
                alert(data.message);
            }
        })
        .catch(err => console.error("Error cargando el expediente:", err));
}

function cerrarPerfilCliente() {
    document.getElementById('modal-perfil-cliente').style.display = 'none';
}

function renderizarTablaPagosPerfil() {
    const tbody = document.querySelector('#tabla-perfil-pagos tbody');
    tbody.innerHTML = '';

    // Calculamos qué pedazo de la cinta vamos a mostrar (paginación)
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
    contenedor.innerHTML = '';

    const totalPaginas = Math.ceil(pagosActualesPerfil.length / limitePorPagina);

    // Si solo hay 1 página (o ninguna), no mostramos botones
    if (totalPaginas <= 1) return;

    for (let i = 1; i <= totalPaginas; i++) {
        const btn = document.createElement('button');
        btn.innerText = i;
        btn.style.padding = '5px 12px';
        btn.style.border = '1px solid #cbd5e1';
        btn.style.borderRadius = '4px';
        btn.style.cursor = 'pointer';

        // Estilo del botón activo vs inactivo
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
            renderizarTablaPagosPerfil(); // ¡Cambio de escena dinámico!
        };

        contenedor.appendChild(btn);
    }
}