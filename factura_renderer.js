// nandodm/servinet/ServiNet-2722301d95f257ef5efb6c73b69eff2ce58b6f55/assets/js/factura_renderer.js

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const citaId = urlParams.get('cita_id');
    
    const loadingState = document.getElementById('loading-state');
    const invoiceContent = document.getElementById('invoice-content');
    const errorState = document.getElementById('error-state');
    const errorMessage = document.getElementById('error-message');

    if (!citaId) {
        if(loadingState) loadingState.classList.add('hidden');
        if(errorState) {
            errorMessage.textContent = 'Falta el ID de la cita para generar la factura.';
            errorState.classList.remove('hidden');
        }
        return;
    }

    async function loadFacturaData() {
        if(loadingState) loadingState.classList.remove('hidden');
        if(invoiceContent) invoiceContent.classList.add('hidden');

        try {
            const response = await fetch(`/ServiNet/api/get_factura_data.php?cita_id=${citaId}`);
            const result = await response.json();

            if (result.success && result.data) {
                const data = result.data;
                
                // 1. Cabecera y Datos Generales
                document.getElementById('factura-id').textContent = data.factura_id;
                document.getElementById('fecha-emision').textContent = data.fecha_emision;
                document.getElementById('estado-pago').textContent = data.estado_pago.toUpperCase();
                
                // 2. Datos del Cliente
                document.getElementById('cliente-nombre').textContent = data.cliente.nombre;
                document.getElementById('cliente-email').textContent = data.cliente.correo;
                document.getElementById('cliente-telefono').textContent = data.cliente.telefono;
                
                // 3. Datos del Profesional
                document.getElementById('prof-nombre').textContent = data.profesional.nombre;
                document.getElementById('prof-categoria').textContent = data.profesional.especialidades;
                document.getElementById('prof-municipio').textContent = 'Puebla, México'; // Dato simulado si no viene de la API
                
                // 4. Detalle de Servicio
                const detalleBody = document.getElementById('detalle-servicio-body');
                detalleBody.innerHTML = `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">${data.servicio.descripcion}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">$${data.servicio.tarifa_base.toFixed(2)} MXN</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right font-bold">$${data.servicio.monto_pagado.toFixed(2)} MXN</td>
                    </tr>
                `;
                
                // 5. Totales
                document.getElementById('subtotal-amount').textContent = `$${data.totales.subtotal.toFixed(2)} MXN`;
                document.getElementById('comision-amount').textContent = `-$${data.totales.comision_servinet.toFixed(2)} MXN`;
                document.getElementById('total-final-amount').textContent = `$${data.totales.total_final.toFixed(2)} MXN`;

                if(loadingState) loadingState.classList.add('hidden');
                if(invoiceContent) invoiceContent.classList.remove('hidden');

            } else {
                if(loadingState) loadingState.classList.add('hidden');
                if(errorState) {
                    errorMessage.textContent = result.message || 'El servicio no está marcado como PAGADO.';
                    errorState.classList.remove('hidden');
                }
            }
        } catch (error) {
            console.error('Factura Error:', error);
            if(loadingState) loadingState.classList.add('hidden');
            if(errorState) {
                errorMessage.textContent = 'Error de conexión con el servidor. Verifique la API.';
                errorState.classList.remove('hidden');
            }
        }
    }

    loadFacturaData();
});