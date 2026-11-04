// Geocode helper on the location form: fills the coordinate fields from
// the external provider and says so plainly when it cannot [S9].
const geocodeButton = document.querySelector('[data-geocode]');

if (geocodeButton) {
    geocodeButton.addEventListener('click', async () => {
        const name = document.querySelector('#name')?.value ?? '';
        const city = document.querySelector('#city')?.value ?? '';
        const status = document.querySelector('[data-geocode-status]');
        const query = [name, city].filter(Boolean).join(', ');

        if (!query) {
            return;
        }

        geocodeButton.disabled = true;
        status.textContent = 'Looking up coordinates...';

        try {
            const response = await fetch(`${geocodeButton.dataset.geocodeUrl}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
            });
            const result = await response.json();

            if (result.found) {
                document.querySelector('#latitude').value = result.latitude;
                document.querySelector('#longitude').value = result.longitude;
                status.textContent = `Found: ${result.display_name}`;
            } else {
                status.textContent = result.message ?? 'Geocoding unavailable — enter the coordinates manually.';
            }
        } catch {
            status.textContent = 'Geocoding unavailable — enter the coordinates manually.';
        } finally {
            geocodeButton.disabled = false;
        }
    });
}
