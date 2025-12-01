// nandodm/servinet/ServiNet-2722301d95f2577ef5efb6c73b69eff2ce58b6f55/perfil_publico.js

/**
 * Función auxiliar para generar el HTML de las estrellas de calificación.
 */
function renderRating(rating) {
    const fixedRating = parseFloat(rating || 0).toFixed(1); 
    const roundedRating = Math.round(fixedRating * 2) / 2;
    let html = '';
    
    for (let i = 1; i <= 5; i++) {
        if (roundedRating >= i) {
            html += '<i class="fas fa-star"></i>';
        } else if (roundedRating >= i - 0.5) {
            html += '<i class="fas fa-star-half-alt"></i>';
        } else {
            html += '<i class="far fa-star"></i>'; 
        }
    }
    return `<div class="text-yellow-500 text-lg">${html} <span class="text-gray-600 ml-2 font-medium">${fixedRating}/5.0</span></div>`;
}

/**
 * Genera el HTML para las últimas 3 reseñas.
 */
function renderReviews(reviews) {
    const container = document.getElementById('reviews-container');
    if (!container) return;
    
    container.innerHTML = '';
    
    if (reviews.length === 0) {
        container.innerHTML = '<p class="text-gray-500 italic">Este profesional aún no tiene reseñas.</p>';
        return;
    }

    let html = '';
    reviews.forEach(review => {
        
        // 🔑 CORRECCIÓN CLAVE AQUÍ: Aplicar robustez al nombre del cliente de la reseña.
        const safeApellidoPaterno = review.apellido_paterno || ''; 
        const apellidoInicial = safeApellidoPaterno.length > 0 ? safeApellidoPaterno.charAt(0) : '';

        const clientName = review.cliente_nombre 
            ? `${review.cliente_nombre} ${apellidoInicial}.` 
            : 'Cliente Anónimo';

        const reviewDate = review.fecha_reseña ? new Date(review.fecha_reseña).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Fecha desconocida';
        const reviewComment = review.comentario ? review.comentario.substring(0, 100) + (review.comentario.length > 100 ? '...' : '') : 'Sin comentario';
        
        let reviewStarsHtml = '';
        const reviewFixedRating = parseFloat(review.calificacion || 0); 
        for (let i = 1; i <= 5; i++) {
            if (reviewFixedRating >= i) {
                reviewStarsHtml += '<i class="fas fa-star"></i>';
            } else {
                reviewStarsHtml += '<i class="far fa-star"></i>'; 
            }
        }
        
        html += `
            <div class="border-b pb-4">
                <p class="text-yellow-500">${reviewStarsHtml}</p>
                <p class="text-gray-700 mt-1 italic">"${reviewComment}"</p>
                <p class="text-sm text-gray-500 mt-1">— ${clientName} (${reviewDate})</p>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    const totalReviewsDisplay = document.getElementById('total-reviews-count');
    if(totalReviewsDisplay) {
         totalReviewsDisplay.textContent = `(${reviews.length} Reseñas)`; 
    }

}

// 🚀 FUNCIONES DEL LIGHTBOX 🚀

window.openLightbox = function(url, caption) {
    const modal = document.getElementById('lightbox-modal');
    const img = document.getElementById('lightbox-image');
    const cap = document.getElementById('lightbox-caption');
    
    if (modal && img) {
        img.src = url;
        cap.textContent = caption || '';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

window.closeLightbox = function() {
    const modal = document.getElementById('lightbox-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

/**
 * 🚀 Genera la galería de trabajos del portafolio.
 */
function renderPortfolio(portfolio) {
    const container = document.getElementById('portfolio-container');
    if (!container) return;
    
    container.innerHTML = '';

    if (portfolio.length === 0) {
        container.innerHTML = '<p class="text-gray-500 italic">Este profesional aún no ha subido fotos de sus trabajos.</p>';
        return;
    }
    
    let html = '<div class="grid grid-cols-2 md:grid-cols-3 gap-4">';
    
    portfolio.forEach(item => {
        const description = item.descripcion || 'Trabajo completado';
        
        // 🔑 AÑADIDO: onclick event para abrir el lightbox
        // Usamos JSON.stringify y replace para manejar las comillas en el argumento string (descripción)
        const safeCaption = description.replace(/'/g, "\\'");
        const onclickHandler = `openLightbox('${item.url_imagen}', '${safeCaption}')`;

        html += `
            <div class="relative group overflow-hidden rounded-lg shadow-md aspect-square bg-gray-200 cursor-pointer" onclick="${onclickHandler}">
                <img src="${item.url_imagen}" alt="${description}" class="w-full h-full object-cover transition duration-300 group-hover:scale-110">
                <div class="absolute inset-0 bg-black bg-opacity-40 flex items-end opacity-0 group-hover:opacity-100 transition duration-300 p-3">
                    <p class="text-white text-sm font-semibold line-clamp-2">${description}</p>
                </div>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;
}

/**
 * Muestra la foto o las iniciales del profesional en el perfil público.
 */
function updateProfPhotoDisplay(photoUrl, initials) {
    const photoContainer = document.getElementById('prof-avatar-container');
    
    if (!photoContainer) return;
    
    // Limpiamos el contenido anterior
    photoContainer.innerHTML = ''; 

    // 🔑 LÓGICA DE VISUALIZACIÓN
    if (photoUrl) {
        // Si hay foto, inyectamos la etiqueta img
        photoContainer.innerHTML = `<img src="${photoUrl}" alt="Foto de Perfil" class="w-full h-full object-cover">`;
        photoContainer.classList.remove('bg-blue-600', 'text-white');
    } else {
        // Si no hay foto, inyectamos las iniciales con el estilo
        const initialsStyle = `
            font-size: 3rem; /* 48px */
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        photoContainer.innerHTML = `<span style="${initialsStyle}">${initials}</span>`;
        photoContainer.classList.add('bg-blue-600'); // Fondo azul para las iniciales
        photoContainer.classList.remove('bg-gray-200'); // Asegura que no tenga el fondo gris temporal
    }
}


/**
 * Carga los datos del perfil y los inyecta en el DOM.
 */
async function loadPublicProfile() {
    const urlParams = new URLSearchParams(window.location.search);
    const idProfesional = urlParams.get('id');
    const idServicio = urlParams.get('service');
    
    const contentContainer = document.getElementById('profile-content-container');
    const mainContent = document.getElementById('main-profile-content');
    const loadingState = document.getElementById('loading-state');
    
    // 1. Mostrar estado de carga
    if (loadingState) loadingState.classList.remove('hidden');
    if (mainContent) mainContent.classList.add('hidden'); 

    if (!idProfesional || !contentContainer) {
        if(contentContainer) {
            contentContainer.innerHTML = '<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-10 text-red-600">Error: ID de profesional no encontrado en la URL.</div>';
        }
        return;
    }

    try {
        const response = await fetch(`/ServiNet/api/get_profesional_public_profile.php?id=${idProfesional}&service=${idServicio}`);
        
        if (!response.ok) {
             throw new Error(`Error HTTP: ${response.status} ${response.statusText}`);
        }
        
        const result = await response.json();

        if (result.success && result.data) {
            const data = result.data;
            const perfil = data.perfil;
            const servicio = data.servicio_seleccionado;
            
            // 🔑 VERIFICACIÓN DE ROBUSTEZ FINAL: Usar Encadenamiento Opcional
            // Esto asegura que si 'perfil' es null, estas variables se vuelvan cadenas vacías.
            const safeNombre = perfil?.nombre || ''; 
            const safeApellido = perfil?.apellido_paterno || '';
            
            // Cálculo de iniciales: Verificación de longitud para evitar errores de charAt
            const firstNameInitial = safeNombre.length > 0 ? safeNombre.charAt(0).toUpperCase() : '';
            const apellidoPaternoInitial = safeApellido.length > 0 ? safeApellido.charAt(0).toUpperCase() : '';
            
            const initials = firstNameInitial + apellidoPaternoInitial;
            
            const fullName = `${safeNombre} ${safeApellido}`; 
            
            // --- INYECCIÓN DE DATOS ---
            const setElementText = (id, value, isHTML = false) => {
                const el = document.getElementById(id);
                if (el) {
                    // Descodificar HTML para evitar ver &iacute; o &aacute;
                    const decodedValue = new DOMParser().parseFromString(value, 'text/html').body.textContent;
                    
                    if (isHTML) {
                        el.innerHTML = decodedValue;
                    } else {
                        el.textContent = decodedValue;
                    }
                }
            };
            
            // 1. CABECERA
            updateProfPhotoDisplay(perfil?.foto_perfil_url, initials); 
            
            setElementText('prof-name-display', fullName);
            setElementText('prof-specialty-display', perfil?.especialidades); 
            
            const verificadoIcon = document.getElementById('verificado-icon');
            if (verificadoIcon) {
                if (perfil?.estado_verificacion === 'verificado') {
                     verificadoIcon.classList.remove('hidden');
                } else {
                     verificadoIcon.classList.add('hidden');
                }
            }
            
            setElementText('prof-rating-header', renderRating(perfil?.calificacion_promedio), true);
            
            // 2. INFORMACIÓN PERSONAL/PROFESIONAL
            const descriptionContent = perfil?.descripcion ? perfil.descripcion.replace(/\n/g, '<br>') : 'Descripción no disponible.';
            setElementText('prof-description', descriptionContent, true);
            setElementText('prof-specialties-list', `Especialidades: ${perfil?.especialidades}`);

            // 🚀 RENDERIZAR PORTAFOLIO
            renderPortfolio(data.portafolio || []);

            // 3. DETALLES DEL SERVICIO (Panel lateral)
            const serviceTitle = servicio ? servicio.nombre_servicio : perfil?.especialidades;
            setElementText('service-title-display', serviceTitle);
            
            setElementText('tarifa-base-display', `$${parseFloat(perfil?.tarifa || 0).toFixed(2)} MXN/hr`);
            setElementText('ubicacion-display', perfil?.municipio);
            
            // 🔑 CAMBIO CLAVE: Definir URL para Agendar
            const agendaUrl = `/ServiNet/pages/agendar-cita.html?prof=${idProfesional}&service=${idServicio}`;
            
            // 4. Botones de acción (SOLO QUEDA AGENDAR)
            const agendaBtn = document.getElementById('agenda-btn');
            
            // 🔑 ASIGNAR REDIRECCIÓN DIRECTA
            if (agendaBtn) {
                agendaBtn.onclick = () => {
                    window.location.href = agendaUrl;
                };
            }

            // 5. RESEÑAS
            renderReviews(data.resenas || []); // Aquí es donde estaba fallando
            const allReviewsLink = document.getElementById('all-reviews-link');
            if (allReviewsLink) allReviewsLink.href = `/ServiNet/pages/perfil-publico.html?id=${idProfesional}#resenas`;

            // 6. MOSTRAR CONTENIDO EXITOSO
            if (mainContent) mainContent.classList.remove('hidden');

        } else {
            // Si la API falló O si el perfil no fue encontrado (manejado en PHP)
            contentContainer.innerHTML = `<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-10 text-red-600">Error al cargar perfil: ${result.message || 'La respuesta de la API no fue exitosa. Asegúrate que el ID de profesional exista y tenga perfil asociado.'}</div>`;
        }

    } catch (error) {
        // Captura errores finales de red o del JS
        console.error('Error de red/servidor (try-catch externo):', error);
        contentContainer.innerHTML = `<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-10 text-red-600">Error de conexión con el servidor. (Causa: ${error.message})</div>`;
    } finally {
        if (loadingState) loadingState.classList.add('hidden');
    }
}

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    loadPublicProfile();
    
    // 🔑 LISTENERS PARA CERRAR EL LIGHTBOX
    const closeBtn = document.getElementById('close-lightbox');
    const modal = document.getElementById('lightbox-modal');
    
    if (closeBtn) {
        closeBtn.addEventListener('click', closeLightbox);
    }
    
    if (modal) {
        // Cerrar al hacer clic fuera de la imagen (en el fondo oscuro)
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeLightbox();
            }
        });
    }
});