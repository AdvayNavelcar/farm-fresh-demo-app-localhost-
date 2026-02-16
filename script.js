// script.js

// Farm Fresh App - Modern JS for Quantity Selectors
document.addEventListener('DOMContentLoaded', () => {
    /**
     * Set allowed quantity increments for a product based on its category and unit type.
     * @param {HTMLSelectElement} selectElement
     * @param {string} category
     * @param {string} unitType
     */
    function setQuantityConstraints(selectElement, category, unitType) {
        let options = [];
        if (category === 'herb' || unitType === 'g') {
            // Herbs: 25g, 50g, 75g, 100g, then 100g steps up to 500g
            options = [25, 50, 75, 100, 200, 300, 400, 500].map(g => ({
                value: g / 1000,
                text: `${g} g`
            }));
        } else {
            // Fruits/Vegetables: 250g, 500g, 750g, 1kg, 2kg, 3kg, 4kg, 5kg
            options = [0.25, 0.5, 0.75, 1, 2, 3, 4, 5].map(kg => ({
                value: kg,
                text: `${kg} kg`
            }));
        }
        selectElement.innerHTML = '';
        for (const item of options) {
            const option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.text;
            selectElement.appendChild(option);
        }
    }

    /**
     * Initialize all product cards with quantity selectors.
     * Supports dynamic cards (future-proof for AJAX/live updates).
     */
    function initializeProductCards() {
        const cards = document.querySelectorAll('.product-card');
        cards.forEach(card => {
            const category = card.dataset.category;
            const unitType = card.dataset.unit;
            const select = card.querySelector('.quantity-select');
            if (select) {
                setQuantityConstraints(select, category, unitType);
            }
        });
    }

    // Initial setup
    initializeProductCards();

    // (Optional) If you add AJAX product loading, call initializeProductCards() after DOM update.
});