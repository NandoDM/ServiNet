// nandodm/servinet/ServiNet-e728ea1c53d2fa493dc4a4edd362433270b9cb26/assets/js/chat.js

let pollingInterval; // Variable global para el temporizador de polling

/**
 * Función auxiliar para obtener el ID de cuenta del usuario logueado desde localStorage.
 */
function getCurrentUserId() {
    try {
        const user = JSON.parse(localStorage.getItem('user'));
        return user ? user.id_cuenta : null;
    } catch (e) {
        return null;
    }
}

/**
 * Renderiza un mensaje individual.
 */
function renderMessage(message) {
    // is_emisor = true significa que el mensaje es del usuario logueado (el que lo está viendo)
    const isEmisor = message.is_emisor; 
    const messageClass = isEmisor ? 'client-message' : 'professional-message';
    const timeClass = isEmisor ? 'text-blue-100' : 'text-gray-500';
    
    return `
        <div class="message-box ${messageClass}">
            ${message.contenido}
            <div class="text-xs ${timeClass} text-right mt-1">${message.fecha_envio}</div>
        </div>
    `;
}

/**
 * Realiza scroll al final solo si la ventana ya está cerca del final.
 */
function autoScroll(container) {
     if (container) {
         // Si el usuario está a menos de 50px del fondo, forzar scroll
         const isScrolledToBottom = container.scrollHeight - container.clientHeight <= container.scrollTop + 50;
         
         if (isScrolledToBottom) {
             container.scrollTop = container.scrollHeight;
         }
     }
}

/**
 * Carga el historial de chat y los datos del contacto.
 * Si initialLoad=false, solo recarga la lista.
 */
async function loadChatData(targetId, initialLoad = true) {
    const messagesContainer = document.getElementById('messages-container');
    const targetNameDisplay = document.getElementById('target-name-display');
    const targetInitials = document.getElementById('target-initials');
    
    if (initialLoad) {
        messagesContainer.innerHTML = '<div class="text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando historial de mensajes...</div>';
    }

    try {
        // Usamos cache-buster para asegurar datos frescos durante el polling
        const cacheBuster = initialLoad ? '' : '&_=' + new Date().getTime();
        const response = await fetch(`/ServiNet/api/get_chat_data.php?target_id=${targetId}${cacheBuster}`);
        
        if (!response.ok) {
             throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const result = await response.json();

        if (result.success && result.target) {
            const target = result.target;
            
            // 1. Actualizar cabecera (Solo en la carga inicial)
            if (initialLoad) {
                 targetInitials.textContent = target.initials;
                 targetNameDisplay.textContent = `Chat con ${target.full_name}`;
            }
            
            // 2. Cargar mensajes
            const currentScrollTop = messagesContainer.scrollTop;
            const currentScrollHeight = messagesContainer.scrollHeight;
            
            messagesContainer.innerHTML = '';
            
            if (result.messages && result.messages.length > 0) {
                 result.messages.forEach(message => {
                    messagesContainer.innerHTML += renderMessage(message);
                });
                
                // Mantiene la posición del scroll si no es la carga inicial o si el usuario estaba al fondo
                if (initialLoad) {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                } else {
                    autoScroll(messagesContainer);
                }
            } else if (initialLoad) {
                 messagesContainer.innerHTML = '<div class="text-center text-gray-500 p-4">Inicia la conversación. No hay mensajes aún.</div>';
            }


        } else {
            messagesContainer.innerHTML = `<div class="text-center text-red-500">Error: ${result.message}</div>`;
            clearInterval(pollingInterval); // Detener polling si hay error grave
        }
    } catch (error) {
        console.error('Error de red/servidor al cargar chat:', error);
        if (initialLoad) {
            messagesContainer.innerHTML = '<div class="text-center text-red-500">Error de conexión con el servidor. (GET)</div>';
        }
        clearInterval(pollingInterval); // Detener polling si hay error de conexión
    }
}

/**
 * Maneja el envío del formulario a la API.
 */
async function handleMessageSend(e, targetId) {
    e.preventDefault();
    
    const input = document.getElementById('message-input');
    const contenido = input.value.trim();
    
    if (contenido === '') return;

    // Deshabilitar input y botón
    const sendButton = document.querySelector('#chat-send-form button[type="submit"]');
    input.disabled = true;
    sendButton.disabled = true;

    // 🚨 VERIFICACIÓN DE AUTENTICACIÓN 🚨
    const emisorId = getCurrentUserId();
    if (!emisorId) {
         alert(`ERROR: Debes iniciar sesión para enviar mensajes. Tu ID de Emisor es nulo.`);
         input.disabled = false;
         sendButton.disabled = false;
         window.location.href = '/ServiNet/pages/auth/login.html';
         return;
    }

    const formData = new FormData();
    formData.append('target_id', targetId);
    formData.append('contenido', contenido);

    try {
        const response = await fetch('/ServiNet/api/send_message.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();

        if (result.success) {
            input.value = '';
            // Recargar para mostrar el mensaje enviado y forzar scroll
            await loadChatData(targetId, false); 
            const messagesContainer = document.getElementById('messages-container');
            if (messagesContainer) messagesContainer.scrollTop = messagesContainer.scrollHeight;

        } else {
            alert('Error al enviar: ' + result.message);
        }
    } catch (error) {
        console.error('Fallo al enviar el mensaje:', error);
        alert('Error de conexión al intentar enviar el mensaje. (POST)');
    } finally {
        input.disabled = false;
        sendButton.disabled = false;
        input.focus();
    }
}

// ----------------------------------------------------
// INICIALIZACIÓN PRINCIPAL
// ----------------------------------------------------
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const targetId = urlParams.get('prof') || urlParams.get('client');

    if (!targetId) {
        const messagesContainer = document.getElementById('messages-container');
        if(messagesContainer) {
            messagesContainer.innerHTML = '<div class="text-center text-red-500">Error: ID de contacto no especificado en la URL. Asegúrate de hacer clic en el botón de Chat desde un perfil.</div>';
        }
        return;
    }
    
    // 1. Cargar los datos iniciales
    loadChatData(targetId, true);
    
    // 2. Iniciar Polling (Recargar cada 3 segundos)
    // 💡 Aquí se inicia el temporizador de Polling 💡
    pollingInterval = setInterval(() => {
        loadChatData(targetId, false); 
    }, 3000); // 3000ms = 3 segundos
    
    // 3. Asignar el manejador de envío al formulario
    const sendForm = document.getElementById('chat-send-form');
    if (sendForm) {
         sendForm.addEventListener('submit', (e) => handleMessageSend(e, targetId));
    }
    
    // Opcional: Detener el polling si el usuario navega fuera o cierra la ventana
    window.addEventListener('beforeunload', () => {
        clearInterval(pollingInterval);
    });
});