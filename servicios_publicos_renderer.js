// nandodm/servinet/ServiNet-2722301d95f257ef5efb6c73b69eff2ce58b6f55/servicios_publicos_renderer.js

// Almacenamos los servicios cargados globalmente en el scope para accederlos desde el modal
let loadedServices = [];

/**
 * Genera el HTML para las estrellas de calificación.
 */
function renderRating(rating) {
    const fixedRating = parseFloat(rating || 0).toFixed(1); 
    const roundedRating = Math.round(fixedRating * 2) / 2;
    let html = '';
    
    for (let i = 1; i <= 5; i++) {
        if (roundedRating >= i) {
            html += '<i class="fas fa-star text-sm"></i>';
        } else if (roundedRating >= i - 0.5) {
            html += '<i class="fas fa-star-half-alt text-sm"></i>';
        } else {
            html += '<i class="far fa-star text-sm"></i>'; 
        }
    }
    return `<div class="professional-rating flex items-center text-yellow-500">${html} <span class="text-gray-500 ml-2 text-sm">(${fixedRating}/5.0)</span></div>`;
}

/**
 * Dibuja las tarjetas de servicio en la cuadrícula.
 */
window.renderServiceCards = function (servicesToRender) {
    const servicesGrid = document.getElementById('servicesGrid');
    const emptyState = document.getElementById('emptyState');
    const totalServicesDisplay = document.getElementById('totalServices');
    const totalProfessionalsDisplay = document.getElementById('totalProfessionals');

    if (!servicesGrid) return;
    servicesGrid.innerHTML = ''; 
    loadedServices = servicesToRender; // Almacenar los servicios

    if (servicesToRender.length === 0) {
        emptyState.classList.remove('hidden');
        servicesGrid.classList.add('hidden');
        totalServicesDisplay.textContent = '0';
        totalProfessionalsDisplay.textContent = '0';
        return;
    }

    let uniqueProfessionals = new Set();
    servicesGrid.classList.remove('hidden');
    emptyState.classList.add('hidden');

    servicesToRender.forEach(service => {
        uniqueProfessionals.add(service.profesional.id);
        
        // 🔑 1. OBTENER LA IMAGEN DEL SERVICIO (NEW)
        // Usamos la URL de la imagen que el profesional subió
        const serviceImageUrl = service.imagen_url || '/ServiNet/assets/img/default_service.webp'; 
        // 🔑 2. OBTENER LA FOTO DE PERFIL DEL PROFESIONAL (para el avatar)
        const photoUrl = service.profesional.foto_perfil || '/ServiNet/assets/img/default-avatar.png';
        
        // Calcular iniciales para el avatar de respaldo
        const nombreParts = service.profesional.nombre.split(/\s+/);
        const initials = (nombreParts[0]?.charAt(0) || '').toUpperCase() + 
                         (service.profesional.apellido_paterno?.charAt(0) || '').toUpperCase();


        const badgeColor = service.categoria.nombre.toLowerCase().includes('hogar') ? 'bg-blue-600' :
                           service.categoria.nombre.toLowerCase().includes('tecnología') ? 'bg-purple-600' :
                           service.categoria.nombre.toLowerCase().includes('automotriz') ? 'bg-green-600' :
                           'bg-gray-600'; 
        
        const cardHtml = `
            <div class="service-card bg-white rounded-xl shadow-lg overflow-hidden card-hover" 
                 data-category-id="${service.categoria.id}" 
                 data-service-id="${service.id}">
                
                <div class="service-card__image h-48 bg-gray-200 relative flex items-center justify-center p-4" 
                     style="background-image: url('${serviceImageUrl}'); background-size: cover; background-position: center;">
                     
                    <div class="service-card__overlay absolute inset-0 bg-black opacity-10"></div>
                     
                    <div class="service-card__badge absolute top-4 right-4 z-10">
                        <span class="px-3 py-1 text-white text-xs font-bold rounded-full shadow-md ${badgeColor}">${service.categoria.nombre}</span>
                    </div>
                </div>
                
                <div class="service-card__content p-6">
                    <div class="service-card__header flex justify-between items-start mb-4">
                        <h3 class="service-card__title text-xl font-bold text-gray-900 line-clamp-2">${service.titulo}</h3>
                        <div class="service-card__price text-2xl font-bold text-blue-600">$${parseFloat(service.precio_base).toFixed(2)}/hr</div>
                    </div>
                    
                    <p class="service-card__description text-gray-600 mb-4 line-clamp-2">
                        ${service.descripcion}
                    </p>
                    
                    <div class="service-card__professional flex items-center mb-4">
                        <div class="professional-avatar w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold mr-3 overflow-hidden">
                            ${photoUrl.includes('default-avatar') ? initials : `<img src="${photoUrl}" alt="Avatar" class="w-full h-full object-cover">`}
                        </div>
                        
                        <div class="professional-info flex-1">
                            <h4 class="professional-name font-semibold text-gray-900">
                                ${service.profesional.nombre}
                                ${service.profesional.verificado ? '<i class="fas fa-check-circle text-green-500 text-xs ml-1" title="Verificado"></i>' : ''}
                            </h4>
                            ${renderRating(service.profesional.calificacion_promedio)}
                        </div>
                    </div>
                    
                    <div class="service-card__meta flex justify-between text-sm text-gray-500 mb-4">
                        <div class="meta-item flex items-center text-green-600">
                            <i class="fas fa-map-marker-alt mr-1"></i>
                            <span class="font-bold">${service.profesional.municipio}</span>
                        </div>
                        <div class="meta-item flex items-center">
                            <i class="fas fa-clock mr-1"></i>
                            <span>${service.duracion} min (Est.)</span>
                        </div>
                    </div>
                    
                    <button onclick="showDetailModal(${service.id})" class="service-card__action w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition font-semibold flex items-center justify-center">
                        <i class="fas fa-eye mr-2"></i>
                        Ver Detalles y Agendar
                    </button>
                </div>
            </div>
        `;
        servicesGrid.innerHTML += cardHtml;
    });

    totalServicesDisplay.textContent = servicesToRender.length;
    totalProfessionalsDisplay.textContent = uniqueProfessionals.size;
}

/**
 * Muestra el modal de detalles del servicio.
 */
window.showDetailModal = function(serviceId) {
    const service = loadedServices.find(s => s.id === serviceId);
    if (!service) return;

    const modal = document.getElementById('detailModal');
    
    // 1. Llenar Modal
    document.getElementById('modal-title').textContent = service.titulo;
    document.getElementById('modal-description').textContent = service.descripcion;
    document.getElementById('modal-price').textContent = `$${parseFloat(service.precio_base).toFixed(2)} MXN/hr`;
    document.getElementById('modal-duration').textContent = `${service.duracion} minutos`;
    document.getElementById('modal-category').textContent = service.categoria.nombre;
    
    // 2. Llenar datos del Profesional
    const profName = service.profesional.nombre + (service.profesional.verificado ? ' ✅' : '');
    document.getElementById('modal-prof-name').textContent = profName;
    document.getElementById('modal-prof-municipio').textContent = service.profesional.municipio;
    document.getElementById('modal-prof-rating').innerHTML = renderRating(service.profesional.calificacion_promedio);
    
    // 🔑 Usamos la foto de perfil del profesional para el avatar dentro del modal
    const photoUrl = service.profesional.foto_perfil || '/ServiNet/assets/img/default-avatar.png';
    const avatarImg = document.createElement('img');
    avatarImg.src = photoUrl;
    avatarImg.className = 'w-full h-full object-cover';
    
    const avatarContainer = document.getElementById('modal-prof-avatar');
    if (avatarContainer) {
        avatarContainer.innerHTML = '';
        avatarContainer.appendChild(avatarImg);
    }
    
    // 3. CORRECCIÓN CLAVE: Llenar disponibilidad.
    const isAvailable = service.disponibilidad_final === true; 

    const availabilityText = isAvailable ? 'DISPONIBLE' : 'NO DISPONIBLE';
    const availabilityClass = isAvailable ? 'text-green-600' : 'text-red-600';
    
    document.getElementById('modal-availability').textContent = availabilityText;
    document.getElementById('modal-availability').className = `font-semibold ${availabilityClass}`;

    // 4. Configurar botones de acción
    const profileUrl = `/ServiNet/pages/perfil-publico.html?id=${service.profesional.id}&service=${service.id}`;
    
    const modalProfileBtn = document.getElementById('modal-profile-btn');
    if (modalProfileBtn) {
        modalProfileBtn.href = profileUrl;
    }

    // 5. Mostrar Modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}


// Muestra el estado de carga
window.showLoading = function() {
    document.getElementById('loadingState').classList.remove('hidden');
    document.getElementById('servicesGrid').classList.add('hidden');
    document.getElementById('emptyState').classList.add('hidden');
}

// Oculta el estado de carga
window.hideLoading = function() {
    document.getElementById('loadingState').classList.add('hidden');
    document.getElementById('servicesGrid').classList.remove('hidden');
}