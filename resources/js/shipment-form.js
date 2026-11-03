// Repeating shipment item rows: add from the template, remove with the
// trash button, keeping at least one row on the form.
document.querySelectorAll('[data-add-item]').forEach((button) => {
    button.addEventListener('click', () => {
        const template = document.querySelector('[data-item-template]');
        const container = document.querySelector('[data-item-rows]');

        if (template && container) {
            container.appendChild(template.content.cloneNode(true));
        }
    });
});

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-remove-row]');

    if (!trigger) {
        return;
    }

    const container = trigger.closest('[data-item-rows]');
    const row = trigger.closest('[data-item-row]');

    if (container && row && container.querySelectorAll('[data-item-row]').length > 1) {
        row.remove();
    }
});
