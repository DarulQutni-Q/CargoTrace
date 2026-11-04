import L from 'leaflet';

// The tracking screen's world view: dark OSM-derived tiles, one dotted
// point per shipment endpoint, a great-circle arc per active shipment,
// and the yellow live marker on each shipment's latest known position [S9].

const TILES_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}';
const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &middot; Tiles &copy; <a href="https://www.esri.com/">Esri</a> (World Dark Gray Canvas)';

const STATUS_COLORS = {
    CREATED: '#9ba0aa',
    BOOKED: '#fcd34d',
    PICKED_UP: '#2dd4bf',
    IN_TRANSIT: '#60a5fa',
    DELIVERED: '#34d399',
};

function greatCirclePoints(from, to, steps = 48) {
    const toRad = (deg) => (deg * Math.PI) / 180;
    const toDeg = (rad) => (rad * 180) / Math.PI;

    const [lat1, lon1] = [toRad(from[0]), toRad(from[1])];
    const [lat2, lon2] = [toRad(to[0]), toRad(to[1])];

    const d = 2 * Math.asin(
        Math.sqrt(
            Math.sin((lat2 - lat1) / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin((lon2 - lon1) / 2) ** 2,
        ),
    );

    if (d === 0 || Number.isNaN(d)) {
        return [from, to];
    }

    const points = [];
    for (let i = 0; i <= steps; i += 1) {
        const f = i / steps;
        const a = Math.sin((1 - f) * d) / Math.sin(d);
        const b = Math.sin(f * d) / Math.sin(d);
        const x = a * Math.cos(lat1) * Math.cos(lon1) + b * Math.cos(lat2) * Math.cos(lon2);
        const y = a * Math.cos(lat1) * Math.sin(lon1) + b * Math.cos(lat2) * Math.sin(lon2);
        const z = a * Math.sin(lat1) + b * Math.sin(lat2);
        points.push([toDeg(Math.atan2(z, Math.sqrt(x * x + y * y))), toDeg(Math.atan2(y, x))]);
    }

    return points;
}

function bearingDeg(from, to) {
    const toRad = (deg) => (deg * Math.PI) / 180;
    const [lat1, lat2] = [toRad(from[0]), toRad(to[0])];
    const dLon = toRad(to[1] - from[1]);
    const y = Math.sin(dLon) * Math.cos(lat2);
    const x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLon);

    return (Math.atan2(y, x) * 180) / Math.PI;
}

function pointPopup(shipment, kind, point) {
    return `
        <div class="min-w-44">
            <p class="font-mono text-xs text-white/90">${shipment.shipment_number}</p>
            <p class="mt-1 text-[11px] text-white/70">${kind}: ${point.location_name}</p>
            ${point.time ? `<p class="font-mono text-[10px] text-white/50">${point.time}</p>` : ''}
        </div>
    `;
}

function initMap() {
    const container = document.getElementById('world-map');

    if (!container) {
        return;
    }

    const map = L.map(container, {
        worldCopyJump: true,
        attributionControl: true,
        zoomControl: false,
    }).setView([20, 20], 2);

    L.control.zoom({ position: 'bottomright' }).addTo(map);
    L.tileLayer(TILES_URL, { attribution: ATTRIBUTION, maxZoom: 10, minZoom: 2 }).addTo(map);

    const payload = JSON.parse(container.dataset.shipments || '[]');
    const focusId = Number(container.dataset.focus || 0);
    const bounds = [];

    payload.forEach((shipment) => {
        const color = STATUS_COLORS[shipment.status] ?? '#9ba0aa';
        const dim = focusId !== 0 && shipment.id !== focusId ? 0.35 : 1;

        if (shipment.origin && shipment.destination) {
            L.circleMarker([shipment.origin.latitude, shipment.origin.longitude], {
                radius: 5, color: '#ffffff', weight: 1.5, fillColor: '#0a0b0d', fillOpacity: 1, opacity: dim,
            }).addTo(map)
                .bindPopup(pointPopup(shipment, 'Origin', shipment.origin))
                .bindTooltip(`From ${shipment.origin.location_name}`, { direction: 'bottom', className: 'ct-tooltip' });

            L.circleMarker([shipment.destination.latitude, shipment.destination.longitude], {
                radius: 5, color: color, weight: 1.5, fillColor: color, fillOpacity: 1, opacity: dim,
            }).addTo(map)
                .bindPopup(pointPopup(shipment, 'Destination', shipment.destination))
                .bindTooltip(`Dest ${shipment.destination.location_name}`, { direction: 'top', className: 'ct-tooltip' });

            L.polyline(greatCirclePoints(
                [shipment.origin.latitude, shipment.origin.longitude],
                [shipment.destination.latitude, shipment.destination.longitude],
            ), { color: '#ffffff', weight: 1.25, opacity: 0.3 * dim }).addTo(map);

            bounds.push([shipment.origin.latitude, shipment.origin.longitude]);
            bounds.push([shipment.destination.latitude, shipment.destination.longitude]);
        }

        if (shipment.current_position) {
            const position = [shipment.current_position.latitude, shipment.current_position.longitude];
            const heading = shipment.previous_position
                ? bearingDeg(
                    [shipment.previous_position.latitude, shipment.previous_position.longitude],
                    position,
                )
                : 0;

            const icon = L.divIcon({
                className: '',
                html: `<div class="ct-plane ${focusId !== 0 && shipment.id !== focusId ? 'ct-plane-dim' : ''}" style="transform: rotate(${heading}deg)">
                         <svg width="18" height="18" viewBox="0 0 24 24" fill="${shipment.id === focusId ? '#facc15' : color}"
                              stroke="#0a0b0d" stroke-width="1">
                           <path d="M10.5 20.5 12 22l1.5-1.5v-4.6l6.5 2v-2.4L13.5 12V5.4a1.5 1.5 0 0 0-3 0V12L4 15.5v2.4l6.5-2v4.6z"/>
                         </svg>
                       </div>`,
                iconSize: [18, 18],
                iconAnchor: [9, 9],
            });

            L.marker(position, { icon, zIndexOffset: 500 })
                .addTo(map)
                .bindPopup(pointPopup(shipment, 'Current position', shipment.current_position));

            bounds.push(position);
        }
    });

    if (focusId !== 0 && bounds.length > 0) {
        const focused = payload.find((shipment) => shipment.id === focusId);
        const focusBounds = [];

        if (focused?.origin) focusBounds.push([focused.origin.latitude, focused.origin.longitude]);
        if (focused?.destination) focusBounds.push([focused.destination.latitude, focused.destination.longitude]);
        if (focused?.current_position) focusBounds.push([focused.current_position.latitude, focused.current_position.longitude]);

        map.fitBounds(focusBounds.length > 0 ? focusBounds : bounds, { padding: [60, 60], maxZoom: 5 });
    } else if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [50, 50], maxZoom: 4 });
    }

    return map;
}

initMap();
