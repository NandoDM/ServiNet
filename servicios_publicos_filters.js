// nandodm/servinet/ServiNet-e7a913c5251aedd806317b56fa60d612c9d7bd2f/assets/js/servicios_publicos_filters.js
// Depende de register_utils.js (para fetchAndPopulateSelect) y servicios_publicos_renderer.js (para renderServiceCards)

document.addEventListener('DOMContentLoaded', function() {
    
    // --- Referencias a Elementos de Filtro ---
    const categoryFilter = document.getElementById('categoryFilter');
    const municipioFilter = document.getElementById('municipioFilter'); 
    const priceFilter = document.getElementById('priceFilter'); 
    const availabilityFilter = document.getElementById('availabilityFilter');
    const searchInput = document.getElementById('searchInput');
    const clearSearchBtn = document.getElementById('clearSearch');
    const applyFiltersBtn = document.getElementById('applyFiltersBtn');

    // --- Variables de Estado ---
    let currentFilters = {
        search: '',
        categoria: 'all',
        municipio: 'all',
        precio_rango: 'all',
        disponibilidad: 'false' // 'true' si está marcado
    };

    // ----------------------------------------------------
    // 1. Inicialización de Selectores Dinámicos
    // ----------------------------------------------------
    async function initializeFilters() {
        if (!window.fetchAndPopulateSelect) {
            console.error('Error: register_utils.js (fetchAndPopulateSelect) no está cargado.');
            return;
        }
        
        // A. Cargar Municipios
        await window.fetchAndPopulateSelect('get_municipios.php', 'municipioFilter', 'data', 'nombre', 'nombre', 'Cargando Municipios...');
        
        // CORRECCIÓN CLAVE 1: Añadir la opción "Todos los Municipios" al inicio
        const municipioSelect = document.getElementById('municipioFilter');
        if (municipioSelect) {
            const allMunOption = document.createElement('option');
            allMunOption.value = 'all'; 
            allMunOption.textContent = 'Todos los Municipios'; 
            
            if (municipioSelect.firstChild) {
                if (municipioSelect.firstChild.value === '') {
                    municipioSelect.replaceChild(allMunOption, municipioSelect.firstChild);
                } else {
                     municipioSelect.prepend(allMunOption);
                }
            } else {
                 municipioSelect.appendChild(allMunOption);
            }
            municipioSelect.value = 'all'; 
        }

        // B. Cargar Categorías
        await window.fetchAndPopulateSelect('get_categorias.php', 'categoryFilter', 'categorias', 'id_categoria', 'nombre_categoria', 'Cargando Categorías...');
        
        // CORRECCIÓN CLAVE 2: Añadir la opción "Todas las Categorías" al inicio
        const categorySelect = document.getElementById('categoryFilter');
        if (categorySelect) {
            const allCatOption = document.createElement('option');
            allCatOption.value = 'all'; 
            allCatOption.textContent = 'Todas las Categorías'; 
            
             if (categorySelect.firstChild) {
                if (categorySelect.firstChild.value === '') {
                    categorySelect.replaceChild(allCatOption, categorySelect.firstChild);
                } else {
                     categorySelect.prepend(allCatOption);
                }
            } else {
                 categorySelect.appendChild(allCatOption);
            }
            categorySelect.value = 'all'; 
        }
    }

    // ----------------------------------------------------
    // 2. Lógica de Filtrado y Búsqueda (Carga de Servicios)
    // ----------------------------------------------------
    
    function collectFilters() {
        currentFilters.search = searchInput.value.trim();
        currentFilters.categoria = categoryFilter.value; 
        currentFilters.municipio = municipioFilter.value;
        currentFilters.precio_rango = priceFilter.value;
        currentFilters.disponibilidad = availabilityFilter.checked ? 'true' : 'false';
    }

    async function loadServicesFromAPI() {
        if (window.showLoading) window.showLoading();

        collectFilters();
        
        const params = new URLSearchParams(currentFilters);
        const fetchUrl = `/ServiNet/api/get_all_services_public.php?${params.toString()}`;

        try {
            const response = await fetch(fetchUrl);
            
            if (!response.ok) {
                 throw new Error('Error HTTP: ' + response.status);
            }

            const data = await response.json();
            
            if (data.success && window.renderServiceCards) {
                // Renderiza los resultados (vacío si no hay, lleno si hay)
                window.renderServiceCards(data.services || []);
            } else {
                // Si success: false (Error de lógica en PHP)
                throw new Error(data.message || 'Error desconocido al cargar servicios.');
            }

        } catch (error) {
            console.error("Error al cargar servicios desde la API:", error);
            // Mostrar error solo si el renderer existe
            if (window.renderServiceCards) {
                 window.renderServiceCards([]); // Borra la lista
                 document.getElementById('emptyState').classList.add('hidden'); // Asegura que el estado vacío no se muestre por un error
            }
            // Muestra mensaje de error en la cuadrícula de forma explícita
            const grid = document.getElementById('servicesGrid');
            if (grid) {
                grid.innerHTML = '<p class="text-red-500 text-center col-span-full">Error de conexión con el servidor. Por favor, intente recargar la página.</p>';
                grid.classList.remove('hidden');
            }
            document.getElementById('totalServices').textContent = 'Error';
            document.getElementById('totalProfessionals').textContent = 'Error';

        } finally {
            if (window.hideLoading) window.hideLoading();
        }
    }
    
    // ----------------------------------------------------
    // 3. Asignación de Event Listeners
    // ----------------------------------------------------
    
    function attachEventListeners() {
        // Ejecutar búsqueda al cambiar cualquier filtro SELECT/CHECKBOX/BUTTON
        [categoryFilter, municipioFilter, priceFilter, availabilityFilter].forEach(el => {
            el.addEventListener('change', loadServicesFromAPI);
        });

        // Ejecutar búsqueda al presionar ENTER en el input o al usar el botón Aplicar
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                loadServicesFromAPI();
            }
        });

        applyFiltersBtn.addEventListener('click', loadServicesFromAPI);
        
        // Botón de limpiar búsqueda
        const resetFiltersBtn = document.getElementById('resetFilters');
        if (resetFiltersBtn) {
            resetFiltersBtn.addEventListener('click', function() {
                // Restablecer todos los filtros a 'all' o estado inicial
                searchInput.value = '';
                categoryFilter.value = 'all';
                municipioFilter.value = 'all';
                priceFilter.value = 'all';
                availabilityFilter.checked = false;
                clearSearchBtn.classList.add('hidden');
                
                loadServicesFromAPI();
            });
        }
        
        // Mostrar/Ocultar botón de limpiar búsqueda (solo para el campo de búsqueda principal)
        searchInput.addEventListener('input', () => {
             clearSearchBtn.classList.toggle('hidden', searchInput.value.trim() === '');
        });
        
        clearSearchBtn.classList.add('hidden');
    }

    // --- Ejecución Principal ---
    initializeFilters().then(() => {
        attachEventListeners();
        // Carga inicial de servicios
        loadServicesFromAPI();
    }).catch(error => {
        // Fallo crítico en la carga inicial de los selectores (municipios/categorías)
        console.error('Fallo en la inicialización de filtros:', error);
        document.getElementById('servicesGrid').innerHTML = '<p class="text-red-500 text-center col-span-full">Error al cargar filtros dinámicos. (Verifique register_utils.js)</p>';
        document.getElementById('servicesGrid').classList.remove('hidden');
    });
});