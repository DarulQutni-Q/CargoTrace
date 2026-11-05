# Attribution

Every library, tool, and external source used by CargoTrace, recorded as the
brief requires. Each member must be able to explain what each entry does and
where the project's own code begins.

## Application

| What | License | Source | Used for |
| --- | --- | --- | --- |
| Laravel | MIT | https://laravel.com | Application framework: routing, Eloquent, validation, authentication primitives |
| Blade (part of Laravel) | MIT | https://laravel.com | View templating |
| Laravel Sanctum | MIT | https://laravel.com/docs/sanctum | API token authentication for the REST surface |
| Leaflet | BSD-2-Clause | https://leafletjs.com | Interactive world map on the shipments and tracking screens |
| Chart.js | MIT | https://www.chartjs.org | Dashboard charts (shipments by status/carrier) |
| OpenStreetMap tiles | ODbL (data) | https://tile.openstreetmap.org | Basemap tiles for the Leaflet maps, with attribution shown on the map |
| OpenStreetMap Nominatim | ODbL (data) | https://nominatim.org | Geocoding service for the location form, used through the Laravel HTTP Client with a proper user agent |
| Open-Meteo | CC BY 4.0 (data) | https://open-meteo.com | Informational weather for origin/destination on the shipment detail page, through the Laravel HTTP Client (5 s timeout, fallback labelled "cached") |

## Frontend

| What | License | Source | Used for |
| --- | --- | --- | --- |
| Tailwind CSS | MIT | https://tailwindcss.com | Utility styling, loaded from CDN (`cdn.tailwindcss.com`); no build step |
| Plus Jakarta Sans / JetBrains Mono | SIL OFL 1.1 | https://fonts.google.com | Interface and data typography, loaded from Google Fonts |

## Data & infrastructure

| What | License | Source | Used for |
| --- | --- | --- | --- |
| MySQL 8.0 | GPL v2 / FOSS exception | https://www.mysql.com | Primary database (runs in the `mysql:8.0` Docker image) |
| PHPUnit | BSD-3-Clause | https://phpunit.de | Test framework shipped with Laravel |
| Docker | Apache-2.0 | https://docs.docker.com | Local MySQL container (`docker run`, no Compose file) |
