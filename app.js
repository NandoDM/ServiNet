// nandodm/servinet/ServiNet-e728ea1c53d2fa493dc4a4edd362433270b9cb26/assets/js/app.js

// =====================================================================
// === CORE: MANEJO DE ESTADO DE SESIÓN, SEGURIDAD Y CARGA GLOBAL ===
// =====================================================================

function logout() {
    localStorage.removeItem('isLoggedIn');
    localStorage.removeItem('user');
    window.location.replace('/ServiNet/index.html');
}

// 🚨 FUNCIÓN AGREGADA PARA ELIMINACIÓN DE CUENTA 🚨
async function requestDeleteConfirmation() {
    if (!confirm("ADVERTENCIA CRÍTICA: ¿Estás ABSOLUTAMENTE seguro de eliminar tu cuenta? Esta acción es PERMANENTE y borrará todos tus datos, citas e historial.")) {
        return;
    }

    // Se usa prompt simple para la demostración. En producción se recomienda un modal seguro.
    const password = prompt("Por favor, ingresa tu contraseña actual para confirmar la eliminación de tu cuenta:");

    if (!password) {
        alert("La eliminación ha sido cancelada o no se proporcionó la contraseña.");
        return;
    }
    
    // Preparar el envío
    const formData = new FormData();
    formData.append('password', password);

    try {
        const response = await fetch('/ServiNet/api/delete_account.php', {
            method: 'POST',
            body: formData 
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(`✅ ${result.message}`);
            // La API ya destruye la sesión, solo limpiamos el localStorage y redirigimos.
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('user');
            window.location.replace('/ServiNet/index.html');
        } else {
            alert(`❌ Error al intentar eliminar la cuenta: ${result.message}`);
        }
    } catch (error) {
        console.error('Delete error:', error);
        alert('Error de conexión con el servidor. Intenta nuevamente.');
    }
}
// 🚨 FIN FUNCIÓN AGREGADA 🚨


// ----------------------------------------------------
// FUNCIÓN AUXILIAR GLOBAL PARA OCULTAR CTAS (SOLUCIÓN AL ERROR DE SINCRONIZACIÓN)
// Debe ser llamada por index.js y initializeAboutPage() después de que el contenido se cargue.
// ----------------------------------------------------
window.hideIndexCTAs = function() {
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const heroGuestCta = document.getElementById('hero-guest-cta');
    const heroUserCta = document.getElementById('hero-user-cta');
    const ctaFinalSection = document.getElementById('cta-final-section');
    
    // Busca cualquier elemento con las clases/IDs que necesitan control universal
    const universalCtas = document.querySelectorAll('.cta-section-hide, .logged-in-hide, #roles-section');

    if (isLoggedIn) {
        // Ocultar CTAs específicas del Index (Hero y Final)
        if (heroGuestCta) heroGuestCta.classList.add('hidden'); 
        if (heroUserCta) heroUserCta.classList.remove('hidden'); 
        if (ctaFinalSection) ctaFinalSection.classList.add('hidden'); 
        
        // Ocultar CTAs universales (ej. en la página Acerca De)
        universalCtas.forEach(el => el.classList.add('hidden')); 
        
    } else {
        // Mostrar CTAs de Invitado si no está logueado
        if (heroUserCta) heroUserCta.classList.add('hidden'); 
        if (heroGuestCta) heroGuestCta.classList.remove('hidden'); 
        if (ctaFinalSection) ctaFinalSection.classList.remove('hidden');

        universalCtas.forEach(el => el.classList.remove('hidden'));
    }
}
// --------------------------------------------------------------------

async function renderAuthArea() {
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const navGuestArea = document.getElementById('nav-guest-area');
    const navUserArea = document.getElementById('nav-user-area');
    const path = window.location.pathname;
    const isDashboardPage = path.includes('/pages/dashboard/');
    
    // Seguridad de Ruta: Redirigir si no está logueado y está en Dashboard
    if (!isLoggedIn && isDashboardPage) {
        if (!path.includes('/pages/auth/login.html')) {
            window.location.replace('/ServiNet/pages/auth/login.html');
            return; 
        }
    }

    if (navGuestArea && navUserArea) {
        
        if (isLoggedIn) {
            navGuestArea.classList.add('hidden');
            navUserArea.classList.remove('hidden');

            try {
                // Carga del Menú de Usuario Logueado (Componente)
                const response = await fetch('/ServiNet/components/logged-in-menu.html');
                if (response.ok) {
                    const menuHtml = await response.text();
                    navUserArea.innerHTML = menuHtml;

                    const user = JSON.parse(localStorage.getItem('user'));
                    if (user) {
                        const firstName = user.nombre.split(' ')[0];
                        const role = user.rol ? user.rol.toLowerCase() : 'cliente';
                        const photoUrl = user.foto_perfil_url; // 🚨 Lee la URL de la foto 🚨
                        
                        document.getElementById('user-name-display').textContent = firstName;
                        document.getElementById('user-role-display').textContent = `Rol: ${role.charAt(0).toUpperCase() + role.slice(1)}`;

                        const initialsContainer = document.getElementById('user-initials-container'); // Contenedor DIV
                        const initialsSpan = document.getElementById('user-initials'); // Contenedor SPAN
                        
                        if (photoUrl && initialsContainer && initialsSpan) {
                            // Si hay foto, reemplazamos el contenido del DIV por la imagen
                            initialsContainer.innerHTML = `<img src="${photoUrl}" alt="Foto de Perfil" class="w-full h-full object-cover rounded-full">`;
                            initialsContainer.classList.remove('bg-blue-600', 'text-white');
                        } else if (initialsContainer && initialsSpan) {
                             // Si no hay foto, restauramos las iniciales por defecto
                             initialsSpan.textContent = firstName.charAt(0).toUpperCase();
                             initialsContainer.classList.add('bg-blue-600', 'text-white');
                        }


                        // Referencias a los bloques de opciones del menú desplegable
                        const clientOptions = document.getElementById('client-options');
                        const professionalOptions = document.getElementById('professional-options');
                        const adminOptions = document.getElementById('admin-options');
                        
                        // Ocultar todos los bloques al principio
                        if (clientOptions) clientOptions.classList.add('hidden');
                        if (professionalOptions) professionalOptions.classList.add('hidden');
                        if (adminOptions) adminOptions.classList.add('hidden');
                        
                        let targetDashboardPath = '/ServiNet/pages/dashboard/cliente/index.html'; // Default

                        // 🚨 LÓGICA DE ASIGNACIÓN DE DASHBOARD POR ROL 🚨
                        if (role === 'admin') {
                            if (adminOptions) adminOptions.classList.remove('hidden');
                            targetDashboardPath = '/ServiNet/pages/dashboard/admin/index.html'; 
                            document.getElementById('admin-dashboard-link').href = targetDashboardPath; 
                        } else if (role === 'profesional') {
                            if (professionalOptions) professionalOptions.classList.remove('hidden');
                            targetDashboardPath = '/ServiNet/pages/dashboard/profesional/index.html'; 
                            document.getElementById('prof-dashboard-link').href = targetDashboardPath; 
                        } else { // cliente
                            if (clientOptions) clientOptions.classList.remove('hidden');
                            document.getElementById('dashboard-link').href = targetDashboardPath; 
                        }
                        
                        // Redirección de seguridad (evita que un rol entre en el dashboard de otro)
                        if (isDashboardPage && !path.includes(role)) {
                            window.location.replace(targetDashboardPath);
                            return;
                        }

                        document.getElementById('logout-button').addEventListener('click', logout);
                        
                        // Lógica del TOGGLE del menú desplegable (Se mantiene integrada)
                        const userMenuButton = document.getElementById('user-menu-button');
                        const userMenuDropdown = document.getElementById('user-menu-dropdown');
                        
                        if (userMenuButton && userMenuDropdown) {
                            userMenuButton.addEventListener('click', function() {
                                userMenuDropdown.classList.toggle('hidden');
                            });
                            document.addEventListener('click', function(event) {
                                if (!userMenuDropdown.contains(event.target) && !userMenuButton.contains(event.target)) {
                                    userMenuDropdown.classList.add('hidden');
                                }
                            });
                        }
                    }
                }
            } catch (error) {
                console.error('Error cargando el menú del usuario:', error);
            }

        } else {
            // Estado No Logueado
            navUserArea.classList.add('hidden');
            navGuestArea.classList.remove('hidden');

            if (window.hideIndexCTAs) {
                 window.hideIndexCTAs();
            }
        }
    }
}

function initializeMobileMenu() {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }
}

function loadGlobalComponents() {
    // Cargar Header
    fetch('/ServiNet/components/header.html')
        .then(response => response.text())
        .then(data => {
            document.getElementById('header-container').innerHTML = data;
            renderAuthArea(); 
            initializeMobileMenu(); 
        })
        .catch(error => console.error('Error al cargar header:', error));

    // Cargar Footer
    fetch('/ServiNet/components/footer.html')
        .then(response => response.text())
        .then(data => {
            document.getElementById('footer-container').innerHTML = data;
        })
        .catch(error => console.error('Error al cargar footer:', error));
}


// =====================================================================
// === 2. LÓGICA DE PÁGINAS ESPECÍFICAS (Se mantiene consolidada) ===
// =====================================================================

// Lógica para el Toggle de Disponibilidad
function initializeAvailabilityToggle() {
    const toggle = document.getElementById('availabilityToggle');
    const statusText = document.getElementById('availability-status-text');
    const statusContainer = document.getElementById('availability-status-container');
    
    if (!toggle || !statusText || !statusContainer) return;
    
    // Función para actualizar el estado visual
    function updateVisualState(status) {
        const isAvailable = status === 'DISPONIBLE';
        toggle.checked = isAvailable;
        statusText.innerHTML = `Actualmente, tu perfil está: **${status}**`;
        
        statusContainer.classList.toggle('bg-green-50', isAvailable);
        statusContainer.classList.toggle('border-green-200', isAvailable);
        statusText.classList.toggle('text-green-700', isAvailable);
        
        statusContainer.classList.toggle('bg-red-50', !isAvailable);
        statusContainer.classList.toggle('border-red-200', !isAvailable);
        statusText.classList.toggle('text-red-700', !isAvailable);
        
    }

    // 1. Cargar estado inicial (GET request)
    async function loadInitialState() {
        try {
            const response = await fetch('/ServiNet/api/update_disponibilidad.php?method=GET');
            const result = await response.json();
            
            if (result.success) {
                updateVisualState(result.status);
            } else {
                console.error('Error al cargar disponibilidad:', result.message);
                updateVisualState('Error de Carga'); 
                toggle.disabled = true;
            }
        } catch (error) {
            console.error('Error de red al cargar disponibilidad:', error);
            updateVisualState('Error de Red');
            toggle.disabled = true;
        }
    }
    
    // 2. Manejar cambio de toggle (POST request)
    toggle.addEventListener('change', async function() {
        const newStatus = this.checked ? 'on' : 'off';
        const formData = new FormData();
        formData.append('status', newStatus);
        
        // Bloquear temporalmente
        toggle.disabled = true;
        
        try {
            const response = await fetch('/ServiNet/api/update_disponibilidad.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                updateVisualState(result.status);
                alert('✅ Disponibilidad actualizada a: ' + result.status);
            } else {
                alert('❌ Error al actualizar disponibilidad: ' + result.message);
                // Revertir el toggle visual si el guardado falla
                toggle.checked = !this.checked;
            }
        } catch (error) {
            alert('❌ Error de conexión al intentar guardar la disponibilidad.');
             // Revertir el toggle visual si el guardado falla
            toggle.checked = !this.checked;
        } finally {
            toggle.disabled = false;
        }
    });

    // Cargar el estado al iniciar
    loadInitialState();
}

// Lógica para el Perfil del Cliente
function initializeClientProfile() {
    const user = JSON.parse(localStorage.getItem('user'));
    if (user) {
        // CORRECCIÓN: Estos elementos ya no existen en el perfil.html final del cliente.
        // document.getElementById('nombre').value = user.nombre || '';
        // document.getElementById('email').value = user.correo || '';
    }

    // Los listeners de los formularios de perfil de cliente/profesional ya están en perfil.html
    
    // REMOVIDO: El listener del botón de eliminar se mueve al nav component
    // document.getElementById('delete-account-button')?.addEventListener('click', requestDeleteConfirmation);
}

// Lógica para el Perfil del Profesional
function initializeProfessionalProfile() {
    const user = JSON.parse(localStorage.getItem('user'));
    if (user) {
        document.getElementById('display-nombre').textContent = user.nombre || 'Nombre no especificado';
        document.getElementById('display-email').textContent = user.correo || 'correo@no-disponible.com';
    }

    document.querySelectorAll('.md\\:col-span-2 button.text-blue-600').forEach(button => {
        button.addEventListener('click', () => {
            alert('Iniciando modo de edición para esta sección (Funcionalidad de formulario).');
        });
    });
    
    document.querySelector('.bg-red-600')?.addEventListener('click', () => {
        alert('Abriendo modal para Cambiar Contraseña.');
    });
}

// Lógica de Inicialización de Dashboards (Común)
function initializeDashboard() {
    const user = JSON.parse(localStorage.getItem('user'));
    
    if (user && document.getElementById('dashboard-user-name')) {
        document.getElementById('dashboard-user-name').textContent = user.nombre.split(' ')[0]; 
    }

    const path = window.location.pathname;
    const dashboardNavContainer = document.getElementById('dashboard-nav-container');

    if (dashboardNavContainer) {
        let navComponentPath = '';
        if (path.includes('/dashboard/cliente/')) {
            navComponentPath = '/ServiNet/components/dashboard-nav-cliente.html';
        } else if (path.includes('/dashboard/profesional/')) {
            navComponentPath = '/ServiNet/components/dashboard-nav-profesional.html';
        } else if (path.includes('/dashboard/admin/')) { 
            navComponentPath = '/ServiNet/components/dashboard-nav-admin.html'; 
        }
        
        if (navComponentPath) {
            fetch(navComponentPath)
                .then(response => response.text())
                .then(data => {
                    dashboardNavContainer.innerHTML = data;
                })
                .catch(error => console.error('Error cargando navegación lateral:', error));
        }
    }
}

// Lógica para animaciones en Acerca De
function initializeAboutPage() {
    // 🚨 LLAMADA CLAVE: Ocultar CTAs inmediatamente
    window.hideIndexCTAs(); 
    
    const stepCards = document.querySelectorAll('.step-card');
    stepCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`; 
    });
}

// =====================================================================
// === INICIALIZACIÓN GLOBAL (Controlador de Páginas) ===
// =====================================================================
document.addEventListener('DOMContentLoaded', function() {
    loadGlobalComponents();
    
    const path = window.location.pathname;

    // 1. Controlador para Dashboards
    if (path.includes('/pages/dashboard/')) {
        initializeDashboard();
        
        if (path.includes('/perfil.html')) {
            // Se asegura que la lógica se corra solo en la página correcta
            if (path.includes('/cliente/')) {
                 // La carga de datos se hace directamente en perfil.html
            } else if (path.includes('/profesional/')) {
                initializeProfessionalProfile();
            }
        }
        
        // Aplica el toggle de disponibilidad en la Agenda y el Index del Profesional
        if (path.includes('/profesional/index.html') || path.includes('/profesional/agenda.html')) {
            initializeAvailabilityToggle();
        }
    }
    
    // 2. Controlador para páginas estáticas
    if (path.includes('/pages/acerca-de.html')) {
        initializeAboutPage();
    }
    
// CÓDIGO CLAVE DENTRO DE app.js:
// ...

function loadGlobalComponents() {
    // Cargar Header
    fetch('/ServiNet/components/header.html')
        .then(response => response.text())
        .then(data => {
            document.getElementById('header-container').innerHTML = data;
            renderAuthArea(); 
            initializeMobileMenu(); 
        })
        .catch(error => console.error('Error al cargar header:', error));

    // Cargar Footer
    fetch('/ServiNet/components/footer.html')
        .then(response => response.text())
        .then(data => {
            document.getElementById('footer-container').innerHTML = data;
        })
        .catch(error => console.error('Error al cargar footer:', error));
}


// ...
// === INICIALIZACIÓN GLOBAL (Controlador de Páginas) ===
// =====================================================================
document.addEventListener('DOMContentLoaded', function() {
    loadGlobalComponents(); // ESTA LÍNEA DEBE ESTAR AHÍ.
    
    // ... (restos de la lógica de app.js)
});});