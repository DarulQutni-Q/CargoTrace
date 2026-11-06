/* CargoTrace 3D globe — vanilla canvas port of Globe3DCanvas.tsx (commit 46db38f).
 *
 * No framework, no WebGL: orthographic projection on a 2D canvas, the same
 * simplified land polygons, elevated great-circle arcs (solid completed leg,
 * dashed remaining leg, surface shadow under the focused corridor), volt
 * checkpoint badges, drag to rotate, wheel/buttons to zoom, auto-spin, and
 * click-to-focus on the live vessel marker. Theme follows the document
 * `dark` class, same as the Leaflet map (re-rendered on `ct:theme`).
 *
 * Wiring (Blade): CargoTraceGlobe.mount(globeContainer, entries, focusId,
 * onSelect) + CargoTraceGlobe.initProjection({...}) for the 2D/3D switcher.
 * Projection preference persists in sessionStorage under
 * `cargotrace_map_projection_v1` ('2d' | '3d'), same key as the React build.
 */
(function () {
  'use strict';

  var WORLD_LAND_POLYGONS = [
  // Sumatra (Aceh: Banda Aceh, Sabang, Bireuen, Lhokseumawe, Krueng Geukuh -> Lampung)
  [
    [5.8, 95.2],
    [5.3, 97.1],
    [3.8, 98.7],
    [1.7, 101.4],
    [-0.5, 103.6],
    [-3.0, 104.8],
    [-5.9, 105.8],
    [-5.5, 104.2],
    [-3.8, 102.2],
    [-1.0, 100.3],
    [2.0, 98.0],
    [4.1, 96.1],
    [5.8, 95.2],
  ],
  // Java (Merak, Jakarta, Semarang, Surabaya, Banyuwangi)
  [
    [-6.0, 106.0],
    [-6.1, 106.9],
    [-6.9, 110.4],
    [-7.2, 112.8],
    [-8.2, 114.4],
    [-8.7, 114.0],
    [-7.8, 110.4],
    [-7.0, 106.5],
    [-6.0, 106.0],
  ],
  // Kalimantan (Borneo)
  [
    [7.0, 116.8],
    [4.2, 117.9],
    [1.0, 118.9],
    [-1.3, 116.8],
    [-3.4, 114.6],
    [-3.0, 111.7],
    [-0.1, 109.3],
    [2.0, 109.6],
    [4.9, 114.9],
    [7.0, 116.8],
  ],
  // Sulawesi
  [
    [1.5, 125.2],
    [0.5, 123.0],
    [-1.5, 121.5],
    [-5.1, 119.4],
    [-5.5, 120.5],
    [-4.0, 122.6],
    [-1.0, 123.5],
    [1.5, 125.2],
  ],
  // Papua / New Guinea
  [
    [-0.8, 131.2],
    [-2.5, 140.7],
    [-6.5, 147.8],
    [-10.2, 150.5],
    [-9.0, 143.0],
    [-8.0, 138.0],
    [-4.5, 134.0],
    [-0.8, 131.2],
  ],
  // Sri Lanka
  [
    [9.8, 80.0],
    [8.5, 81.2],
    [6.0, 80.2],
    [6.9, 79.8],
    [9.8, 80.0],
  ],
  // UK & Ireland
  [
    [58.6, -5.0],
    [57.5, -1.8],
    [51.5, 1.4],
    [50.1, -5.2],
    [54.5, -5.9],
    [58.6, -5.0],
  ],
  // Japan
  [
    [45.5, 141.9],
    [40.0, 140.0],
    [35.6, 140.8],
    [31.2, 130.6],
    [34.0, 131.0],
    [38.0, 139.0],
    [45.5, 141.9],
  ],
  // Eurasia (Western Europe: Rotterdam/Hamburg -> Mediterranean -> Middle East -> India -> SE Asia -> China -> Siberia)
  [
    [36.0, -9.0],
    [43.3, -8.4],
    [48.4, -4.5],
    [51.0, 1.8],
    [51.9, 4.1], // Rotterdam
    [53.6, 10.0], // Hamburg
    [57.7, 10.6],
    [60.0, 5.0],
    [70.0, 25.0],
    [68.0, 40.0],
    [72.0, 70.0],
    [73.0, 120.0],
    [66.0, 170.0],
    [55.0, 160.0],
    [43.0, 132.0],
    [38.9, 121.6],
    [31.2, 121.5], // Shanghai
    [22.3, 114.2], // Hong Kong
    [20.8, 106.7],
    [10.8, 106.7],
    [13.7, 100.7], // Bangkok
    [1.3, 103.8], // Singapore / Malay Peninsula
    [6.5, 100.1],
    [16.8, 96.2],
    [22.3, 91.8],
    [21.5, 87.0],
    [13.1, 80.3],
    [8.1, 77.5], // Southern India
    [19.0, 72.8], // Mumbai
    [24.9, 67.0], // Karachi
    [25.5, 57.0],
    [29.9, 48.5],
    [25.0, 55.1], // Dubai / Jebel Ali
    [22.5, 59.5],
    [12.8, 45.0], // Aden
    [29.9, 32.5], // Suez
    [32.0, 34.8],
    [36.8, 36.2],
    [41.0, 29.0],
    [38.0, 23.7],
    [40.8, 14.3],
    [43.3, 5.4],
    [36.0, -5.4],
    [36.0, -9.0],
  ],
  // Africa (Tangier -> Suez / Port Said -> Horn of Africa -> Cape of Good Hope -> West Africa)
  [
    [35.8, -5.8],
    [37.3, 9.8],
    [31.3, 32.3], // Port Said
    [11.8, 43.4],
    [11.8, 51.3],
    [-4.0, 39.7],
    [-25.9, 32.6],
    [-34.4, 18.5],
    [-22.9, 14.5],
    [-6.1, 12.4],
    [4.0, 9.7],
    [6.4, 3.4],
    [5.3, -4.0],
    [14.7, -17.4],
    [28.0, -13.0],
    [35.8, -5.8],
  ],
  // Australia
  [
    [-12.5, 130.8],
    [-12.0, 142.5],
    [-19.2, 146.8],
    [-27.5, 153.0],
    [-33.9, 151.2],
    [-38.1, 144.9],
    [-35.0, 138.5],
    [-34.0, 115.0],
    [-32.0, 115.8],
    [-21.9, 114.1],
    [-18.0, 122.2],
    [-12.5, 130.8],
  ],
  // North America
  [
    [70.0, -160.0],
    [70.0, -70.0],
    [47.5, -52.7],
    [40.7, -74.0],
    [25.8, -80.2],
    [19.2, -96.1],
    [8.9, -79.5],
    [16.8, -99.9],
    [34.0, -118.2],
    [49.3, -123.1],
    [61.2, -149.9],
    [70.0, -160.0],
  ],
  // South America
  [
    [10.5, -75.5],
    [10.6, -66.9],
    [-1.4, -48.5],
    [-8.0, -34.9],
    [-23.0, -43.2],
    [-34.6, -58.4],
    [-54.8, -68.3],
    [-41.5, -73.0],
    [-18.5, -70.3],
    [-12.0, -77.0],
    [-2.2, -79.9],
    [10.5, -75.5],
  ],
];

  var DEG2RAD = Math.PI / 180;
  var STORAGE_KEY = 'cargotrace_map_projection_v1';

  function project3D(lat, lon, rotLat, rotLon, cx, cy, radius, altitude) {
    var phi = lat * DEG2RAD;
    var lambda = (lon - rotLon) * DEG2RAD;
    var phi0 = rotLat * DEG2RAD;
    var cosPhi = Math.cos(phi);
    var sinPhi = Math.sin(phi);
    var cosPhi0 = Math.cos(phi0);
    var sinPhi0 = Math.sin(phi0);
    var cosLambda = Math.cos(lambda);
    var sinLambda = Math.sin(lambda);
    var xUnit = cosPhi * sinLambda;
    var yUnit = cosPhi0 * sinPhi - sinPhi0 * cosPhi * cosLambda;
    var zUnit = sinPhi0 * sinPhi + cosPhi0 * cosPhi * cosLambda;
    return {
      x: cx + xUnit * radius * altitude,
      y: cy - yUnit * radius * altitude,
      z: zUnit,
      visible: zUnit > -0.05
    };
  }

  function greatCirclePoints(from, to, steps) {
    var toRad = function (deg) { return (deg * Math.PI) / 180; };
    var toDeg = function (rad) { return (rad * 180) / Math.PI; };
    var lat1 = toRad(from[0]);
    var lon1 = toRad(from[1]);
    var lat2 = toRad(to[0]);
    var lon2 = toRad(to[1]);
    var d = 2 * Math.asin(Math.sqrt(
      Math.pow(Math.sin((lat2 - lat1) / 2), 2) +
      Math.cos(lat1) * Math.cos(lat2) * Math.pow(Math.sin((lon2 - lon1) / 2), 2)
    ));
    if (d === 0 || isNaN(d)) return [from, to];
    var points = [];
    var i, f, a, b, x, y, z;
    for (i = 0; i <= steps; i += 1) {
      f = i / steps;
      a = Math.sin((1 - f) * d) / Math.sin(d);
      b = Math.sin(f * d) / Math.sin(d);
      x = a * Math.cos(lat1) * Math.cos(lon1) + b * Math.cos(lat2) * Math.cos(lon2);
      y = a * Math.cos(lat1) * Math.sin(lon1) + b * Math.cos(lat2) * Math.sin(lon2);
      z = a * Math.sin(lat1) + b * Math.sin(lat2);
      points.push([toDeg(Math.atan2(z, Math.sqrt(x * x + y * y))), toDeg(Math.atan2(y, x))]);
    }
    return points;
  }

  var ICONS = {
    play: '<polygon points="6 3 20 12 6 21 6 3" fill="currentColor" stroke="none"/>',
    pause: '<rect x="14" y="4" width="4" height="16" rx="1" fill="currentColor" stroke="none"/><rect x="6" y="4" width="4" height="16" rx="1" fill="currentColor" stroke="none"/>',
    plus: '<path d="M5 12h14"/><path d="M12 5v14"/>',
    minus: '<path d="M5 12h14"/>',
    reset: '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
    compass: '<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>'
  };

  function icon(name, cls) {
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' + (cls || 'size-3') + '">' + ICONS[name] + '</svg>';
  }

  function roundRectPath(ctx, x, y, w, h, r) {
    ctx.beginPath();
    if (typeof ctx.roundRect === 'function') {
      ctx.roundRect(x, y, w, h, r);
      return;
    }
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  function readProjection() {
    try {
      return window.sessionStorage.getItem(STORAGE_KEY) === '3d' ? '3d' : '2d';
    } catch (e) {
      return '2d';
    }
  }

  function mountGlobe(container, shipments, focusId, onSelectShipment) {
    var canvas = document.createElement('canvas');
    canvas.setAttribute('data-testid', 'globe-3d-canvas');
    canvas.className = 'absolute inset-0 block h-full w-full cursor-grab active:cursor-grabbing';
    container.appendChild(canvas);

    var controls = document.createElement('div');
    // Controls live in the map wrapper (sibling of the globe canvas), NOT
    // inside the globe container: the globe container sits at z-10 under the
    // inspector card (z-400), so controls inside it would be unclickable.
    // As a wrapper-level sibling at z-450 they stay above the inspector.
    // initProjection() hides them together with the globe in 2D mode.
    controls.setAttribute('data-globe-controls', '');
    controls.className = 'absolute bottom-4 right-3 z-[450] hidden flex-col items-end gap-2 sm:right-4';
    controls.innerHTML =
      '<div class="flex items-center gap-1.5 rounded-md border border-[var(--line-strong)] bg-[var(--bg-overlay)] p-1 shadow-md">' +
        '<button type="button" data-globe-spin class="inline-flex h-7 items-center gap-1 rounded bg-[var(--bg-surface)] px-2 text-[11px] font-semibold text-[var(--ink-2)] transition-colors hover:text-[var(--ink-1)] cursor-pointer">' +
          '<span data-globe-spin-icon>' + icon('play') + '</span><span data-globe-spin-label>Auto-spin</span>' +
        '</button>' +
        '<button type="button" data-globe-reset title="Reset 3D globe orientation" class="inline-flex h-7 items-center gap-1 rounded bg-[var(--bg-surface)] px-2 text-[11px] font-medium text-[var(--ink-2)] hover:text-[var(--ink-1)] cursor-pointer">' +
          icon('reset') + '<span class="hidden sm:inline">Reset</span>' +
        '</button>' +
      '</div>' +
      '<div class="flex flex-col overflow-hidden rounded-md border border-[var(--line-strong)] bg-[var(--bg-overlay)] shadow-md">' +
        '<button type="button" data-globe-zoom-in aria-label="Zoom in 3D globe" class="flex size-8 items-center justify-center border-b border-[var(--line)] text-[var(--ink-1)] hover:bg-[var(--bg-surface)] cursor-pointer">' + icon('plus', 'size-3.5') + '</button>' +
        '<button type="button" data-globe-zoom-out aria-label="Zoom out 3D globe" class="flex size-8 items-center justify-center text-[var(--ink-1)] hover:bg-[var(--bg-surface)] cursor-pointer">' + icon('minus', 'size-3.5') + '</button>' +
      '</div>' +
      '<div class="rounded border border-[var(--line)] bg-[var(--bg-overlay)] px-2 py-0.5 font-mono text-[10px] text-[var(--ink-3)]">' +
        '<span class="mr-1 inline-block align-[-2px]">' + icon('compass') + '</span>' +
        '3D Orthographic Globe &middot; <span data-globe-zoom-label>108%</span> &middot; Drag to rotate' +
      '</div>';
    var controlsHost = container.parentNode || container;
    controlsHost.appendChild(controls);

    var spinBtn = controls.querySelector('[data-globe-spin]');
    var spinIcon = controls.querySelector('[data-globe-spin-icon]');
    var spinLabel = controls.querySelector('[data-globe-spin-label]');
    var zoomLabel = controls.querySelector('[data-globe-zoom-label]');

    var autoSpin = false;
    var zoomScale = 1.08;
    var hitTargets = [];
    var animId = 0;
    var destroyed = false;

    var v = {
      rotLat: 14, rotLon: 72, targetLat: 14, targetLon: 72,
      zoom: 1.08, targetZoom: 1.08,
      dragging: false, dragStartX: 0, dragStartY: 0,
      startLat: 14, startLon: 72, movedDistance: 0, dashPhase: 0
    };

    function setZoomLabel() {
      if (zoomLabel) zoomLabel.textContent = Math.round(zoomScale * 100) + '%';
    }

    function setZoom(next) {
      next = Math.max(0.75, Math.min(2.2, next));
      v.targetZoom = next;
      v.zoom = next;
      zoomScale = Number(next.toFixed(2));
      setZoomLabel();
      render();
    }

    function centerOnFocus() {
      var target = focusId !== 0
        ? (shipments || []).filter(function (s) { return s.id === focusId; })[0]
        : (shipments || [])[0];
      if (target) {
        var pts = [];
        if (target.origin) pts.push([target.origin.latitude, target.origin.longitude]);
        if (target.current_position) pts.push([target.current_position.latitude, target.current_position.longitude]);
        if (target.destination) pts.push([target.destination.latitude, target.destination.longitude]);
        if (pts.length > 0) {
          var avgLat = pts.reduce(function (acc, p) { return acc + p[0]; }, 0) / pts.length;
          var avgLon = pts.reduce(function (acc, p) { return acc + p[1]; }, 0) / pts.length;
          var clampedLat = Math.max(-50, Math.min(55, avgLat));
          v.targetLat = clampedLat;
          v.targetLon = avgLon;
          v.rotLat = clampedLat;
          v.rotLon = avgLon;
          var nextZoom = focusId !== 0 ? 1.18 : 1.08;
          v.targetZoom = nextZoom;
          v.zoom = nextZoom;
          zoomScale = nextZoom;
          setZoomLabel();
          return;
        }
      }
      v.targetLat = 14; v.targetLon = 72; v.rotLat = 14; v.rotLon = 72;
      v.targetZoom = 1.08; v.zoom = 1.08; zoomScale = 1.08;
      setZoomLabel();
    }

    function render() {
      if (destroyed) return;
      var ctx = canvas.getContext('2d');
      if (!ctx) return;
      var rect = container.getBoundingClientRect();
      var width = Math.max(280, Math.min(2400, Math.floor(rect.width || 640)));
      var height = Math.max(280, Math.min(1800, Math.floor(rect.height || 460)));
      var dpr = Math.min(window.devicePixelRatio || 1, 2);
      var targetW = Math.floor(width * dpr);
      var targetH = Math.floor(height * dpr);
      if (canvas.width !== targetW || canvas.height !== targetH) {
        canvas.width = targetW;
        canvas.height = targetH;
      }
      ctx.save();
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

      var needsMoreFrames = false;
      if (!v.dragging) {
        if (autoSpin) {
          v.targetLon = (((v.targetLon + 0.22 + 180) % 360) - 180);
          v.dashPhase = (v.dashPhase + 0.35) % 24;
          needsMoreFrames = true;
        }
        var dLat = v.targetLat - v.rotLat;
        var dLon = v.targetLon - v.rotLon;
        if (dLon > 180) dLon -= 360;
        if (dLon < -180) dLon += 360;
        if (Math.abs(dLat) > 0.04 || Math.abs(dLon) > 0.04) {
          v.rotLat += dLat * 0.16;
          v.rotLon += dLon * 0.16;
          needsMoreFrames = true;
        } else {
          v.rotLat = v.targetLat;
          v.rotLon = v.targetLon;
        }
      } else {
        needsMoreFrames = true;
      }
      var dZoom = v.targetZoom - v.zoom;
      if (Math.abs(dZoom) > 0.003) {
        v.zoom += dZoom * 0.2;
        needsMoreFrames = true;
      } else {
        v.zoom = v.targetZoom;
      }

      var isDark = document.documentElement.classList.contains('dark');
      var cx = width / 2;
      var cy = height / 2;
      var baseRadius = Math.min(width, height) * 0.38 * v.zoom;

      // 1. Background + halo
      ctx.fillStyle = isDark ? '#060809' : '#E6ECEF';
      ctx.fillRect(0, 0, width, height);
      var haloGrad = ctx.createRadialGradient(cx, cy, baseRadius * 0.75, cx, cy, baseRadius * 1.25);
      if (isDark) {
        haloGrad.addColorStop(0, 'rgba(198, 244, 50, 0.12)');
        haloGrad.addColorStop(1, 'rgba(8, 11, 12, 0)');
      } else {
        haloGrad.addColorStop(0, 'rgba(8, 11, 12, 0.10)');
        haloGrad.addColorStop(1, 'rgba(230, 236, 239, 0)');
      }
      ctx.fillStyle = haloGrad;
      ctx.beginPath();
      ctx.arc(cx, cy, baseRadius * 1.25, 0, Math.PI * 2);
      ctx.fill();

      // 2. Ocean sphere with specular lighting
      var sphereGrad = ctx.createRadialGradient(cx - baseRadius * 0.3, cy - baseRadius * 0.3, baseRadius * 0.08, cx, cy, baseRadius);
      if (isDark) {
        sphereGrad.addColorStop(0, '#182227');
        sphereGrad.addColorStop(0.65, '#0D1316');
        sphereGrad.addColorStop(1, '#080B0C');
      } else {
        sphereGrad.addColorStop(0, '#FFFFFF');
        sphereGrad.addColorStop(0.7, '#EEF3F6');
        sphereGrad.addColorStop(1, '#D5DFE5');
      }
      ctx.beginPath();
      ctx.arc(cx, cy, baseRadius, 0, Math.PI * 2);
      ctx.fillStyle = sphereGrad;
      ctx.fill();

      // Clip to sphere: graticule + continents
      ctx.save();
      ctx.beginPath();
      ctx.arc(cx, cy, baseRadius, 0, Math.PI * 2);
      ctx.clip();

      // 3. Graticule every 15 degrees
      var lon, lat, p, started;
      ctx.strokeStyle = isDark ? 'rgba(255, 255, 255, 0.07)' : 'rgba(8, 11, 12, 0.08)';
      ctx.lineWidth = 0.8;
      for (lon = -180; lon < 180; lon += 15) {
        ctx.beginPath();
        started = false;
        for (lat = -75; lat <= 75; lat += 5) {
          p = project3D(lat, lon, v.rotLat, v.rotLon, cx, cy, baseRadius, 1.0);
          if (p.z > 0) {
            if (!started) { ctx.moveTo(p.x, p.y); started = true; }
            else ctx.lineTo(p.x, p.y);
          } else started = false;
        }
        ctx.stroke();
      }
      for (lat = -60; lat <= 60; lat += 15) {
        ctx.beginPath();
        started = false;
        for (lon = -180; lon <= 180; lon += 5) {
          p = project3D(lat, lon, v.rotLat, v.rotLon, cx, cy, baseRadius, 1.0);
          if (p.z > 0) {
            if (!started) { ctx.moveTo(p.x, p.y); started = true; }
            else ctx.lineTo(p.x, p.y);
          } else started = false;
        }
        ctx.lineWidth = lat === 0 ? 1.2 : 0.8;
        ctx.strokeStyle = lat === 0
          ? (isDark ? 'rgba(198, 244, 50, 0.22)' : 'rgba(8, 11, 12, 0.16)')
          : (isDark ? 'rgba(255, 255, 255, 0.07)' : 'rgba(8, 11, 12, 0.08)');
        ctx.stroke();
      }

      // 4. Land polygons
      WORLD_LAND_POLYGONS.forEach(function (poly) {
        ctx.beginPath();
        var visibleCount = 0;
        poly.forEach(function (coord, idx) {
          var pp = project3D(coord[0], coord[1], v.rotLat, v.rotLon, cx, cy, baseRadius, 1.0);
          if (pp.z > -0.15) visibleCount += 1;
          if (idx === 0) ctx.moveTo(pp.x, pp.y);
          else ctx.lineTo(pp.x, pp.y);
        });
        ctx.closePath();
        if (visibleCount >= 2) {
          ctx.fillStyle = isDark ? 'rgba(198, 244, 50, 0.11)' : '#DCE4E8';
          ctx.fill();
          ctx.strokeStyle = isDark ? 'rgba(198, 244, 50, 0.35)' : 'rgba(8, 11, 12, 0.28)';
          ctx.lineWidth = 1.0;
          ctx.stroke();
        }
      });
      ctx.restore();

      // Rim ring
      ctx.beginPath();
      ctx.arc(cx, cy, baseRadius, 0, Math.PI * 2);
      ctx.strokeStyle = isDark ? 'rgba(198, 244, 50, 0.45)' : '#080B0C';
      ctx.lineWidth = isDark ? 1.5 : 1.75;
      ctx.stroke();

      // 5. Corridor trajectories
      var newHitTargets = [];
      var isNearCoord = function (a, b, eps) {
        eps = eps || 0.02;
        return Math.abs(a[0] - b[0]) < eps && Math.abs(a[1] - b[1]) < eps;
      };
      var sortedShipments = (shipments || []).slice().sort(function (a, b) {
        var aFocus = a.id === focusId ? 1 : 0;
        var bFocus = b.id === focusId ? 1 : 0;
        return aFocus - bFocus;
      });

      sortedShipments.forEach(function (shipment) {
        if (!shipment.origin || !shipment.destination) return;
        var isFocused = focusId !== 0 && shipment.id === focusId;
        var isDimmed = focusId !== 0 && shipment.id !== focusId;
        var originCoord = [shipment.origin.latitude, shipment.origin.longitude];
        var destCoord = [shipment.destination.latitude, shipment.destination.longitude];

        var rawCandidates = [];
        if (shipment.checkpoints && shipment.checkpoints.length > 0) {
          rawCandidates.push.apply(rawCandidates, shipment.checkpoints.slice().reverse());
        }
        if (shipment.current_position) rawCandidates.push(shipment.current_position);
        var intermediateStops = [];
        rawCandidates.forEach(function (pt) {
          var coord = [pt.latitude, pt.longitude];
          if (isNearCoord(coord, originCoord) || isNearCoord(coord, destCoord)) return;
          var dup = intermediateStops.some(function (ex) {
            return isNearCoord([ex.latitude, ex.longitude], coord);
          });
          if (!dup) intermediateStops.push(pt);
        });
        var orderedWaypoints = [originCoord].concat(intermediateStops.map(function (pt) {
          return [pt.latitude, pt.longitude];
        }));
        var hasMovedFromOrigin = orderedWaypoints.length >= 2;

        var primaryColor = shipment.status === 'DELIVERED'
          ? (isDark ? '#10B981' : '#047857')
          : shipment.delayed
            ? (isDark ? '#F59E0B' : '#D97706')
            : (isDark ? '#C6F432' : '#080B0C');

        var drawElevatedArcSegment = function (start, end, dashed, color, lineWidth, alpha) {
          var steps = 36;
          var pts = greatCirclePoints(start, end, steps);
          if (isFocused) {
            ctx.save();
            ctx.beginPath();
            var startedShadow = false;
            pts.forEach(function (coord) {
              var sp = project3D(coord[0], coord[1], v.rotLat, v.rotLon, cx, cy, baseRadius, 1.0);
              if (sp.z > 0) {
                if (!startedShadow) { ctx.moveTo(sp.x, sp.y); startedShadow = true; }
                else ctx.lineTo(sp.x, sp.y);
              } else startedShadow = false;
            });
            ctx.strokeStyle = isDark ? 'rgba(0,0,0,0.45)' : 'rgba(8,11,12,0.14)';
            ctx.lineWidth = lineWidth;
            ctx.stroke();
            ctx.restore();
          }
          ctx.save();
          ctx.globalAlpha = alpha;
          ctx.strokeStyle = color;
          ctx.lineWidth = lineWidth;
          ctx.lineCap = 'round';
          if (dashed) {
            ctx.setLineDash([5, 6]);
            ctx.lineDashOffset = -v.dashPhase;
          }
          ctx.beginPath();
          var startedArc = false;
          pts.forEach(function (coord, idx) {
            var t = idx / steps;
            var archBoost = isFocused ? Math.sin(t * Math.PI) * 0.055 : Math.sin(t * Math.PI) * 0.025;
            var ap = project3D(coord[0], coord[1], v.rotLat, v.rotLon, cx, cy, baseRadius, 1.0 + archBoost);
            if (ap.z > -0.02) {
              if (!startedArc) { ctx.moveTo(ap.x, ap.y); startedArc = true; }
              else ctx.lineTo(ap.x, ap.y);
            } else startedArc = false;
          });
          ctx.stroke();
          ctx.restore();
        };

        var secondary = isDark ? '#525E64' : '#64748B';
        if (shipment.status === 'DELIVERED') {
          var allPts = orderedWaypoints.concat([destCoord]);
          for (var i = 0; i < allPts.length - 1; i += 1) {
            drawElevatedArcSegment(allPts[i], allPts[i + 1], false,
              isFocused ? primaryColor : secondary, isFocused ? 3.0 : 1.5, isDimmed ? 0.25 : 0.95);
          }
        } else if (hasMovedFromOrigin) {
          for (var j = 0; j < orderedWaypoints.length - 1; j += 1) {
            drawElevatedArcSegment(orderedWaypoints[j], orderedWaypoints[j + 1], false,
              isFocused ? primaryColor : secondary, isFocused ? 3.2 : 1.6, isDimmed ? 0.28 : 0.98);
          }
          var latestStop = orderedWaypoints[orderedWaypoints.length - 1];
          drawElevatedArcSegment(latestStop, destCoord, true,
            isFocused ? (isDark ? '#D0D8DC' : '#3D4850') : secondary,
            isFocused ? 2.2 : 1.3, isDimmed ? 0.22 : 0.88);
        } else {
          drawElevatedArcSegment(originCoord, destCoord, true,
            isFocused ? primaryColor : secondary,
            isFocused ? 2.6 : 1.4, isDimmed ? 0.25 : (isFocused ? 0.92 : 0.55));
        }

        var drawNode = function (coord, radius, fill, stroke, badgeText, badgeTone) {
          var np = project3D(coord[0], coord[1], v.rotLat, v.rotLon, cx, cy, baseRadius, 1.0);
          if (np.z <= 0) return null;
          ctx.save();
          ctx.globalAlpha = isDimmed ? 0.35 : 1;
          ctx.beginPath();
          ctx.arc(np.x, np.y, radius, 0, Math.PI * 2);
          ctx.fillStyle = fill;
          ctx.fill();
          ctx.lineWidth = 2;
          ctx.strokeStyle = stroke;
          ctx.stroke();
          if (badgeText && isFocused) {
            ctx.font = '700 10px "IBM Plex Mono", monospace';
            var textW = ctx.measureText(badgeText).width;
            var padX = 6;
            var boxW = textW + padX * 2;
            var boxH = 18;
            var boxX = np.x - boxW / 2;
            var boxY = np.y - radius - boxH - 5;
            ctx.fillStyle = badgeTone === 'volt' ? '#C6F432' : (isDark ? '#101518' : '#FFFFFF');
            ctx.strokeStyle = '#080B0C';
            ctx.lineWidth = 1.25;
            roundRectPath(ctx, boxX, boxY, boxW, boxH, 4);
            ctx.fill();
            ctx.stroke();
            ctx.fillStyle = badgeTone === 'volt' ? '#080B0C' : (isDark ? '#FFFFFF' : '#080B0C');
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(badgeText, np.x, boxY + boxH / 2 + 0.5);
          }
          ctx.restore();
          return np;
        };

        drawNode(originCoord, isFocused ? 5.5 : 3.5,
          isDark ? '#080B0C' : '#FFFFFF', isDark ? '#FFFFFF' : '#080B0C',
          shipment.origin.code ? 'Origin: ' + shipment.origin.code : undefined, 'surface');
        drawNode(destCoord, isFocused ? 5.5 : 3.5,
          isFocused ? primaryColor : (isDark ? '#172024' : '#FFFFFF'),
          isDark ? '#FFFFFF' : '#080B0C',
          shipment.destination.code ? 'Dest: ' + shipment.destination.code : undefined, 'surface');

        if (isFocused) {
          intermediateStops.forEach(function (cp, idx) {
            drawNode([cp.latitude, cp.longitude], 5.5, '#C6F432', '#080B0C',
              '#' + (idx + 1) + ' ' + String(cp.location_name).split('\u00b7')[0].trim(), 'volt');
          });
        }

        if (shipment.current_position) {
          var curP = project3D(shipment.current_position.latitude, shipment.current_position.longitude,
            v.rotLat, v.rotLon, cx, cy, baseRadius, 1.02);
          if (curP.z > 0) {
            ctx.save();
            ctx.globalAlpha = isDimmed ? 0.4 : 1;
            ctx.beginPath();
            ctx.arc(curP.x, curP.y, isFocused ? 8 : 6, 0, Math.PI * 2);
            ctx.fillStyle = isFocused ? '#C6F432' : '#080B0C';
            ctx.fill();
            ctx.lineWidth = 2;
            ctx.strokeStyle = isFocused ? '#080B0C' : (shipment.delayed ? '#F59E0B' : '#C6F432');
            ctx.stroke();
            ctx.beginPath();
            ctx.arc(curP.x, curP.y, 2.5, 0, Math.PI * 2);
            ctx.fillStyle = isFocused ? '#080B0C' : '#C6F432';
            ctx.fill();
            ctx.restore();
            newHitTargets.push({ id: shipment.id, x: curP.x, y: curP.y, r: 14, label: shipment.shipment_number });
          }
        }
      });

      hitTargets = newHitTargets;
      ctx.restore();

      if (needsMoreFrames) {
        animId = window.requestAnimationFrame(render);
      }
    }

    function setSpin(next) {
      autoSpin = next;
      spinBtn.classList.toggle('bg-[#C6F432]', autoSpin);
      spinBtn.classList.toggle('text-[#080B0C]', autoSpin);
      spinBtn.classList.toggle('bg-[var(--bg-surface)]', !autoSpin);
      spinBtn.classList.toggle('text-[var(--ink-2)]', !autoSpin);
      spinIcon.innerHTML = icon(autoSpin ? 'pause' : 'play');
      spinLabel.textContent = autoSpin ? 'Spinning' : 'Auto-spin';
      render();
    }

    function resetView() {
      v.targetLat = 14; v.targetLon = 72; v.rotLat = 14; v.rotLon = 72;
      v.targetZoom = 1.08; v.zoom = 1.08; zoomScale = 1.08;
      setZoomLabel();
      render();
    }

    spinBtn.addEventListener('click', function () { setSpin(!autoSpin); });
    controls.querySelector('[data-globe-reset]').addEventListener('click', resetView);
    controls.querySelector('[data-globe-zoom-in]').addEventListener('click', function () { setZoom(v.targetZoom + 0.2); });
    controls.querySelector('[data-globe-zoom-out]').addEventListener('click', function () { setZoom(v.targetZoom - 0.2); });

    canvas.addEventListener('pointerdown', function (e) {
      v.dragging = true;
      v.dragStartX = e.clientX;
      v.dragStartY = e.clientY;
      v.startLat = v.rotLat;
      v.startLon = v.rotLon;
      v.movedDistance = 0;
      try { canvas.setPointerCapture(e.pointerId); } catch (err) { /* noop */ }
      render();
    });
    canvas.addEventListener('pointermove', function (e) {
      if (!v.dragging) return;
      var dx = e.clientX - v.dragStartX;
      var dy = e.clientY - v.dragStartY;
      v.movedDistance = Math.hypot(dx, dy);
      var sensitivity = 0.28 / v.zoom;
      v.rotLon = (((v.startLon - dx * sensitivity + 540) % 360) - 180);
      v.rotLat = Math.max(-70, Math.min(70, v.startLat + dy * sensitivity));
      v.targetLon = v.rotLon;
      v.targetLat = v.rotLat;
      render();
    });
    var endDrag = function (e) {
      if (!v.dragging) return;
      v.dragging = false;
      try { canvas.releasePointerCapture(e.pointerId); } catch (err) { /* noop */ }
      if (v.movedDistance < 6 && onSelectShipment) {
        var rect = canvas.getBoundingClientRect();
        var clickX = e.clientX - rect.left;
        var clickY = e.clientY - rect.top;
        var hit = null;
        for (var i = 0; i < hitTargets.length; i += 1) {
          if (Math.hypot(hitTargets[i].x - clickX, hitTargets[i].y - clickY) <= hitTargets[i].r) {
            hit = hitTargets[i];
            break;
          }
        }
        if (hit) onSelectShipment(hit.id);
      }
      render();
    };
    canvas.addEventListener('pointerup', endDrag);
    canvas.addEventListener('pointercancel', endDrag);
    canvas.addEventListener('wheel', function (e) {
      e.preventDefault();
      setZoom(v.targetZoom - e.deltaY * 0.0012);
    }, { passive: false });

    var onResize = function () { render(); };
    var onTheme = function () { render(); };
    var onLoad = function () { render(); };
    window.addEventListener('resize', onResize);
    window.addEventListener('ct:theme', onTheme);
    window.addEventListener('load', onLoad);

    centerOnFocus();
    render();

    return {
      render: render,
      controls: controls,
      destroy: function () {
        destroyed = true;
        if (animId) window.cancelAnimationFrame(animId);
        window.removeEventListener('resize', onResize);
        window.removeEventListener('ct:theme', onTheme);
        window.removeEventListener('load', onLoad);
        if (canvas.parentNode) canvas.parentNode.removeChild(canvas);
        if (controls.parentNode) controls.parentNode.removeChild(controls);
      }
    };
  }

  function initProjection(opts) {
    var btn2d = opts.btn2d;
    var btn3d = opts.btn3d;
    var mapEl = opts.mapEl;
    var globeEl = opts.globeEl;
    var active2d = 'bg-[#080B0C] text-white dark:bg-[#C6F432] dark:text-[#080B0C]';
    var active3d = active2d;
    var inactive = 'text-[var(--ink-2)] hover:text-[var(--ink-1)]';

    function applyMode(mode) {
      var is3d = mode === '3d';
      if (btn2d) {
        btn2d.className = btn2d.className.replace(active2d, '').replace(inactive, '').trim() +
          ' ' + (is3d ? inactive : active2d);
      }
      if (btn3d) {
        btn3d.className = btn3d.className.replace(active3d, '').replace(inactive, '').trim() +
          ' ' + (is3d ? active3d : inactive);
      }
      if (mapEl) mapEl.classList.toggle('invisible', is3d);
      if (mapEl) mapEl.classList.toggle('pointer-events-none', is3d);
      if (globeEl) globeEl.classList.toggle('hidden', !is3d);
      var controlsEl = opts.controlsEl ||
        (globeEl && globeEl.parentNode ? globeEl.parentNode.querySelector('[data-globe-controls]') : null);
      if (controlsEl) {
        controlsEl.classList.toggle('hidden', !is3d);
        controlsEl.classList.toggle('flex', is3d);
      }
      if (is3d) {
        if (typeof opts.onShow3D === 'function') opts.onShow3D();
      } else if (typeof opts.onShow2D === 'function') {
        window.setTimeout(function () { opts.onShow2D(); }, 60);
      }
    }

    function setMode(mode) {
      try { window.sessionStorage.setItem(STORAGE_KEY, mode); } catch (e) { /* ignore */ }
      applyMode(mode);
    }

    if (btn2d) btn2d.addEventListener('click', function () { setMode('2d'); });
    if (btn3d) btn3d.addEventListener('click', function () { setMode('3d'); });
    applyMode(readProjection());
    return { setMode: setMode, mode: readProjection() };
  }

  window.CargoTraceGlobe = {
    mount: mountGlobe,
    initProjection: initProjection,
    readProjection: readProjection
  };
})();
