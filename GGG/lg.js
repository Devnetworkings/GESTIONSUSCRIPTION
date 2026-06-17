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
async function cargarTablaClientes() {
    try {
        const response = await fetch('leer_clientes.php').then(r => r.text());
        const res = JSON.parse(response.trim());

        if (res.success) {
            const tbody = document.querySelector('#tabla-clientes tbody');
            tbody.innerHTML = ''; // Limpiamos el set

            const clientes = res.data;
            const filasTotales = 10; // Exigencia del director: 10 filas fijas

            for (let i = 0; i < filasTotales; i++) {
                const tr = document.createElement('tr');

                if (clientes[i]) {
                    // Si el cliente existe, lo ponemos en pantalla
                    const c = clientes[i];
                    tr.innerHTML = `
                        <td><strong>${c.ci}</strong></td>
                        <td>${c.nombres} ${c.apellidos}</td>
                        <td>${c.tlf}</td>
                        <td><span style="color: ${c.estatus === 'Activo' ? '#10b981' : '#ef4444'}; font-weight: bold;">${c.estatus}</span></td>
                    `;
                } else {
                    // Si no hay cliente, ponemos una fila vacía y limpia
                    tr.innerHTML = `
                        <td style="color: #cbd5e1;">---</td>
                        <td style="color: #cbd5e1;">---</td>
                        <td style="color: #cbd5e1;">---</td>
                        <td style="color: #cbd5e1;">---</td>
                    `;
                }
                tbody.appendChild(tr);
            }
        }
    } catch (error) {
        console.error("Falla en postproducción:", error);
    }
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
async function cargarTablaPagos() {
    try {
        const response = await fetch('leer_pagos.php').then(r => r.text());
        const res = JSON.parse(response.trim());

        if (res.success) {
            const tbody = document.querySelector('#tabla-pagos tbody');
            tbody.innerHTML = '';

            const pagos = res.data;
            const filasTotales = 10;

            for (let i = 0; i < filasTotales; i++) {
                const tr = document.createElement('tr');

                if (pagos[i]) {
                    const p = pagos[i];
                    tr.innerHTML = `
                        <td>${p.nombres} ${p.apellidos} <br><small style="color:#64748b;">C.I: ${p.ci}</small></td>
                        <td><strong>${p.reference}</strong></td>
                        <td style="color: #10b981; font-weight: bold; font-size: 1.1em;">${p.monto}</td>
                        <td><span style="background: #e2e8f0; padding: 4px 8px; border-radius: 6px; font-weight: bold;">${p.coin}</span></td>
                    `;
                } else {
                    tr.innerHTML = `
                        <td style="color: #cbd5e1;">---</td>
                        <td style="color: #cbd5e1;">---</td>
                        <td style="color: #cbd5e1;">---</td>
                        <td style="color: #cbd5e1;">---</td>
                    `;
                }
                tbody.appendChild(tr);
            }
        }
    } catch (error) {
        console.error("Falla en postproducción (Pagos):", error);
    }
}