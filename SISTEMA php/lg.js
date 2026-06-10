async function procesarFormulario(event, url, callbackExito) {
    event.preventDefault();
    const res = await fetch(url, { method: 'POST', body: new FormData(event.target) }).then(r => r.json());
    if (res.success) { callbackExito(res); event.target.reset(); }
    else { alert("Error: " + res.message); }
}

function cargarVista(nombreVista) {
    const content = document.getElementById('content');
    const formCliente = document.getElementById('form-cliente');
    const addpay = document.getElementById('addpay');

    // 1. Ocultamos todo primero
    if (formCliente) formCliente.style.display = 'none';
    if (addpay) addpay.style.display = 'none';

    // 2. Mostramos solo lo que toca
    if (nombreVista === 'clientes' && formCliente) {
        formCliente.style.display = 'block';
    }
    else if (nombreVista === 'pagos' && addpay) {
        addpay.style.display = 'block';
    }
}

function toggleLogin(vista) {
    document.getElementById('formu').style.display = (vista === 'register') ? 'none' : 'flex';
    document.getElementById('form-register').style.display = (vista === 'register') ? 'flex' : 'none';
}