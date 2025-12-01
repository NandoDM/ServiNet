// =====================================================================
// === FUNCIONES UTILITARIAS ===
// =====================================================================

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Función auxiliar para mostrar alertas (puedes usar esta simple si no tienes un sistema de alerta global)
function showAlert(message, type) {
    // Implementación simple de alert, o puedes inyectar un div de alerta como en login.js
    alert(`${type.toUpperCase()}: ${message}`); 
}

// =====================================================================
// === LÓGICA DE LA PÁGINA DE CONTACTO ===
// =====================================================================
document.addEventListener('DOMContentLoaded', function() {
    
    // ----------------------------------------------------
    // A. FAQ Accordion Logic
    // ----------------------------------------------------
    const faqQuestions = document.querySelectorAll('.faq-question');
    faqQuestions.forEach(question => {
        question.addEventListener('click', function() {
            const answer = this.nextElementSibling;
            const icon = this.querySelector('i.fa-chevron-down, i.fa-chevron-up');
            
            // Cerrar otras respuestas
            faqQuestions.forEach(otherQuestion => {
                if (otherQuestion !== this) {
                    const otherAnswer = otherQuestion.nextElementSibling;
                    const otherIcon = otherQuestion.querySelector('i.fa-chevron-down, i.fa-chevron-up');
                    
                    otherAnswer.classList.add('hidden');
                    otherIcon.classList.replace('fa-chevron-up', 'fa-chevron-down');
                }
            });
            
            // Abrir/Cerrar la respuesta actual
            answer.classList.toggle('hidden');
            
            if (answer.classList.contains('hidden')) {
                icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
            } else {
                icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
            }
        });
    });
    
    // ----------------------------------------------------
    // B. Contador de Caracteres
    // ----------------------------------------------------
    const mensajeTextarea = document.getElementById('mensaje');
    const charCount = document.getElementById('charCount');
    
    if (mensajeTextarea && charCount) {
        mensajeTextarea.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = `${length}/500 caracteres`;
            
            if (length > 450) {
                charCount.classList.remove('text-gray-500');
                charCount.classList.add('text-orange-500');
            } else {
                charCount.classList.remove('text-orange-500');
                charCount.classList.add('text-gray-500');
            }
        });
    }

    // ----------------------------------------------------
    // C. Manejo del Formulario de contacto
    // ----------------------------------------------------
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Recolección y Validación de datos
            const nombre = document.getElementById('nombre').value.trim();
            const email = document.getElementById('email').value.trim();
            const asunto = document.getElementById('asunto').value;
            const mensaje = document.getElementById('mensaje').value.trim();
            
            if (!nombre || !email || !asunto || !mensaje) {
                showAlert('Por favor completa todos los campos obligatorios', 'error');
                return;
            }
            
            if (!isValidEmail(email)) {
                showAlert('Por favor ingresa un email válido', 'error');
                return;
            }
            
            if (mensaje.length < 10) {
                showAlert('El mensaje debe tener al menos 10 caracteres', 'error');
                return;
            }
            
            // Simular envío
            const submitBtn = contactForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enviando...';
            submitBtn.disabled = true;
            
            // 🚨 Aquí iría la lógica fetch() real a la API de contacto 🚨
            // Ejemplo: fetch('/ServiNet/api/contact.php', { method: 'POST', body: new FormData(this) })
            
            setTimeout(() => {
                showAlert('Mensaje enviado correctamente. Te contactaremos en menos de 24 horas.', 'success');
                contactForm.reset();
                if (charCount) charCount.textContent = '0/500 caracteres';
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 2000);
        });
    }
});