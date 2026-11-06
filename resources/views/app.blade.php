<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CargoTrace · Global Shipment Visibility &amp; Tracking</title>
    <meta
      name="description"
      content="Real-time supply chain and shipment visibility web application with GPS tracking, deterministic ETA delay detection, exception management, and corridor analytics."
    />
    <meta property="og:title" content="CargoTrace · Global Shipment Visibility &amp; Tracking" />
    <meta
      property="og:description"
      content="Real-time supply chain and shipment visibility web application with GPS tracking, deterministic ETA delay detection, exception management, and corridor analytics."
    />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin=""
    />
    @viteReactRefresh
    @vite(['src/main.tsx'])
  </head>
  <body class="bg-[#FFFFFF] text-[#080B0C] antialiased selection:bg-[#C6F432] selection:text-[#080B0C]">
    <div id="root"></div>
  </body>
</html>
