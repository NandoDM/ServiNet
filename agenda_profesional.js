// nandodm/servinet/ServiNet-e7a913c5251aedd806317b56fa60d612c9d7bd2f/assets/js/agenda_profesional.js

function getStatusBadge(status) {
    let statusText = status.charAt(0).toUpperCase() + status.slice(1);
    let colorClass = 'status-pending bg-yellow-100 text-yellow-800 border-yellow-500'; // Default
    
    if (status === 'confirmada') {
        colorClass = 'status-confirmed bg-blue-100 text-blue-800 border-blue-500';
    } else if (status === 'cancelada') {
        colorClass = 'status-cancelled bg-red-100 text-red-800 border-red-500';
    } else if (status === 'finalizada') {
         colorClass = 'status-completed bg-green-100 text-green-800 border-green-500';
         statusText = 'Finalizada';
    }
    
    // Devolvemos el HTML con las clases necesarias de styles.css
    return `<span class="text-xs font-semibold px-3 py-1 rounded-full ${colorClass.split(' ').slice(2).join(' ')}">${statusText}</span>`;
}

function renderAgenda(agenda) {
    const listContainer = document.getElementById('citas-list-container');
    if (!listContainer) return;

    listContainer.innerHTML = '';
    
    if (agenda.length === 0) {
        listContainer.innerHTML = '<div class="bg-white p-6 rounded-lg shadow-md text-center text-gray-500">No tienes servicios agendados o solicitudes pendientes.</div>';
        return;
    }

    agenda.forEach(item => {
        const isPending = item.estado === 'pendiente';
        const isCompleted = item.estado === 'finalizada';
        const isConfirmed = item.estado === 'confirmada';
        
        // Determinar color de la barra lateral (basado en Tailwind en styles.css)
        let borderColorClass = '';
        if (isCompleted) {
            borderColorClass = 'border-l-green-500';
        } else if (item.estado === 'confirmada') {
            borderColorClass = 'border-l-blue-500';
        } else if (item.estado === 'pendiente') {
            borderColorClass = 'border-l-yellow-500';
        } else if (item.estado === 'cancelada') {
            borderColorClass = 'border-l-red-500';
        }

        const chatLink = `<a href="/ServiNet/pages/chat-interfaz.html?client=${item.cliente_id}" class="text-sm professional-color hover:underline mr-4">Mensajear</a>`;
        
        let actionsHtml = '';
        
        if (isPending) {
            actionsHtml = `
                ${chatLink}
                <button onclick="handleAgendaAction(${item.id_cita}, 'confirmar')" class="bg-green-600 text-white py-2 px-4 text-sm rounded-md hover:bg-green-700 transition">Aceptar Cita</button>
                <button onclick="handleAgendaAction(${item.id_cita}, 'rechazar')" class="text-sm text-red-600 hover:underline ml-3">Rechazar</button>
            `;
        } else if (isConfirmed) {
            // Cita Confirmada: Finalizar, Cancelar Y Reportar Disputa
            actionsHtml = `
                ${chatLink}
                <button onclick="handleAgendaAction(${item.id_cita}, 'finalizar')" class="bg-blue-600 text-white py-2 px-4 text-sm rounded-md hover:bg-blue-700 transition">Finalizar Servicio</button>
                <button onclick="handleAgendaAction(${item.id_cita}, 'cancelar')" class="text-sm text-red-600 hover:underline ml-3">Cancelar</button>
                <button onclick="handleProfDispute(${item.id_cita}, 'profesional')" class="text-sm text-red-600 hover:underline ml-3">Reportar Disputa</button>
            `;
        } else if (isCompleted) { 
            // Cita Finalizada: Reportar Disputa (si el cliente no liberó el pago)
            actionsHtml = `
                ${chatLink}
                <span class="text-sm text-gray-500 mr-4">Servicio Finalizado.</span>
                <button onclick="handleProfDispute(${item.id_cita}, 'profesional')" class="text-sm text-red-600 hover:underline">Reportar Disputa</button>
            `;
        } else { // cancelada, etc.
             actionsHtml = `
                ${chatLink}
                <span class="text-sm text-red-500">Acción Finalizada.</span>
            `;
        }

        const cardHtml = `
            <div class="p-4 rounded-lg shadow-md border-l-4 ${borderColorClass} mb-4 hover:shadow-lg transition bg-white">
                <div class="flex justify-between items-center mb-2">
                    <p class="text-lg font-semibold text-gray-800">${isPending ? 'Solicitud' : 'Cita'}: ${item.servicio}</p>
                    ${getStatusBadge(item.estado)}
                </div>
                <div class="text-sm text-gray-600 space-y-1">
                    <p><i class="fas fa-calendar-day mr-2 text-blue-500"></i> <strong>Fecha/Hora:</strong> ${item.fecha_hora}</p>
                    <p><i class="fas fa-user-circle mr-2 text-blue-500"></i> <strong>Cliente:</strong> ${item.cliente_nombre}</p>
                    <p><i class="fas fa-dollar-sign mr-2 text-blue-500"></i> <strong>Monto (Est.):</strong> $${item.monto} MXN</p>
                    ${item.comentario_cliente ? `<p><i class="fas fa-comment mr-2 text-blue-500"></i> <strong>Comentario:</strong> ${item.comentario_cliente.substring(0, 50)}...</p>` : ''}
                </div>
                <div class="mt-3 text-right">
                    ${actionsHtml}
                </div>
            </div>
        `;
        listContainer.innerHTML += cardHtml;
    });
}

// 🔑 Manejador para Aceptar/Rechazar/Finalizar (Llama a la API)
window.handleAgendaAction = async function(citaId, action) {
    let confirmMsg = '';
    if (action === 'confirmar') {
        confirmMsg = `¿Deseas confirmar la Cita #${citaId} con el cliente? Al aceptar, el cliente será notificado.`;
    } else if (action === 'rechazar') {
        confirmMsg = `¿Deseas rechazar la Solicitud #${citaId}? Esta acción es irreversible y liberará los fondos al cliente.`;
    } else if (action === 'cancelar') {
        confirmMsg = `¿Estás seguro de cancelar la Cita #${citaId}? Esto podría afectar tu calificación.`;
    } else if (action === 'finalizar') {
        confirmMsg = `¿Confirmas que el Servicio #${citaId} ha sido COMPLETADO? Esto notificará al cliente para que libere el pago.`;
    } else {
        return;
    }

    if (confirm(confirmMsg)) {
        
        const formData = new FormData();
        formData.append('cita_id', citaId);
        formData.append('action', action);
        
        try {
            const response = await fetch('/ServiNet/api/update_cita_status.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                alert(`✅ Cita #${citaId} actualizada a: ${result.new_status}.`);
            } else {
                alert(`❌ Error al actualizar la cita: ${result.message}`);
            }
        } catch (error) {
            console.error("Error de conexión:", error);
            alert('❌ Error de conexión con el servidor.');
        }

        // Recargar la lista después de la acción
        loadProfessionalAgenda();
    }
}

// 🔑 NUEVO MANEJADOR PARA DISPUTAS DEL PROFESIONAL
window.handleProfDispute = function(citaId, role) {
    const disputeModal = document.getElementById('dispute-modal');
    const form = document.getElementById('dispute-form');
    
    // Asume que el modal de disputa ya fue inyectado en agenda.html
    if (disputeModal) {
        document.getElementById('dispute-cita-id').textContent = `#${citaId}`;
        document.getElementById('dispute-target-cita-id').value = citaId;
        document.getElementById('dispute-iniciada-por').value = role;

        disputeModal.classList.remove('hidden');
        disputeModal.classList.add('flex');
        document.getElementById('dispute-message').classList.add('hidden');
        form.reset();
    } else {
         alert('Error: El modal de disputa no se ha cargado en la página.');
    }
};


async function loadProfessionalAgenda() {
    const container = document.getElementById('citas-list-container');
    if (!container) return;
    
    container.innerHTML = '<div class="bg-white p-6 rounded-lg shadow-md text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando citas y solicitudes...</div>';

    try {
        const response = await fetch('/ServiNet/api/get_profesional_agenda.php');
        const result = await response.json();

        if (result.success) {
            // Ordenar para mostrar pendientes y confirmadas primero
            const sortedAgenda = result.agenda.sort((a, b) => {
                const order = ['pendiente', 'confirmada', 'finalizada', 'cancelada'];
                return order.indexOf(a.estado) - order.indexOf(b.estado);
            });
            
            renderAgenda(sortedAgenda);

        } else {
            container.innerHTML = `<div class="bg-white p-6 rounded-lg shadow-md text-center text-red-500">Error al cargar la agenda: ${result.message}</div>`;
        }

    } catch (error) {
        console.error('Error de conexión con la API de Agenda:', error);
        container.innerHTML = `<div class="bg-white p-6 rounded-lg shadow-md text-center text-red-500">Error de conexión con el servidor.</div>`;
    }
}

// Inicialización del script
document.addEventListener('DOMContentLoaded', loadProfessionalAgenda);