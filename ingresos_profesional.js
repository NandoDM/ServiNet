// nandodm/servinet/ServiNet-e7a913c5251aedd806317b56fa60d612c9d7bd2f/assets/js/ingresos_profesional.js

function renderTransactionHistory(transactions) {
    const listContainer = document.getElementById('transaction-history-list');
    if (!listContainer) return;

    listContainer.innerHTML = '';

    if (transactions.length === 0) {
        listContainer.innerHTML = '<li class="py-3 text-center text-gray-500">No hay transacciones registradas.</li>';
        return;
    }

    transactions.forEach(item => {
        let amountClass = 'text-gray-900';
        let amountSign = '';
        let iconClass = 'fas fa-exchange-alt';
        
        if (item.estado === 'liberado' || item.estado === 'retenido') {
            amountClass = 'text-green-600';
            amountSign = '+';
            iconClass = (item.estado === 'liberado') ? 'fas fa-arrow-down' : 'fas fa-hourglass-half';
        } else if (item.estado === 'reembolsado') {
             amountClass = 'text-red-600';
             amountSign = '-';
             iconClass = 'fas fa-undo-alt';
        } 
        
        const typeDetail = (item.estado === 'retenido') ? `Pago retenido en Escrow | Cliente: ${item.cliente}` : 
                           (item.estado === 'liberado') ? `Liberado por el cliente | Cliente: ${item.cliente}` : item.tipo;
        
        const listItem = `
            <li class="py-3 flex justify-between items-center hover:bg-gray-50 px-2 rounded-md">
                <div class="flex items-center">
                    <i class="${iconClass} text-xl mr-4 ${amountClass}"></i>
                    <div>
                        <p class="font-semibold text-gray-800">${item.nombre_item}</p>
                        <p class="text-xs text-gray-500">${item.fecha} - ${typeDetail}</p>
                    </div>
                </div>
                <span class="font-bold ${amountClass}">${amountSign} $${item.monto} MXN</span>
            </li>
        `;
        listContainer.innerHTML += listItem;
    });
}

async function loadProfessionalIngresos() {
    const kpiContainer = document.getElementById('kpi-container');
    const historyContainer = document.getElementById('transaction-history-list');

    if (!kpiContainer || !historyContainer) return;
    
    // Simular carga
    historyContainer.innerHTML = '<li class="py-3 text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando historial...</li>';

    try {
        const response = await fetch('/ServiNet/api/get_profesional_ingresos.php');
        const result = await response.json();

        if (result.success) {
            const data = result.data;

            // 1. Llenar KPIs
            document.getElementById('saldo-actual-amount').textContent = `$${data.saldo_actual} MXN`;
            document.getElementById('pendiente-liberar-amount').textContent = `$${data.total_retenido} MXN`;
            document.getElementById('retiros-mes-amount').textContent = `$${data.retiros_mes} MXN`;

            // 2. Renderizar historial
            renderTransactionHistory(data.historial_transacciones);

        } else {
            console.error('Error del backend:', result.message);
            // Mostrar error en los contenedores
            document.getElementById('saldo-actual-amount').textContent = 'Error';
            document.getElementById('pendiente-liberar-amount').textContent = 'Error';
            document.getElementById('retiros-mes-amount').textContent = 'Error';
            historyContainer.innerHTML = `<li class="py-3 text-center text-red-500">Error: ${result.message}</li>`;
        }

    } catch (error) {
        console.error('Error de conexión con la API de Ingresos:', error);
        document.getElementById('saldo-actual-amount').textContent = 'Error';
        document.getElementById('pendiente-liberar-amount').textContent = 'Error';
        document.getElementById('retiros-mes-amount').textContent = 'Error';
        historyContainer.innerHTML = `<li class="py-3 text-center text-red-500">Error de conexión con el servidor.</li>`;
    }
}

// Ejecutar la carga de datos al cargar el DOM
document.addEventListener('DOMContentLoaded', loadProfessionalIngresos);