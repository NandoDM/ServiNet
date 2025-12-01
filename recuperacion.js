// nandodm/servinet/ServiNet-35fb085a9f887b792b3d4ac23d22f72cd494ceec/assets/js/recuperacion.js

// Se reutiliza la función showAlert del login.js para mantener la coherencia
function showAlert(message, type) {
    const existingAlert = document.querySelector('.custom-alert');
    if (existingAlert) existingAlert.remove();
    
    const alert = document.createElement('div');
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
    setTimeout(() => { alert.remove(); }, 6000);
}

// Función para validar email (reutilizada)
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}


document.addEventListener('DOMContentLoaded', function() {
    const recuperacionForm = document.getElementById('recuperacionForm');
    
    if (recuperacionForm) {
        recuperacionForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const correo = document.getElementById('correo').value;
            
            if (!correo || !isValidEmail(correo)) {
                showAlert('Por favor, ingresa un correo electrónico válido.', 'error');
                return;
            }
            
            const submitBtn = recuperacionForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<div class="auth-loading mr-2"></div> Solicitando...';
            submitBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('correo', correo);

            try {
                // Llamada al nuevo endpoint de recuperación
                const response = await fetch('/ServiNet/api/auth/recuperacion.php', {
                    method: 'POST',
                    body: formData 
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('✅ ' + data.message, 'success');
                    recuperacionForm.reset();
                } else {
                    showAlert('Error: ' + (data.message || 'Fallo en la conexión.'), 'error');
                }
                
            } catch (error) {
                console.error('Recuperation error:', error);
                showAlert('Error de conexión con el servidor. Intenta nuevamente.', 'error');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    }
});