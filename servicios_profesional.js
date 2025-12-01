// nandodm/servinet/ServiNet-2722301d95f257ef5efb6c73b69eff2ce58b6f55/servicios_profesional.js

let allServices = []; 
const serviceForm = document.getElementById('service-form');
const serviceModal = document.getElementById('service-modal');
const modalTitle = document.getElementById('service-modal-title');
const serviceMessage = document.getElementById('service-message');
const deleteServiceBtn = document.getElementById('delete-service-btn');
const serviceAvailabilityToggle = document.getElementById('service-availability-toggle');
// 🔑 NUEVAS REFERENCIAS PARA LA IMAGEN
const imagePreviewContainer = document.getElementById('service-image-preview-container');
const imagePreview = document.getElementById('service-image-preview');


function getStatusIndicator(isDisponible) {
    if (isDisponible == 1 || isDisponible === true) {
        return '<span class="status-active professional-bg-active text-xs font-semibold px-3 py-1 rounded-full"><i class="fas fa-check-circle mr-1"></i> ACTIVO</span>';
    } else {
        return '<span class="status-paused bg-yellow-100 text-yellow-800 text-xs font-semibold px-3 py-1 rounded-full"><i class="fas fa-pause-circle mr-1"></i> PAUSADO</span>';
    }
}

function displayMessage(success, message) {
    serviceMessage.classList.remove('hidden', 'bg-red-100', 'bg-green-100', 'text-red-700', 'text-green-700');
    if (success) {
        serviceMessage.classList.add('bg-green-100', 'text-green-700');
    } else {
        serviceMessage.classList.add('bg-red-100', 'text-red-700');
    }
    serviceMessage.innerHTML = message;
}

function renderServiceList(services) {
    const container = document.getElementById('services-list-container');
    if (!container) return;
    
    container.innerHTML = '';

    if (services.length === 0) {
        container.innerHTML = '<div class="bg-white p-6 rounded-lg shadow-md text-center text-gray-500">Aún no has publicado ningún servicio. ¡Comienza a añadir uno!</div>';
        return;
    }

    services.forEach(service => {
        const isDisponible = service.disponible == 1;
        // 🔑 USAR IMAGEN DEL SERVICIO COMO FONDO
        const imageUrl = service.imagen_url || '/ServiNet/assets/img/default_service.webp';
        
        const cardHtml = `
            <div class="bg-white p-6 rounded-lg shadow-md professional-border-left-2 ${isDisponible ? 'border-l-green-500' : 'border-l-yellow-500'} hover:shadow-lg transition">
                <div class="relative h-32 mb-4 rounded-lg overflow-hidden" 
                     style="background-image: url('${imageUrl}'); background-size: cover; background-position: center;">
                    <div class="absolute inset-0 bg-black bg-opacity-30 p-3 flex justify-between items-start">
                        <h3 class="text-xl font-bold text-white">${service.titulo}</h3>
                        ${getStatusIndicator(isDisponible)}
                    </div>
                </div>
                
                <div class="border-t border-gray-200 pt-4 space-y-2">
                    <p class="text-sm text-gray-500">${service.categoria_nombre}</p>
                    <p class="text-gray-600">
                        <i class="fas fa-dollar-sign professional-color mr-2"></i> 
                        <strong>Precio Base:</strong> $${parseFloat(service.precio_base).toFixed(2)} MXN
                    </p>
                    <p class="text-gray-600">
                        <i class="fas fa-clock professional-color mr-2"></i> 
                        <strong>Duración:</strong> ${service.duracion} minutos
                    </p>
                    <p class="text-gray-600 text-sm italic line-clamp-2">${service.descripcion}</p>
                </div>

                <div class="mt-4 text-right">
                    <button onclick="openServiceModal(${service.id})" class="bg-blue-600 text-white py-2 px-4 text-sm rounded-md hover:bg-blue-700 transition">
                        <i class="fas fa-edit mr-2"></i> Editar
                    </button>
                </div>
            </div>
        `;
        container.innerHTML += cardHtml;
    });
}

async function loadMyServices() {
    const container = document.getElementById('services-list-container');
    if (!container) return;
    
    container.innerHTML = '<div class="bg-white p-6 rounded-lg shadow-md text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando tus servicios...</div>';

    // Usar el ID de cuenta de localStorage para simular la sesión
    const user = JSON.parse(localStorage.getItem('user'));
    const id_cuenta = user ? user.id_cuenta : 0; 

    try {
        const response = await fetch(`/ServiNet/api/get_my_services.php?id_cuenta=${id_cuenta}`);
        const result = await response.json();

        if (result.success) {
            // Asegurarse de que id_categoria es un entero para la búsqueda posterior
            allServices = result.services.map(s => ({
                ...s,
                id_categoria: parseInt(s.id_categoria)
            }));
            renderServiceList(allServices);
        } else {
            container.innerHTML = `<div class="bg-white p-6 rounded-lg shadow-md text-center text-red-500">Error al cargar servicios: ${result.message}</div>`;
        }

    } catch (error) {
        console.error('Error de conexión con la API de servicios:', error);
        container.innerHTML = `<div class="bg-white p-6 rounded-lg shadow-md text-center text-red-500">Error de conexión con el servidor.</div>`;
    }
}

// Lógica de Modal (Global para el window)
window.openServiceModal = async function(serviceId = null) { // Convertido a async
    serviceForm.reset();
    serviceMessage.classList.add('hidden');
    document.getElementById('service-id').value = '';
    
    // 🔑 Ocultar/Limpiar imagen de vista previa
    if (imagePreviewContainer) imagePreviewContainer.classList.add('hidden');

    const selectCat = document.getElementById('id_categoria');
    
    // 1. Cargar SELECT de categorías y esperar a que termine
    if (window.fetchAndPopulateSelect) {
        // fetchAndPopulateSelect ya es async, lo esperamos antes de continuar
        await fetchAndPopulateSelect('get_categorias.php', 'id_categoria', 'categorias', 'id_categoria', 'nombre_categoria'); 
    }

    if (serviceId) {
        const service = allServices.find(s => s.id == serviceId);
        if (!service) {
            alert('Servicio no encontrado.');
            return;
        }

        modalTitle.textContent = 'Editar Servicio: ' + service.titulo;
        document.getElementById('service-id').value = service.id;
        
        // Llenar campos
        document.getElementById('nombre_servicio').value = service.titulo;
        document.getElementById('precio').value = parseFloat(service.precio_base).toFixed(2);
        document.getElementById('duracion').value = service.duracion;
        document.getElementById('descripcion').value = service.descripcion;
        
        // 🔑 CORRECCIÓN CLAVE: Mostrar Imagen Actual si existe
        if (imagePreviewContainer && imagePreview && service.imagen_url) {
            // Revisa si la URL es el valor por defecto de la BD antes de mostrar
            if (service.imagen_url !== '/ServiNet/assets/img/default_service.webp') {
                 imagePreview.src = service.imagen_url;
                 imagePreviewContainer.classList.remove('hidden');
            } else {
                 imagePreviewContainer.classList.add('hidden');
            }
        }

        // SELECCIÓN CORREGIDA: Se asigna el valor DESPUÉS de que fetchAndPopulateSelect haya terminado
        selectCat.value = String(service.id_categoria); 

        // Mostrar opciones de edición
        deleteServiceBtn.classList.remove('hidden');
        serviceAvailabilityToggle.classList.remove('hidden');
        document.getElementById('is_disponible').checked = service.disponible == 1;

    } else {
        modalTitle.textContent = 'Añadir Nuevo Servicio';
        deleteServiceBtn.classList.add('hidden');
        serviceAvailabilityToggle.classList.add('hidden');
    }

    serviceModal.classList.remove('hidden');
    serviceModal.classList.add('flex');
}

// Función de Cierre del Modal
document.getElementById('close-service-modal').addEventListener('click', () => {
    serviceModal.classList.add('hidden');
    serviceModal.classList.remove('flex');
});


// Manejo del Envío del Formulario (Añadir o Editar)
serviceForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const isEdit = document.getElementById('service-id').value !== '';
    const submitBtn = document.getElementById('save-service-btn');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Guardando...';
    submitBtn.disabled = true;

    // 🔑 USAMOS FormData DIRECTAMENTE PARA INCLUIR EL ARCHIVO
    const formData = new FormData(this);
    
    // Añadir el ID de cuenta (de la sesión, para el backend)
    const user = JSON.parse(localStorage.getItem('user'));
    formData.append('id_cuenta', user ? user.id_cuenta : 0);
    
    // Para el modo edición, asegurar que el estado de disponibilidad y ID del servicio estén presentes
    if (isEdit) {
         formData.append('disponible', document.getElementById('is_disponible').checked ? 1 : 0);
         formData.append('service_id', document.getElementById('service-id').value);
    }
    
    // Definir el endpoint
    const endpoint = isEdit ? '/ServiNet/api/update_service.php' : '/ServiNet/api/add_service.php';
    
    try {
        // 🔑 Enviar FormData (incluye el archivo si fue seleccionado)
        const response = await fetch(endpoint, { method: 'POST', body: formData });
        const result = await response.json();
        
        if (result.success) {
            displayMessage(true, result.message);
            // Recargar la lista después de un breve retraso
            setTimeout(() => {
                serviceModal.classList.add('hidden');
                serviceModal.classList.remove('flex');
                loadMyServices(); 
            }, 1000);
        } else {
            displayMessage(false, result.message);
        }

    } catch (error) {
        displayMessage(false, 'Error de conexión con el servidor.');
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});

// Implementación Real de Eliminar Servicio
deleteServiceBtn.addEventListener('click', async function() {
    const serviceId = document.getElementById('service-id').value;
    const user = JSON.parse(localStorage.getItem('user'));
    
    if (confirm(`¿Estás seguro de ELIMINAR el servicio ID #${serviceId}? Esta acción es permanente.`)) {
        
        const deleteFormData = new FormData();
        deleteFormData.append('service_id', serviceId);
        deleteFormData.append('id_cuenta', user ? user.id_cuenta : 0);
        
        try {
            const response = await fetch('/ServiNet/api/delete_service.php', {
                method: 'POST',
                body: deleteFormData
            });
            const result = await response.json();

            if (result.success) {
                alert('✅ ' + result.message);
                serviceModal.classList.add('hidden');
                serviceModal.classList.remove('flex');
                loadMyServices();
            } else {
                alert('❌ Error al eliminar: ' + result.message);
            }
        } catch (error) {
            alert('❌ Error de conexión con el servidor.');
        }
    }
});

// Listener para abrir el modal (botón "Nuevo Servicio")
document.getElementById('open-add-service-modal').addEventListener('click', () => openServiceModal(null));

// Inicialización
document.addEventListener('DOMContentLoaded', loadMyServices);