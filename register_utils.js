// nandodm/servinet/ServiNet-35fb085a9f887b792b3d4ac23d22f72cd494ceec/assets/js/register_utils.js

// =====================================================================
// === UTILS Y MANEJO DE CATÁLOGOS (Reutilizables en varios formularios) ===
// =====================================================================

// 🔑 NUEVA FUNCIÓN: Decodifica entidades HTML (ej: &iacute; -> í)
function decodeHtmlEntities(text) {
    if (!text) return '';
    const parser = new DOMParser();
    const doc = parser.parseFromString(text, 'text/html');
    return doc.documentElement.textContent;
}

// Función genérica para obtener datos de catálogos y poblar un SELECT
// arrayKey: la clave del objeto JSON que contiene el array de datos (ej: 'data', 'categorias')
async function fetchAndPopulateSelect(endpoint, selectId, arrayKey, valueKey, textKey) {
    const selectElement = document.getElementById(selectId);
    if (!selectElement) return;

    try {
        // 🚨 CORRECCIÓN DE RUTA APLICADA: Forzamos el fetch a usar la ruta completa de la API
        const apiUrl = endpoint.includes('municipios') 
                        ? '/ServiNet/api/get_municipios.php' 
                        : '/ServiNet/api/get_categorias.php';
        
        const response = await fetch(apiUrl);
        const data = await response.json();

        selectElement.innerHTML = `<option value="" disabled selected>Cargando...</option>`;

        if (data.success && data[arrayKey] && data[arrayKey].length > 0) {
            
            const labelText = selectId.includes('municipio') ? 'municipio' : 'categoría';
            selectElement.innerHTML = `<option value="" disabled selected>Selecciona tu ${labelText} *</option>`;

            data[arrayKey].forEach(item => {
                const option = document.createElement('option');
                
                // 🔑 CORRECCIÓN CLAVE: Decodificar tanto el valor (value) como el texto visible
                const decodedValue = decodeHtmlEntities(item[valueKey]);
                const decodedText = decodeHtmlEntities(item[textKey]);
                
                option.value = decodedValue; 
                option.textContent = decodedText;
                selectElement.appendChild(option);
            });
        } else {
            // Si la respuesta es success:true pero el array está vacío (ej: no hay categorías activas)
            selectElement.innerHTML = `<option value="" disabled>No hay datos disponibles</option>`;
        }
    } catch (error) {
        // Captura el error de red o de PHP y lo muestra
        console.error('Error de red al cargar catálogo ' + endpoint, error);
        selectElement.innerHTML = `<option value="" disabled>Error de carga</option>`;
    }
}

// =====================================================================
// === LÓGICA DE INTERFAZ Y VALIDACIÓN (Compartida por Cliente y Profesional) ===
// =====================================================================

// Inicializa toggles y validación en tiempo real
function setupFormInteractions() {
    // ----------------------------------------------------
    // TOGGLES DE CONTRASEÑA
    // ----------------------------------------------------
    const togglePassword = document.getElementById('togglePassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const passwordInput = document.getElementById('contrasena');
    const confirmPasswordInput = document.getElementById('confirmar_contrasena');
    
    // Función de alternancia universal
    const setupToggle = (button, input) => {
        if (button && input) {
            button.addEventListener('click', function() {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }
    };

    setupToggle(togglePassword, passwordInput);
    setupToggle(toggleConfirmPassword, confirmPasswordInput);
    
    // ----------------------------------------------------
    // VALIDACIÓN EN TIEMPO REAL
    // ----------------------------------------------------
    
    // Validar coincidencia de contraseña
    function validatePasswordMatch() {
        if (!passwordInput || !confirmPasswordInput) return true;
        
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        const errorElement = document.getElementById('confirmPasswordError');
        
        if (confirmPassword && password !== confirmPassword) {
            errorElement.textContent = 'Las contraseñas no coinciden';
            return false;
        } else {
            errorElement.textContent = '';
            return true;
        }
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', validatePasswordMatch);
    }
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', validatePasswordMatch);
    }
    
    // Validar Teléfono (patrón básico)
    const telefonoInput = document.getElementById('telefono');
    if (telefonoInput) {
        telefonoInput.addEventListener('blur', function() {
            const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
            const errorElement = document.getElementById('telefonoError');
            if (this.value && !phoneRegex.test(this.value.replace(/\s/g, ''))) {
                errorElement.textContent = 'Por favor ingresa un número de teléfono válido';
            } else {
                errorElement.textContent = '';
            }
        });
    }

    // Validación Específica para la Descripción (solo Profesional)
    const descripcionInput = document.getElementById('descripcion');
    if (descripcionInput) {
        descripcionInput.addEventListener('input', function() {
            const errorElement = document.getElementById('descripcionError');
            if (this.value.length < 50 && this.value.length > 0) {
                errorElement.textContent = `Mínimo 50 caracteres. Faltan ${50 - this.value.length}`;
                this.classList.add('border-red-500');
            } else {
                errorElement.textContent = '';
                this.classList.remove('border-red-500');
            }
        });
    }
}

// =====================================================================
// === LÓGICA DE ENVÍO DE FORMULARIO (Manejo de Transacción a PHP) ===
// =====================================================================

function setupFormSubmission() {
    const registrationForm = document.getElementById('registrationForm');
    if (!registrationForm) return;
    
    const registerBtn = document.getElementById('registerBtn');
    const isProfessional = window.location.pathname.includes('registro-profesional.html');
    
    registrationForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // 1. VALIDACIÓN FINAL DEL LADO DEL CLIENTE
        
        if (!this.checkValidity()) {
            alert('Por favor, completa todos los campos obligatorios.');
            return;
        }

        const password = document.getElementById('contrasena')?.value;
        const confirmPassword = document.getElementById('confirmar_contrasena')?.value;
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
        
        if (password !== confirmPassword || !passwordRegex.test(password)) {
            alert('Asegúrate de que las contraseñas coincidan y cumplan los requisitos de seguridad.');
            return;
        }

        if (isProfessional) {
            // Validación de campo obligatorio: Categoría Principal
            const categoria = document.getElementById('id_categoria_principal')?.value;
            if (!categoria) {
                 alert('Por favor, selecciona tu Categoría Principal.');
                 return;
            }

            const descripcion = document.getElementById('descripcion')?.value;
            if (descripcion && descripcion.length < 50) {
                alert('La descripción profesional debe tener al menos 50 caracteres.');
                return;
            }
        }
        
        // 2. PROCESAMIENTO Y ENVÍO
        const originalText = registerBtn.innerHTML;
        registerBtn.innerHTML = '<div class="auth-loading mr-2"></div> Creando cuenta...';
        registerBtn.disabled = true;

        const endpoint = isProfessional 
            ? '/ServiNet/api/auth/register_profesional.php' 
            : '/ServiNet/api/auth/register_cliente.php';
            
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: new FormData(registrationForm) 
            });
            
            const result = await response.json();
            
            if (result.success) {
                localStorage.setItem('isLoggedIn', 'true');
                localStorage.setItem('user', JSON.stringify(result.user));
                alert('¡Cuenta creada exitosamente! Redirigiendo...');
                
                const targetPath = isProfessional 
                    ? '/ServiNet/pages/dashboard/profesional/index.html'
                    : '/ServiNet/index.html'; // Redirigir al index para el cliente
                    
                window.location.href = targetPath;
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Registration error:', error);
            alert('Error de conexión con el servidor. Intenta nuevamente.');
        } finally {
            registerBtn.innerHTML = originalText;
            registerBtn.disabled = false;
        }
    });
}

// =====================================================================
// === LÓGICA DE SELECCIÓN DE CUENTA (Manejada en este mismo archivo) ===
// =====================================================================

function initializeAccountSelection() {
    const clienteCard = document.querySelector('.client-card');
    const profesionalCard = document.querySelector('.professional-card');
    const continueBtn = document.getElementById('continueBtn');
    
    // Si no estamos en la página de selección, salimos
    if (!clienteCard || !profesionalCard) return; 

    const clienteCheckmark = document.getElementById('clienteCheckmark');
    const profesionalCheckmark = document.getElementById('profesionalCheckmark');
    const continueText = document.getElementById('continueText');
    let selectedAccountType = '';
    
    // Solo necesitamos que la tarjeta esté seleccionada para habilitar el botón
    const updateButtonState = () => {
        const accountSelected = selectedAccountType !== '';
        
        if (accountSelected) {
            continueBtn.disabled = false; // <--- SE HABILITA EL BOTÓN
        } else {
            continueBtn.disabled = true;
        }
    }

    const resetSelection = () => {
        clienteCard.classList.remove('selected');
        profesionalCard.classList.remove('selected');
        clienteCheckmark.classList.remove('selected');
        profesionalCheckmark.classList.remove('selected');
    };

    const updateContinueButton = (accountType) => {
        selectedAccountType = accountType;
        continueText.innerHTML = `<i class="fas fa-arrow-right mr-3"></i> Continuar como ${accountType.charAt(0).toUpperCase() + accountType.slice(1)}`;
        updateButtonState(); // Habilitar el botón inmediatamente al seleccionar
    };

    // Usamos addEventListener en la tarjeta (div.client-card)
    clienteCard.addEventListener('click', () => {
        resetSelection();
        clienteCard.classList.add('selected');
        clienteCheckmark.classList.add('selected');
        updateContinueButton('cliente');
    });

    // Usamos addEventListener en la tarjeta (div.professional-card)
    profesionalCard.addEventListener('click', () => {
        resetSelection();
        profesionalCard.classList.add('selected');
        profesionalCheckmark.classList.add('selected');
        updateContinueButton('profesional');
    });
    
    // Inicializar el botón en disabled
    updateButtonState();

    // Listener para el botón "Continuar"
    continueBtn.addEventListener('click', () => {
        if (selectedAccountType === 'cliente') {
            window.location.href = '/ServiNet/pages/auth/registro-cliente.html';
        } else if (selectedAccountType === 'profesional') {
            window.location.href = '/ServiNet/pages/auth/registro-profesional.html';
        }
    });
    
    // Restaurar el texto estático de términos si fue modificado en la versión anterior.
    const termsContainer = document.querySelector('.text-center.mt-12 .text-gray-500'); 
    if (termsContainer) {
        termsContainer.innerHTML = `
            Al crear una cuenta, aceptas nuestros 
            <a href="/ServiNet/pages/terminos-servicio.html" class="text-blue-600 hover:underline font-medium">Términos de Servicio</a> 
            y 
            <a href="/ServiNet/pages/politica-privacidad.html" class="text-blue-600 hover:underline font-medium">Política de Privacidad</a>
        `;
    }
}


// =====================================================================
// === INICIALIZACIÓN DE MÓDULOS AL CARGAR EL DOM ===
// =====================================================================

document.addEventListener('DOMContentLoaded', function() {
    const path = window.location.pathname;

    // Lógica para la página de Selección de Cuenta
    if (path.includes('seleccion-cuenta.html')) {
        initializeAccountSelection();
    }
    
    // Lógica para las páginas de Registro
    if (path.includes('registro-cliente.html') || path.includes('registro-profesional.html')) {
        // 1. Carga de catálogos dinámicos
        // MUNICIPOS: ArrayKey: 'data' (del JSON), Value/Text: 'nombre' (que es el nombre_municipio)
        fetchAndPopulateSelect('get_municipios.php', 'municipio', 'data', 'nombre', 'nombre'); 
        
        // Solo para registro profesional
        if (path.includes('registro-profesional.html')) {
            // 🚨 CÓDIGO CORREGIDO 🚨
            // ArrayKey: 'categorias' (del JSON), Value: 'id_categoria', Text: 'nombre_categoria'
            fetchAndPopulateSelect('get_categorias.php', 'id_categoria_principal', 'categorias', 'id_categoria', 'nombre_categoria'); 
        }

        // 2. Configuración de interacción (toggles, validación en tiempo real)
        setupFormInteractions();
        
        // 3. Configuración de envío (submit)
        setupFormSubmission();
    }
});