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
| Blade Lucide Icons (mallardduck/blade-lucide-icons) | MIT | https://github.com/mallardduck/blade-lucide-icons | Blade components for the Lucide icon set (the only icon set in the UI) |
| Leaflet | BSD-2-Clause | https://leafletjs.com | Interactive world map on the tracking screen |
| Chart.js | MIT | https://www.chartjs.org | Dashboard charts (shipments by status/carrier) |
| Esri World Dark Gray Canvas tiles | Esri terms (attribution required) | https://www.arcgis.com/home/item.html?id=2afe5b083d53490d8c7f44ea8f66fbf9 | Dark basemap rendered on OpenStreetMap-derived data; CARTO's dark basemap now requires an API key, Esri's canvas is key-free and policy-compliant with attribution |
| OpenStreetMap Nominatim | ODbL (data) | https://nominatim.org | Geocoding service for the location form, used through the Laravel HTTP Client with a proper user agent |

## Frontend

| What | License | Source | Used for |
| --- | --- | --- | --- |
| Tailwind CSS | MIT | https://tailwindcss.com | Utility styling; design tokens from DESIGN.md map into its theme |
| Vite | MIT | https://vite.dev | Asset bundling, Hot Module Replacement during development |
| laravel-vite-plugin | MIT | https://github.com/laravel/vite-plugin | Vite/Laravel integration, Blade directive, font handling |
| IBM Plex Sans / IBM Plex Mono | SIL OFL 1.1 | https://github.com/IBM/plex | Interface and data typography (loaded through Bunny Fonts at build time) |
| Lucide | ISC | https://lucide.dev | Icon artwork rendered through the Blade package above |

## Data & infrastructure

| What | License | Source | Used for |
| --- | --- | --- | --- |
| MySQL 8.4 | GPL v2 / FOSS exception | https://www.mysql.com | Primary database (runs in the `mysql` Docker image locally) |
| PHPUnit | BSD-3-Clause | https://phpunit.de | Test framework shipped with Laravel |
| Docker Compose | Apache-2.0 | https://docs.docker.com/compose | Local MySQL service definition |

