// =====================================================================
// === CORE: ANIMACIÓN Y CARGA DE CONTENIDO PRINCIPAL (INDEX.HTML) ===
// =====================================================================

// FUNCIÓN DE ANIMACIONES (Del index.js original - Se mantiene aquí ya que es específica del Index)
function initializeAnimations() {
    // ... (Toda la lógica de animaciones) ...
}


// FUNCIÓN PRINCIPAL DE CARGA DE CONTENIDO
function loadIndexContent() {
    
    const mainContent = document.getElementById('main-content');
    
    if (mainContent) {
        // Cargar Hero Section
        fetch('/ServiNet/components/sections/home-hero.html')
            .then(r => r.text())
            .then(d => {
                mainContent.innerHTML += d;
                
                // Cargar Features justo después del Hero
                return fetch('/ServiNet/components/sections/home-features.html');
            })
            .then(r => r.text())
            .then(d => {
                mainContent.innerHTML += d;
                
                // 🚨 ¡PUNTO CLAVE! EJECUTAR LA LÓGICA DE OCULTAMIENTO Y ANIMACIONES DESPUÉS DE LA CARGA
                if (window.hideIndexCTAs) {
                     window.hideIndexCTAs(); // Vuelve a revisar la sesión y oculta/muestra CTAs
                }
                initializeAnimations();
            })
            .catch(error => {
                console.error('Error al cargar secciones de la página de inicio:', error);
                mainContent.innerHTML = '<p class="text-center text-red-500 py-20">Error al cargar el contenido principal. Verifique los componentes.</p>';
            });
    }
}


// =====================================================================
// === EJECUCIÓN ===
// =====================================================================
document.addEventListener('DOMContentLoaded', function() {
    // NOTA: Si app.js ya corrió (y el header/footer se están cargando), 
    // ejecutamos la carga del contenido principal.
    loadIndexContent();
});