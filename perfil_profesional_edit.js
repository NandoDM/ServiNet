// nandodm/servinet/ServiNet-48ba9527754bd129e6df928d75d5a7bfac0a7456/perfil_profesional_edit.js

// Referencias a elementos
const formPersonalInfo = document.getElementById('personal-info-form');
const msgPersonalInfo = document.getElementById('personal-info-message');
const formProfDetails = document.getElementById('professional-details-form');
const msgProfDetails = document.getElementById('professional-details-message');
const passwordModal = document.getElementById('password-modal');
const openModalBtn = document.getElementById('open-password-modal');
const closeModalBtn = document.getElementById('close-password-modal');
const passwordForm = document.getElementById('password-form');
const passwordMsg = document.getElementById('password-message');

// 🔑 ELEMENTOS DE LA FOTO DE PERFIL
const photoInput = document.getElementById('photo-input');
const photoPreview = document.getElementById('profile-photo-preview');
const initialsDisplay = document.getElementById('profile-initials-display');
const photoMessage = document.getElementById('photo-message');
const emailDisplay = document.getElementById('display-email');
const nameDisplay = document.getElementById('display-nombre-completo');

// 🚀 ELEMENTOS DEL PORTAFOLIO
const portfolioUploadForm = document.getElementById('portfolio-upload-form');
const portfolioMessage = document.getElementById('portfolio-message');
const portfolioListContainer = document.getElementById('portfolio-list-container');


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
    const photoContainer = document.getElementById('prof-avatar-container');
    
    if (!photoContainer) return;
    
    photoContainer.innerHTML = ''; 

    if (photoUrl) {
        photoContainer.innerHTML = `<img src="${photoUrl}" alt="Foto de Perfil" class="w-full h-full object-cover">`;
        photoContainer.classList.remove('bg-blue-600', 'text-white');
    } else {
        const initialsStyle = `
            font-size: 3rem; 
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        photoContainer.innerHTML = `<span style="${initialsStyle}">${initials}</span>`;
        photoContainer.classList.add('bg-blue-600'); 
        photoContainer.classList.remove('bg-gray-200');
    }
}


// --- LÓGICA DE CARGA DE DATOS (API: get_profesional_profile.php) ---

async function loadMunicipalities(selectedMunicipio) {
    const municipioSelect = document.getElementById('municipio');
    // Usamos la función global de register_utils.js
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
        const response = await fetch('/ServiNet/api/get_profesional_profile.php');
        const result = await response.json();

        if (result.success && result.data) {
            const data = result.data;
            currentMunicipio = data.municipio; 
            
            // 1. Cargar datos de la Cuenta
            // 🔑 DECODIFICAR DATOS PARA LA CABECERA
            const decodedNombre = decodeHtmlEntities(data.nombre);
            const decodedApellido = decodeHtmlEntities(data.apellido_paterno);
            
            const nombreParts = decodedNombre.split(/\s+/);
            const firstNameInitial = nombreParts.length > 0 ? nombreParts[0].charAt(0).toUpperCase() : '';
            const apellidoPaternoInitial = decodedApellido.charAt(0).toUpperCase();
            const initials = firstNameInitial + apellidoPaternoInitial;
            
            if (emailDisplay) emailDisplay.textContent = data.correo || 'N/A';
            if (nameDisplay) nameDisplay.textContent = `${decodedNombre} ${decodedApellido}`; // Muestra el nombre decodificado

            // 🔑 CARGA DE FOTO DE PERFIL EXISTENTE
            updateProfilePhotoDisplay(data.foto_perfil_url, initials); 

            const personalData = {
                nombre: data.nombre, // El valor original (codificado) se carga en el input y se decodifica en loadFormData
                apellido_paterno: data.apellido_paterno,
                apellido_materno: data.apellido_materno,
                telefono: data.telefono,
                // municipio se carga después de la lista
            };
            if (formPersonalInfo) loadFormData(formPersonalInfo, personalData);

            // 2. Cargar datos del PerfilProfesional (solo si existen)
            if (data.id_profesional) {
                const profData = {
                    especialidades: data.especialidades,
                    experiencia: data.experiencia,
                    descripcion: data.descripcion
                };
                if (formProfDetails) loadFormData(formProfDetails, profData);
            }
            
        } else {
            // Muestra el mensaje de error del API 
            if (emailDisplay) emailDisplay.textContent = `Error al cargar datos: ${result.message || 'Respuesta de API inválida.'}`;
            const user = JSON.parse(localStorage.getItem('user'));
            if (nameDisplay) nameDisplay.textContent = user ? user.nombre : 'Usuario';
        }
    } catch (error) {
        if (emailDisplay) emailDisplay.textContent = 'Error de conexión con la API.';
    }
    
    // Cargar los municipios al final
    await loadMunicipalities(currentMunicipio);
}

// 🔑 LÓGICA DE SUBIDA DE FOTO DE PERFIL
async function handlePhotoUpload(e) {
    const file = e.target.files[0];
    if (!file) return;

    // 1. Mostrar vista previa local
    const reader = new FileReader();
    reader.onload = (event) => {
        updateProfilePhotoDisplay(event.target.result, ''); 
    };
    reader.readAsDataURL(file);

    // 2. Preparar y enviar datos 
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
            
            const user = JSON.parse(localStorage.getItem('user'));
            if (user && result.photo_url) {
                user.foto_perfil_url = result.photo_url; 
                localStorage.setItem('user', JSON.stringify(user));
                
                if (window.renderAuthArea) {
                     window.renderAuthArea(); 
                }
            }
            
        } else {
            displayMessage(photoMessage, false, result.message);
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

// 🚀 LÓGICA DE GESTIÓN DEL PORTAFOLIO 🚀

/**
 * Renderiza la lista de fotos del portafolio en el dashboard.
 */
function renderPortfolioList(photos) {
    if (!portfolioListContainer) return;

    if (photos.length === 0) {
        portfolioListContainer.innerHTML = '<p class="text-gray-500 col-span-full">Aún no has subido ninguna foto de trabajo.</p>';
        return;
    }

    let html = '';
    photos.forEach(photo => {
        const desc = decodeHtmlEntities(photo.descripcion) || 'Sin descripción'; // Decodificar descripción
        html += `
            <div class="portfolio-image-card rounded-lg shadow-md aspect-square bg-gray-100">
                <img src="${photo.url_imagen}" alt="${desc}" class="w-full h-full object-cover rounded-lg">
                <div class="delete-overlay">
                    <button onclick="deletePortfolioPhoto(${photo.id_foto})" data-id-foto="${photo.id_foto}" class="bg-red-600 text-white p-3 rounded-full hover:bg-red-700 transition shadow-lg">
                        <i class="fas fa-trash-alt"></i> Eliminar
                    </button>
                </div>
            </div>
        `;
    });

    portfolioListContainer.innerHTML = html;
}

/**
 * Carga la lista de fotos del portafolio desde el API.
 */
async function loadPortfolioPhotos() {
    if (!portfolioListContainer) return;
    portfolioListContainer.innerHTML = '<p class="text-gray-500 col-span-full"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando fotos...</p>';

    try {
        const response = await fetch('/ServiNet/api/get_portfolio_photos.php');
        const result = await response.json();

        if (result.success) {
            renderPortfolioList(result.photos);
        } else {
            portfolioListContainer.innerHTML = `<p class="text-red-500 col-span-full">Error al cargar el portafolio: ${result.message}</p>`;
        }
    } catch (error) {
        portfolioListContainer.innerHTML = `<p class="text-red-500 col-span-full">Error de conexión al cargar el portafolio.</p>`;
    }
}

/**
 * Maneja la eliminación de una foto específica (función global).
 */
window.deletePortfolioPhoto = async function(id_foto) {
    if (!confirm('¿Estás seguro de que quieres eliminar permanentemente esta foto del portafolio?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('id_foto', id_foto);
    
    try {
        displayMessage(portfolioMessage, false, `<i class="fas fa-spinner fa-spin mr-2"></i> Eliminando foto #${id_foto}...`);

        const response = await fetch('/ServiNet/api/delete_portfolio_photo.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            displayMessage(portfolioMessage, true, result.message);
            loadPortfolioPhotos(); // Recargar la lista de fotos
        } else {
            displayMessage(portfolioMessage, false, result.message);
        }
    } catch (error) {
        displayMessage(portfolioMessage, false, 'Error de conexión al intentar eliminar la foto.');
    }
};

/**
 * Maneja la subida de una nueva foto al portafolio.
 */
async function handlePortfolioUpload(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('upload-portfolio-btn');
    const originalText = submitBtn.innerHTML;
    
    const fileInput = document.getElementById('portfolio_photo');
    if (fileInput.files.length === 0) {
        displayMessage(portfolioMessage, false, 'Debes seleccionar una foto para el portafolio.');
        return;
    }
    
    const formData = new FormData(portfolioUploadForm);

    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Subiendo...';
    submitBtn.disabled = true;

    try {
        const response = await fetch('/ServiNet/api/add_portfolio_photo.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            displayMessage(portfolioMessage, true, result.message);
            portfolioUploadForm.reset();
            loadPortfolioPhotos(); // Recargar la lista al subir con éxito
        } else {
            displayMessage(portfolioMessage, false, result.message);
        }
        
    } catch (error) {
        displayMessage(portfolioMessage, false, 'Error de conexión con el servidor.');
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}
// ------------------------------------


// --- MANEJO DE ENVÍO DE DATOS (Perfil Personal y Profesional) ---

async function handleProfileUpdate(e) {
    e.preventDefault();
    
    const formId = e.target.id;
    const isPersonalInfoUpdate = (formId === 'personal-info-form');
    
    const submitBtn = e.submitter;
    const originalText = submitBtn.innerHTML;
    
    // 1. Unir datos de ambos formularios (se hace por seguridad aunque se envíen juntos)
    let formData = new FormData(formPersonalInfo);
    new FormData(formProfDetails).forEach((value, key) => {
        formData.set(key, value);
    });

    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Guardando...';
    submitBtn.disabled = true;

    try {
        const response = await fetch('/ServiNet/api/update_profesional_profile.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        const messageElement = isPersonalInfoUpdate ? msgPersonalInfo : msgProfDetails;
        
        if (result.success) {
            const nombre = formData.get('nombre');
            const apellido_paterno = formData.get('apellido_paterno');
            document.getElementById('display-nombre-completo').textContent = `${nombre} ${apellido_paterno}`;
            
            const user = JSON.parse(localStorage.getItem('user'));
            if (user) {
                user.nombre = nombre;
                localStorage.setItem('user', JSON.stringify(user));
                if (window.renderAuthArea) {
                     window.renderAuthArea(); 
                }
            }

            displayMessage(messageElement, true, result.message);
            loadProfileData(); 
        } else {
            displayMessage(messageElement, false, result.message);
        }
        
    } catch (error) {
        displayMessage(isPersonalInfoUpdate ? msgPersonalInfo : msgProfDetails, false, 'Error de conexión con el servidor.');
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

// --- LÓGICA DE MODAL Y CONTRASEÑA ---

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
            const response = await fetch('/ServiNet/api/update_profesional_password.php', {
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
    // Asignar listeners a los formularios de perfil (Personal y Profesional)
    if (formPersonalInfo) formPersonalInfo.addEventListener('submit', handleProfileUpdate);
    if (formProfDetails) formProfDetails.addEventListener('submit', handleProfileUpdate);
    
    // 🔑 ASIGNAR LISTENERS PARA FOTO DE PERFIL Y PORTAFOLIO
    if (photoInput) photoInput.addEventListener('change', handlePhotoUpload);
    if (portfolioUploadForm) portfolioUploadForm.addEventListener('submit', handlePortfolioUpload);
    
    // Cargar los datos iniciales
    loadProfileData();
    loadPortfolioPhotos(); // Cargar lista de fotos al inicio
});