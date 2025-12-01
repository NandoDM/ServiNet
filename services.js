// nandodm/servinet/ServiNet-35fb085a9f887b792b3d4ac23d22f72cd494ceec/assets/js/services.js
// Depende de register_utils.js para la función fetchAndPopulateSelect (asumiendo que está disponible globalmente)

document.addEventListener('DOMContentLoaded', function() {
    
    // --- Referencias a Elementos ---
    const servicesGrid = document.getElementById('servicesGrid');
    const categoryFiltersContainer = document.getElementById('categoryFilters');
    const searchInput = document.getElementById('searchInput');
    const municipioFilter = document.getElementById('municipioFilter'); 
    const priceFilter = document.getElementById('priceFilter'); 
    const clearSearchBtn = document.getElementById('clearSearch');
    const serviceModal = document.getElementById('serviceModal');
    const closeServiceModal = document.getElementById('closeServiceModal');
    const loadingState = document.getElementById('loadingState');
    const emptyState = document.getElementById('emptyState');
    const totalServicesDisplay = document.getElementById('totalServices');
    const totalProfessionalsDisplay = document.getElementById('totalProfessionals');

    let currentFilter = 'all'; // Categoría (Nombre en minúsculas)
    let currentSearchTerm = '';
    let currentMunicipio = 'all'; 
    let currentPriceRange = 'all'; 

    // ----------------------------------------------------
    // A. Funciones de Utilidad y Carga de Catálogos
    // ----------------------------------------------------
    
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
     * Carga datos de catálogos y popula un SELECT.
     * Nota: Esta es una versión simplificada, idealmente se usaría la de register_utils.js
     */
    async function loadCatalog(endpoint, selectElement, arrayKey, valueKey, textKey, defaultText = 'Todos') {
        selectElement.innerHTML = `<option value="all" selected>Cargando...</option>`;
        try {
            const response = await fetch('/ServiNet/api/' + endpoint);
            const data = await response.json();

            if (data.success && data[arrayKey] && data[arrayKey].length > 0) {
                selectElement.innerHTML = `<option value="all">${defaultText}</option>`;
                data[arrayKey].forEach(item => {
                    selectElement.innerHTML += `<option value="${item[valueKey]}">${item[textKey]}</option>`;
                });
            } else {
                selectElement.innerHTML = `<option value="all">${defaultText} (Error)</option>`;
            }
        } catch (error) {
            selectElement.innerHTML = `<option value="all">${defaultText} (Fallo)</option>`;
        }
    }


    /**
     * Dibuja las tarjetas de servicio en la cuadrícula.
     */
    function renderServiceCards(servicesToRender) {
        if (!servicesGrid) return;
        servicesGrid.innerHTML = ''; 

        if (servicesToRender.length === 0) {
            emptyState.classList.remove('hidden');
            servicesGrid.classList.add('hidden');
            totalServicesDisplay.textContent = '0';
            totalProfessionalsDisplay.textContent = '0';
            return;
        }

        let uniqueProfessionals = new Set();
        servicesGrid.classList.remove('hidden');


        servicesToRender.forEach(service => {
            uniqueProfessionals.add(service.profesional_id);

            const initials = service.profesional.split(' ')
                .map(n => n.charAt(0))
                .join('').toUpperCase();

            const badgeColor = service.categoria_nombre.toLowerCase().includes('hogar') ? 'bg-blue-600' :
                               service.categoria_nombre.toLowerCase().includes('tecnología') ? 'bg-purple-600' :
                               'bg-green-600'; 
            
            const cardHtml = `
                <div class="service-card bg-white rounded-xl shadow-lg overflow-hidden card-hover" 
                     data-category="${service.categoria_nombre.toLowerCase()}" 
                     data-id="${service.id}">
                    
                    <div class="service-card__image h-48 bg-gray-200 relative flex items-center justify-center p-4">
                         <span class="text-gray-500 text-sm">Ejemplo visual de ${service.categoria_nombre}</span>
                        <div class="service-card__badge absolute top-4 right-4">
                            <span class="px-3 py-1 text-white text-xs font-bold rounded-full shadow-md ${badgeColor}">${service.categoria_nombre}</span>
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
                            <div class="professional-avatar w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold mr-3">
                                ${initials}
                            </div>
                            <div class="professional-info flex-1">
                                <h4 class="professional-name font-semibold text-gray-900">${service.profesional}</h4>
                                ${renderRating(service.calificacion)}
                            </div>
                        </div>
                        
                        <div class="service-card__meta flex justify-between text-sm text-gray-500 mb-4">
                            <div class="meta-item flex items-center text-green-600">
                                <i class="fas fa-tag mr-1"></i>
                                <span class="font-bold text-lg">Servicio</span>
                            </div>
                            <div class="meta-item flex items-center">
                                <i class="fas fa-map-marker-alt mr-1"></i>
                                <span>${service.profesional_municipio}</span>
                            </div>
                        </div>
                        
                        <button data-service-id="${service.id}" class="service-card__action w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition font-semibold flex items-center justify-center">
                            <i class="fas fa-eye mr-2"></i>
                            Ver Detalles y Agendar
                        </button>
                    </div>
                </div>
            `;
            servicesGrid.innerHTML += cardHtml;
        });

        emptyState.classList.add('hidden');
        totalServicesDisplay.textContent = servicesToRender.length;
        totalProfessionalsDisplay.textContent = uniqueProfessionals.size;
    }

    // ----------------------------------------------------
    // C. Manejo Central de Filtros y Event Listeners
    // ----------------------------------------------------

    /**
     * Función principal que recopila todos los filtros y dispara la carga de datos.
     */
    function handleFilterChange(e) {
        if (e && e.target.classList.contains('filter-btn')) {
            // Manejar click en botones de categoría
            document.querySelectorAll('#categoryFilters .filter-btn').forEach(b => {
                b.classList.remove('active', 'bg-blue-600', 'text-white', 'border-blue-600');
                b.classList.add('border-gray-300', 'text-gray-700', 'hover:bg-gray-100');
            });
            e.target.classList.add('active', 'bg-blue-600', 'text-white', 'border-blue-600');
            e.target.classList.remove('border-gray-300', 'text-gray-700', 'hover:bg-gray-100');
            
            // Usamos el data-category (nombre) para el filtro en el backend
            currentFilter = e.target.dataset.category.toLowerCase();
        }
        
        // Recopilar valores de los SELECTs e INPUTs
        currentSearchTerm = searchInput.value.trim();
        currentMunicipio = municipioFilter.value;
        currentPriceRange = priceFilter.value;
        
        // Ocultar/Mostrar botón de limpiar búsqueda
        clearSearchBtn.classList.toggle('hidden', currentSearchTerm === '');
        
        loadServicesFromAPI();
    }
    
    // ----------------------------------------------------
    // D. Carga y Envío de Datos a la API
    // ----------------------------------------------------
    
    async function loadServicesFromAPI() {
        if (!loadingState || !servicesGrid) return;
        
        loadingState.classList.remove('hidden');
        servicesGrid.classList.add('hidden');
        emptyState.classList.add('hidden');

        // Construir la URL con parámetros de filtro
        const params = new URLSearchParams({
            search: currentSearchTerm,
            category: currentFilter,
            municipio: currentMunicipio,
            price_range: currentPriceRange,
        });
        
        const fetchUrl = `/ServiNet/api/get_services.php?${params.toString()}`;

        try {
            const response = await fetch(fetchUrl);
            const data = await response.json();
            
            if (data.success && data.services) {
                renderServiceCards(data.services);
            } else {
                emptyState.classList.remove('hidden');
                totalServicesDisplay.textContent = '0';
                totalProfessionalsDisplay.textContent = '0';
            }

        } catch (error) {
            console.error("Error al cargar servicios desde la API:", error);
            servicesGrid.innerHTML = '<p class="text-red-500 text-center col-span-full">Error al conectar con el servidor de servicios.</p>';
            servicesGrid.classList.remove('hidden');
        } finally {
            loadingState.classList.add('hidden');
        }
    }
    
    // Ejecución inicial: Carga de catálogos y primera lista de servicios
    async function initializeServicesPage() {
        // 1. Cargar Municipios (Usamos la función local loadCatalog)
        const munElement = document.getElementById('municipioFilter');
        if (munElement) await loadCatalog('get_municipios.php', munElement, 'data', 'nombre', 'nombre', 'Todos los Municipios');
        
        // 2. Cargar Categorías (Poblar los botones dinámicamente)
        fetch('/ServiNet/api/get_categorias.php')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.categorias) {
                    const categoryFilters = document.getElementById('categoryFilters');
                    categoryFilters.innerHTML = '<button data-category="all" class="filter-btn active px-4 py-2 text-sm rounded-full border border-blue-600 bg-blue-600 text-white hover:bg-blue-700 transition">Todas</button>';
                    
                    data.categorias.forEach(cat => {
                        // Usamos el nombre de la categoría en minúsculas como data-category
                        categoryFilters.innerHTML += `
                            <button data-category="${cat.nombre_categoria.toLowerCase()}" 
                                    class="filter-btn px-4 py-2 text-sm rounded-full border border-gray-300 text-gray-700 hover:bg-gray-100 transition">
                                ${cat.nombre_categoria}
                            </button>
                        `;
                    });
                    
                    // Re-asignar listeners a los nuevos botones
                    document.querySelectorAll('#categoryFilters .filter-btn').forEach(btn => {
                        btn.addEventListener('click', handleFilterChange);
                    });
                }
            }).catch(e => console.error("Error al cargar categorías dinámicas:", e));
        
        // 3. Cargar la lista inicial de servicios
        loadServicesFromAPI();
    }
    
    // Asignar listeners iniciales a los selects
    municipioFilter.addEventListener('change', handleFilterChange);
    priceFilter.addEventListener('change', handleFilterChange);
    searchInput.addEventListener('change', handleFilterChange);
    
    // Listener para restablecer filtros
    document.getElementById('resetFilters').addEventListener('click', function() {
        searchInput.value = '';
        municipioFilter.value = 'all';
        priceFilter.value = 'all';
        
        // Resetear botones de categoría
        document.querySelectorAll('#categoryFilters .filter-btn').forEach(b => {
             b.classList.remove('active', 'bg-blue-600', 'text-white', 'border-blue-600');
             b.classList.add('border-gray-300', 'text-gray-700', 'hover:bg-gray-100');
        });
        document.querySelector('.filter-btn[data-category="all"]')?.classList.add('active', 'bg-blue-600', 'text-white', 'border-blue-600');
        
        handleFilterChange();
    });

    initializeServicesPage();
});