document.addEventListener('DOMContentLoaded', function() {
    // --- Funcionalidad del Acordeón (FAQ) ---
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        const header = item.querySelector('.faq-header');
        
        if (header) {
            header.addEventListener('click', () => {
                // Cierra todos los demás ítems
                const faqAccordion = item.closest('#faq-accordion');
                if (faqAccordion) {
                    const allItems = faqAccordion.querySelectorAll('.faq-item');
                    allItems.forEach(otherItem => {
                        if (otherItem !== item && otherItem.classList.contains('active')) {
                            otherItem.classList.remove('active');
                        }
                    });
                }

                // Abre o cierra el ítem actual
                item.classList.toggle('active');
            });
        }
    });
});