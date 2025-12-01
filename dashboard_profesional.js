// nandodm/servinet/ServiNet-e7a913c5251aedd806317b56fa60d612c9d7bd2f/assets/js/dashboard_profesional.js

// Función reutilizada para el badge de estado
function getStatusBadge(status) {
    let statusText = status.charAt(0).toUpperCase() + status.slice(1);
    let colorClass = 'status-pending bg-yellow-100 text-yellow-800'; // Default
    
    if (status === 'confirmada') {
        colorClass = 'status-confirmed bg-blue-100 text-blue-800';
    } else if (status === 'cancelada') {
        colorClass = 'status-cancelled bg-red-100 text-red-800';
    } else if (status === 'finalizada') {
         colorClass = 'status-completed bg-green-100 text-green-800';
    }
    
    return `<span class="${colorClass} text-xs font-semibold px-3 py-1 rounded-full">${statusText}</span>`;
}

function renderAppointments(appointments) {
    const container = document.getElementById('latest-appointments-content');
    if (!container) return;

    if (appointments.length === 0) {
        container.innerHTML = '<div class="p-4 bg-gray-50 rounded-lg text-gray-500 text-center">¡Genial! No tienes citas pendientes.</div>';
        return;
    }

    let html = `
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cita #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Servicio / Cliente</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha / Hora</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
    `;

    appointments.forEach(app => {
        html += `
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${app.id_cita}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                    <p class="font-semibold">${app.servicio}</p>
                    <p class="text-xs text-gray-500">Cliente: ${app.cliente}</p>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${app.fecha_hora}</td>
                <td class="px-6 py-4 whitespace-nowrap">${getStatusBadge(app.estado)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <a href="/ServiNet/pages/dashboard/profesional/agenda.html" class="text-blue-600 hover:text-blue-900 mx-1">Gestionar</a>
                </td>
            </tr>
        `;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}


async function loadProfessionalDashboardData() {
    try {
        // La API es compartida entre index y agenda para los KPIs
        const response = await fetch('/ServiNet/api/get_profesional_dashboard_data.php');
        const result = await response.json();

        if (result.success) {
            const data = result.data;

            // 1. Llenar KPIs
            const userNameDisplay = document.getElementById('dashboard-user-name');
            if(userNameDisplay) {
                 userNameDisplay.textContent = data.nombre_usuario.split(' ')[0];
            }
            
            document.getElementById('citas-pendientes-count').textContent = data.citas_pendientes;
            document.getElementById('fondos-liberar-amount').textContent = `$${data.fondos_retenidos} MXN`;
            document.getElementById('calificacion-promedio-display').textContent = `${data.calificacion}/5.0`;

            // 2. Renderizar citas (Solo para Index)
            renderAppointments(data.latest_appointments);

        } else {
            console.error('Error del backend:', result.message);
            // Mostrar error en los contenedores
            document.getElementById('latest-appointments-content').innerHTML = `<p class="text-red-500">Error al cargar datos: ${result.message}</p>`;
            document.getElementById('citas-pendientes-count').innerHTML = '❌';
            document.getElementById('fondos-liberar-amount').innerHTML = '❌';
            document.getElementById('calificacion-promedio-display').innerHTML = '❌';
        }

    } catch (error) {
        console.error('Error de conexión con la API del dashboard:', error);
        document.getElementById('latest-appointments-content').innerHTML = `<p class="text-red-500">Error de conexión con el servidor.</p>`;
        document.getElementById('citas-pendientes-count').innerHTML = '❌';
        document.getElementById('fondos-liberar-amount').innerHTML = '❌';
        document.getElementById('calificacion-promedio-display').innerHTML = '❌';
    }
}

// Ejecutar la carga de datos al cargar el DOM
document.addEventListener('DOMContentLoaded', function() {
    // Solo cargamos si estamos en el Index
    if (window.location.pathname.includes('/profesional/index.html')) {
        loadProfessionalDashboardData();
    }
});