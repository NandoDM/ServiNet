// =====================================================================
// === FUNCIONES UTILITARIAS ===
// =====================================================================

// Función para mostrar alertas
function showAlert(message, type) {
    const existingAlert = document.querySelector('.custom-alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    const alert = document.createElement('div');
    // Usamos las clases genéricas de Tailwind
    alert.className = `custom-alert fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${
        type === 'error' ? 'bg-red-500 text-white' : 'bg-green-500 text-white'
    }`;
    alert.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'error' ? 'fa-exclamation-triangle' : 'fa-check-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

// Función para validar email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}


// =====================================================================
// === LÓGICA DE LOGIN ===
// =====================================================================
document.addEventListener('DOMContentLoaded', function() {
    
    // TOGGLE DE VISIBILIDAD DE CONTRASEÑA
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('contrasena');
    
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    }

    // Manejo del formulario de login - CONEXIÓN REAL CON PHP
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const correo = document.getElementById('correo').value;
            const contrasena = document.getElementById('contrasena').value;
            
            if (!correo || !contrasena) {
                showAlert('Por favor, completa todos los campos obligatorios.', 'error');
                return;
            }
            
            if (!isValidEmail(correo)) {
                showAlert('Por favor, ingresa un correo electrónico válido.', 'error');
                return;
            }
            
            const submitBtn = loginForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Usando la clase genérica auth-loading (definida en styles.css)
            submitBtn.innerHTML = '<div class="auth-loading mr-2"></div> Iniciando sesión...';
            submitBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('correo', correo);
            formData.append('contrasena', contrasena);

            try {
                // Enviar datos al servidor PHP
                const response = await fetch('/ServiNet/api/auth/login.php', {
                    method: 'POST',
                    body: formData 
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('¡Login exitoso! Redirigiendo...', 'success');
                    
                    // Guardar en localStorage
                    localStorage.setItem('user', JSON.stringify(data.user));
                    localStorage.setItem('isLoggedIn', 'true');
                    
                    // Redirigir al index después de 1.5 segundos
                    setTimeout(() => {
                        window.location.href = '/ServiNet/index.html';
                    }, 1500);
                    
                } else {
                    // Si falla, el PHP devuelve el mensaje de error.
                    showAlert(data.message || 'Error al iniciar sesión. Verifica tus credenciales.', 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
                
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error de conexión o respuesta inesperada del servidor.', 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    }

});