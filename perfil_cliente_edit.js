// nandodm/servinet/ServiNet-e728ea1c53d2fa493dc4a4edd362433270b9cb26/assets/js/perfil_cliente_edit.js

// Referencias a elementos
const formPersonalInfo = document.getElementById('personal-info-form');
const msgPersonalInfo = document.getElementById('personal-info-message');
const passwordModal = document.getElementById('password-modal');
const openModalBtn = document.getElementById('open-password-modal');
const closeModalBtn = document.getElementById('close-password-modal');
const passwordForm = document.getElementById('password-form');
const passwordMsg = document.getElementById('password-message');

// 🚨 Referencias para la Foto 🚨
const photoInput = document.getElementById('photo-input');
const photoPreview = document.getElementById('profile-photo-preview');
const initialsDisplay = document.getElementById('profile-initials-display');
const photoMessage = document.getElementById('photo-message');
const emailDisplay = document.getElementById('display-email');
const nameDisplay = document.getElementById('display-nombre-completo');


// --- FUNCIONES DE UTILIDAD ---

function decodeHtmlEntities(text) {
    if (!text) return '';
    // Usa DOMParser para decodificar las entidades HTML como &ntilde;
    const parser = new DOMParser();
    const doc = parser.parseFromString(text, 'text/html');
    return doc.documentElement.textContent;
}

function displayMessage(element, success, message) {
    element.classList.remove('hidden', 'bg-red-100', 'bg-green-100', 'text-red-700', 'text-green-700');
    if (success) {
        element.classList.add('bg-green-100', 'text-green-700');
    } else {
        element.classList.add('bg-red-100', 'text-red-700');
    }
    element.innerHTML = message;
}

function loadFormData(formEl, formData) {
    Object.keys(formData).forEach(key => {
        const input = formEl.elements[key];
        if (input) {
            // 🔑 APLICAR DECODIFICACIÓN ANTES DE ASIGNAR AL CAMPO
            const decodedValue = decodeHtmlEntities(formData[key]);
            input.value = decodedValue || ''; 
        }
    });
}

/**
 * Muestra la foto o las iniciales del usuario.
 */
function updateProfilePhotoDisplay(photoUrl, initials) {
    if (photoUrl) {
        if (photoPreview) {
            photoPreview.src = photoUrl;
            photoPreview.classList.remove('hidden');
        }
        if (initialsDisplay) initialsDisplay.classList.add('hidden');
    } else {
        if (photoPreview) photoPreview.classList.add('hidden');
        if (initialsDisplay) {
            initialsDisplay.classList.remove('hidden');
            initialsDisplay.textContent = initials;
        }
    }
}


// --- LÓGICA DE CARGA DE DATOS (APIs) ---

async function loadMunicipalities(selectedMunicipio) {
    const municipioSelect = document.getElementById('municipio');
    if (window.fetchAndPopulateSelect) {
        await fetchAndPopulateSelect('/ServiNet/api/get_municipios.php', 'municipio', 'data', 'nombre', 'nombre', 'Selecciona tu Municipio');
        // 🔑 DECODIFICAR EL MUNICIPIO SELECCIONADO ANTES DE ASIGNARLO
        const decodedMunicipio = decodeHtmlEntities(selectedMunicipio);
        if (municipioSelect && municipioSelect.querySelector(`option[value="${decodedMunicipio}"]`)) {
             municipioSelect.value = decodedMunicipio; 
        }
    } else if (municipioSelect) {
        municipioSelect.innerHTML = '<option value="">Error: Falta register_utils.js</option>';
    }
}

async function loadProfileData() {
    let currentMunicipio = '';
    
    try {
        const response = await fetch('/ServiNet/api/get_client_profile.php');
        const result = await response.json();

        if (result.success && result.data) {
            const data = result.data;
            currentMunicipio = data.municipio; 
            
            // 1. Cargar datos de la Cuenta
            // Usamos decodeHtmlEntities para el nombre completo en la cabecera
            const decodedNombre = decodeHtmlEntities(data.nombre);
            const decodedApellido = decodeHtmlEntities(data.apellido_paterno);

            const initials = decodedNombre.charAt(0).toUpperCase() + decodedApellido.charAt(0).toUpperCase();
            
            if (emailDisplay) emailDisplay.textContent = data.correo || 'N/A';
            if (nameDisplay) nameDisplay.textContent = `${decodedNombre} ${decodedApellido}`;
            
            // 🚨 CARGA DE FOTO DE PERFIL EXISTENTE 🚨
            updateProfilePhotoDisplay(data.foto_perfil_url, initials); 

            const personalData = {
                nombre: data.nombre,
                apellido_paterno: data.apellido_paterno,
                apellido_materno: data.apellido_materno,
                telefono: data.telefono,
            };
            // 🔑 loadFormData DECODIFICARÁ LOS VALORES
            if (formPersonalInfo) loadFormData(formPersonalInfo, personalData);
            
        } else {
            if (emailDisplay) emailDisplay.textContent = `Error al cargar datos: ${result.message || 'Respuesta de API inválida.'}`;
            const user = JSON.parse(localStorage.getItem('user'));
            if (nameDisplay) nameDisplay.textContent = user ? user.nombre : 'Usuario';
        }
    } catch (error) {
        if (emailDisplay) emailDisplay.textContent = 'Error de conexión con la API.';
    }
    
    await loadMunicipalities(currentMunicipio);
}

// 🚨 LÓGICA DE SUBIDA DE FOTO (MODIFICADO) 🚨
async function handlePhotoUpload(e) {
    const file = e.target.files[0];
    if (!file) return;

    // 1. Mostrar vista previa local
    const reader = new FileReader();
    reader.onload = (event) => {
        if (photoPreview) {
            photoPreview.src = event.target.result;
            photoPreview.classList.remove('hidden');
        }
        if (initialsDisplay) initialsDisplay.classList.add('hidden');
    };
    reader.readAsDataURL(file);

    // 2. Preparar y enviar datos (Misma API que el profesional)
    const formData = new FormData();
    formData.append('profile_photo', file);
    
    const uploadLabel = document.querySelector('label[for="photo-input"]');
    const originalText = uploadLabel ? uploadLabel.innerHTML : '';
    if (uploadLabel) uploadLabel.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Subiendo...';
    
    if (photoMessage) photoMessage.classList.add('hidden');

    try {
        const response = await fetch('/ServiNet/api/upload_profile_photo.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            displayMessage(photoMessage, true, result.message);
            
            // 🔑 CORRECCIÓN CLAVE: Actualizar localStorage y recargar el menú
            const user = JSON.parse(localStorage.getItem('user'));
            if (user && result.photo_url) {
                user.foto_perfil_url = result.photo_url; 
                localStorage.setItem('user', JSON.stringify(user));
                
                // Forzar la recarga del componente de navegación
                if (window.renderAuthArea) {
                     window.renderAuthArea(); 
                }
            }
            
        } else {
            displayMessage(photoMessage, false, result.message);
            // Si falla, recarga los datos para restaurar la foto anterior
            loadProfileData(); 
        }

    } catch (error) {
        displayMessage(photoMessage, false, 'Error de conexión con la API de subida.');
        loadProfileData(); 
    } finally {
        if (uploadLabel) uploadLabel.innerHTML = originalText;
    }
}
// ------------------------------------

async function handleProfileUpdate(e) {
    e.preventDefault();
    
    const submitBtn = e.submitter;
    const originalText = submitBtn.innerHTML;
    
    let formData = new FormData(formPersonalInfo);
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Guardando...';
    submitBtn.disabled = true;

    try {
        const response = await fetch('/ServiNet/api/update_client_profile.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            const nombre = formData.get('nombre');
            const apellido_paterno = formData.get('apellido_paterno');
            document.getElementById('display-nombre-completo').textContent = `${nombre} ${apellido_paterno}`;
            displayMessage(msgPersonalInfo, true, result.message);
            
            // Actualizar solo el nombre/apellido en localStorage (la foto se mantiene)
            const user = JSON.parse(localStorage.getItem('user'));
            if (user) {
                user.nombre = nombre;
                // No actualizamos el apellido en el objeto, pero actualizamos la visualización del menú
                localStorage.setItem('user', JSON.stringify(user));
                if (window.renderAuthArea) {
                     window.renderAuthArea(); 
                }
            }
            
            loadProfileData(); 
        } else {
            displayMessage(msgPersonalInfo, false, result.message);
        }
        
    } catch (error) {
        displayMessage(msgPersonalInfo, false, 'Error de conexión con el servidor.');
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

// Lógica del modal de contraseña
if (openModalBtn && passwordModal) {
    openModalBtn.addEventListener('click', () => {
        passwordModal.classList.remove('hidden');
        passwordModal.classList.add('flex');
        passwordMsg.classList.add('hidden'); 
        passwordForm.reset(); 
    });
    
    closeModalBtn.addEventListener('click', () => {
        passwordModal.classList.add('hidden');
        passwordModal.classList.remove('flex');
    });
    
    passwordModal.addEventListener('click', (e) => {
        if (e.target === passwordModal) {
             passwordModal.classList.add('hidden');
             passwordModal.classList.remove('flex');
        }
    });
}

if (passwordForm) {
     passwordForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Actualizando...';
        submitBtn.disabled = true;

        const formData = new FormData(this);
        
        try {
            const response = await fetch('/ServiNet/api/update_client_password.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            displayMessage(passwordMsg, result.success, result.message);

            if (result.success) {
                setTimeout(() => {
                    if (window.logout) { 
                         window.logout(); 
                    } else {
                         window.location.replace('/ServiNet/pages/auth/login.html'); 
                    }
                }, 3000);
            }
            
        } catch (error) {
            displayMessage(passwordMsg, false, 'Error de conexión con el servidor.');
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
}


// --- INICIALIZACIÓN ---
document.addEventListener('DOMContentLoaded', function() {
    if (formPersonalInfo) formPersonalInfo.addEventListener('submit', handleProfileUpdate);
    if (photoInput) photoInput.addEventListener('change', handlePhotoUpload);
    
    loadProfileData();
});