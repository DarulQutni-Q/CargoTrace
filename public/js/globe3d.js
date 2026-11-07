/* CargoTrace 3D globe — vanilla canvas port of Globe3DCanvas.tsx (commit 46db38f),
 * with the OSM slippy-tile renderer ported from the AI Studio Globe3DCanvas
 * rework (b6fc426). Real OpenStreetMap raster tiles are projected onto the
 * orthographic sphere as a subdivided mesh; no self-made vector map.
 *
 * No framework, no WebGL: orthographic projection on a 2D canvas, elevated
 * great-circle arcs (solid completed leg, dashed remaining leg, surface
 * shadow under the focused corridor), volt checkpoint badges, drag to rotate,
 * wheel/buttons to zoom, auto-spin, and click-to-focus on the live vessel
 * marker. Theme follows the document `dark` class, same as the Leaflet map
 * (re-rendered on `ct:theme`).
 *
 * Wiring (Blade): CargoTraceGlobe.mount(globeContainer, entries, focusId,
 * onSelect) + CargoTraceGlobe.initProjection({...}) for the 2D/3D switcher.
 * Projection preference persists in sessionStorage under
 * `cargotrace_map_projection_v1` ('2d' | '3d'), same key as the React build.
 */
(function () {
  'use strict';

  /* OpenStreetMap slippy-tile globe (ported from the AI Studio
   * Globe3DCanvas OSM rework, commit b6fc426). Real raster tiles from
   * https://tile.openstreetmap.org/{z}/{x}/{y}.png, projected onto the
   * orthographic sphere as a subdivided mesh. No self-made vector map:
   * when tiles have not loaded yet the sphere shows plain ocean, and
   * missing tiles fall back to a loaded parent tile, never to polygons.
   */
  var MAX_MERCATOR_LAT = 85.05112878;

  var tileCache = new Map();
  var tileListeners = new Set();
  var activeTileRequests = 0;
  var MAX_CONCURRENT_TILE_REQUESTS = 18;
  var tileRequestQueue = [];

  function notifyTileLoaded() {
    tileListeners.forEach(function (cb) { cb(); });
  }

  function processTileQueue() {
    while (activeTileRequests < MAX_CONCURRENT_TILE_REQUESTS && tileRequestQueue.length > 0) {
      var next = tileRequestQueue.pop();
      if (!next) break;
      var existing = tileCache.get(next.key);
      if (!existing || existing.loaded || existing.error) continue;
      activeTileRequests += 1;
      var img = existing.img;
      img.onload = function () {
        activeTileRequests = Math.max(0, activeTileRequests - 1);
        existing.loaded = true;
        existing.lastUsed = performance.now();
        notifyTileLoaded();
        processTileQueue();
      };
      img.onerror = function () {
        activeTileRequests = Math.max(0, activeTileRequests - 1);
        existing.error = true;
        processTileQueue();
      };
      img.src = 'https://tile.openstreetmap.org/' + next.z + '/' + next.x + '/' + next.y + '.png';
    }
  }

  function requestOsmTile(z, x, y) {
    var n = 1 << z;
    var wrappedX = ((x % n) + n) % n;
    var clampedY = Math.max(0, Math.min(n - 1, y));
    var key = z + '/' + wrappedX + '/' + clampedY;
    var entry = tileCache.get(key);
    if (entry) {
      entry.lastUsed = performance.now();
      return entry;
    }
    // Evict oldest-inserted tiles (a Map keeps insertion order) without
    // sorting the whole cache on every request: at deep zoom the cache
    // grows fast and a full sort per tile request janks the frame loop.
    // Base z2/z3 tiles are never evicted (they are the fallback layer).
    if (tileCache.size > 600) {
      var evicted = 0;
      tileCache.forEach(function (entry, key) {
        if (evicted >= 120) return;
        if (key.indexOf('2/') === 0 || key.indexOf('3/') === 0) return;
        tileCache.delete(key);
        evicted += 1;
      });
    }
    var img = new Image();
    entry = { img: img, loaded: false, error: false, lastUsed: performance.now() };
    tileCache.set(key, entry);
    tileRequestQueue.push({ key: key, z: z, x: wrappedX, y: clampedY });
    if (tileRequestQueue.length > 64) {
      tileRequestQueue.splice(0, tileRequestQueue.length - 64);
    }
    processTileQueue();
    return entry;
  }

  var baseTilesPreloaded = false;
  function preloadBaseGlobeTiles() {
    if (baseTilesPreloaded) return;
    baseTilesPreloaded = true;
    for (var y = 0; y < 4; y += 1) {
      for (var x = 0; x < 4; x += 1) {
        requestOsmTile(2, x, y);
      }
    }
  }

  function lonToTileX(lon, z) {
    var n = 1 << z;
    return Math.floor(((((lon + 180) / 360) % 1 + 1) % 1) * n);
  }

  function latToTileY(lat, z) {
    var clamped = Math.max(-MAX_MERCATOR_LAT, Math.min(MAX_MERCATOR_LAT, lat));
    var rad = clamped * DEG2RAD;
    var n = 1 << z;
    var merc = Math.log(Math.tan(Math.PI / 4 + rad / 2));
    return Math.max(0, Math.min(n - 1, Math.floor((1 - merc / Math.PI) * 0.5 * n)));
  }

  function tileXToLon(x, z) {
    return (x / (1 << z)) * 360 - 180;
  }

  function tileYToLat(y, z) {
    var n = Math.PI - (2 * Math.PI * y) / (1 << z);
    return (180 / Math.PI) * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n)));
  }

  function resolveTileDrawable(z, x, y, allowNetworkFetch) {
    var n = 1 << z;
    var wrappedX = ((x % n) + n) % n;
    var clampedY = Math.max(0, Math.min(n - 1, y));
    var exactKey = z + '/' + wrappedX + '/' + clampedY;
    var exact = allowNetworkFetch ? requestOsmTile(z, wrappedX, clampedY) : tileCache.get(exactKey);
    if (exact && exact.loaded && exact.img.complete && exact.img.naturalWidth > 0) {
      return { img: exact.img, sx: 0, sy: 0, sw: 256, sh: 256 };
    }
    // Fall back up the quadtree to the nearest loaded ancestor tile so the
    // globe never shows blank holes while zooming or panning.
    for (var ancZ = z - 1; ancZ >= 2; ancZ -= 1) {
      var shift = z - ancZ;
      var ancX = wrappedX >> shift;
      var ancY = clampedY >> shift;
      var ancKey = ancZ + '/' + ancX + '/' + ancY;
      var anc = tileCache.get(ancKey);
      if (!anc && ancZ === 2) anc = requestOsmTile(2, ancX, ancY);
      if (anc && anc.loaded && anc.img.complete && anc.img.naturalWidth > 0) {
        var scale = 1 << shift;
        var subW = 256 / scale;
        var subX = (wrappedX - (ancX << shift)) * subW;
        var subY = (clampedY - (ancY << shift)) * subW;
        return { img: anc.img, sx: subX, sy: subY, sw: subW, sh: subW };
      }
    }
    return null;
  }

  function osmZoomFor(zoom) {
    return Math.max(2, Math.min(15, Math.round(Math.log2(Math.max(0.75, zoom)) + 2.55)));
  }

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

  /**
   * Liang-Barsky 2D line segment clipper against viewport rectangle
   * [minX, minY, maxX, maxY]. Prevents Canvas 2D from rasterizing
   * 100,000px off-screen dashed paths during deep 2500x street zoom
   * (ported from the AI Studio Globe3DCanvas rework, b6fc426).
   */
  function clipSegmentToViewport(x0, y0, x1, y1, minX, minY, maxX, maxY) {
    var dx = x1 - x0;
    var dy = y1 - y0;
    var t0 = 0;
    var t1 = 1;
    var p = [-dx, dx, -dy, dy];
    var q = [x0 - minX, maxX - x0, y0 - minY, maxY - y0];
    for (var i = 0; i < 4; i += 1) {
      if (p[i] === 0) {
        if (q[i] < 0) return null;
      } else {
        var r = q[i] / p[i];
        if (p[i] < 0) {
          if (r > t1) return null;
          if (r > t0) t0 = r;
        } else {
          if (r < t0) return null;
          if (r < t1) t1 = r;
        }
      }
    }
    return [x0 + t0 * dx, y0 + t0 * dy, x0 + t1 * dx, y0 + t1 * dy];
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
        '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener" class="underline">OpenStreetMap</a> contributors &middot; 3D Globe &middot; <span data-globe-zoom-label>108% &middot; OSM z3</span> &middot; Drag to rotate' +
      '</div>';
    var controlsHost = container.parentNode || container;
    controlsHost.appendChild(controls);

    var spinBtn = controls.querySelector('[data-globe-spin]');
    var spinIcon = controls.querySelector('[data-globe-spin-icon]');
    var spinLabel = controls.querySelector('[data-globe-spin-label]');
    var zoomLabel = controls.querySelector('[data-globe-zoom-label]');

    var autoSpin = false;
    var hitTargets = [];
    var animId = 0;
    var frameScheduled = false;
    var destroyed = false;

    var v = {
      rotLat: 14, rotLon: 72, targetLat: 14, targetLon: 72,
      zoom: 1.08, targetZoom: 1.08,
      dragging: false, dragStartX: 0, dragStartY: 0,
      startLat: 14, startLon: 72, movedDistance: 0, dashPhase: 0
    };

    // Focused shipment id, mutable after mount: setFocus() (called when a
    // feed row or marker is clicked) recenters the globe without a reload.
    var currentFocusId = focusId;

    // One coalesced animation-frame slot (same pattern as the React
    // reference): wheel/pointermove/tile-load handlers only *request* a
    // frame, they never draw synchronously. Before this fix every wheel
    // tick and every pointermove drew a full frame immediately AND the
    // rAF chain drew another one, so each gesture rendered twice.
    function scheduleRender() {
      if (destroyed || frameScheduled) return;
      frameScheduled = true;
      animId = window.requestAnimationFrame(render);
    }

    var lastZoomLabel = '';
    function setZoomLabel() {
      var label = v.zoom >= 10
        ? Math.round(v.zoom) + 'x · OSM z' + osmZoomFor(v.zoom)
        : Math.round(v.zoom * 100) + '% · OSM z' + osmZoomFor(v.zoom);
      if (label !== lastZoomLabel) {
        lastZoomLabel = label;
        if (zoomLabel) zoomLabel.textContent = label;
      }
    }

    // Zoom range matches the React reference: 0.75x up to 2500x street
    // level. The port used to clamp at 2.2x, which is why the globe
    // could not zoom past a regional view (OSM z4). Only the target is
    // set here; render() eases v.zoom toward it logarithmically.
    function setZoom(next) {
      next = Math.max(0.75, Math.min(2500, next));
      v.targetZoom = next;
      scheduleRender();
    }

    function centerOnFocus() {
      var target = currentFocusId !== 0
        ? (shipments || []).filter(function (s) { return s.id === currentFocusId; })[0]
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
          var nextZoom = currentFocusId !== 0 ? 1.18 : 1.08;
          v.targetZoom = nextZoom;
          v.zoom = nextZoom;
          setZoomLabel();
          return;
        }
      }
      v.targetLat = 14; v.targetLon = 72; v.rotLat = 14; v.rotLon = 72;
      v.targetZoom = 1.08; v.zoom = 1.08;
      setZoomLabel();
    }

    function isNearCoord(a, b, eps) {
      eps = eps || 0.02;
      return Math.abs(a[0] - b[0]) < eps && Math.abs(a[1] - b[1]) < eps;
    }

    // Great-circle geometry is static per shipment (waypoints never move
    // while the globe is mounted), so the slerp point arrays are computed
    // once here instead of redoing the trig for every segment on every
    // frame. Only the per-frame projection (rotation/zoom) stays in render.
    var arcCache = new Map();
    function buildArcCache() {
      arcCache.clear();
      (shipments || []).forEach(function (shipment) {
        if (!shipment.origin || !shipment.destination) return;
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
        var segments = [];
        var pushSeg = function (start, end, kind) {
          segments.push({ pts: greatCirclePoints(start, end, 36), dashed: kind !== 'completed', kind: kind });
        };
        if (shipment.status === 'DELIVERED') {
          var allPts = orderedWaypoints.concat([destCoord]);
          for (var i = 0; i < allPts.length - 1; i += 1) pushSeg(allPts[i], allPts[i + 1], 'completed');
        } else if (hasMovedFromOrigin) {
          for (var j = 0; j < orderedWaypoints.length - 1; j += 1) pushSeg(orderedWaypoints[j], orderedWaypoints[j + 1], 'completed');
          pushSeg(orderedWaypoints[orderedWaypoints.length - 1], destCoord, 'remaining');
        } else {
          pushSeg(originCoord, destCoord, 'single');
        }
        arcCache.set(shipment.id, {
          originCoord: originCoord,
          destCoord: destCoord,
          intermediateStops: intermediateStops,
          segments: segments,
          delivered: shipment.status === 'DELIVERED'
        });
      });
    }

    function render() {
      if (destroyed) return;
      frameScheduled = false;
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
          v.targetLon = (((v.targetLon + 0.22 / Math.max(1, Math.sqrt(v.zoom)) + 180) % 360) - 180);
          v.dashPhase = (v.dashPhase + 0.35) % 24;
          needsMoreFrames = true;
        }
        var dLat = v.targetLat - v.rotLat;
        var dLon = v.targetLon - v.rotLon;
        if (dLon > 180) dLon -= 360;
        if (dLon < -180) dLon += 360;
        // Rotation easing epsilon shrinks with zoom (React reference):
        // at street level a fixed 0.04 epsilon snaps the view instead
        // of easing it.
        var angularEps = 0.015 / Math.max(1, v.zoom);
        if (Math.abs(dLat) > angularEps || Math.abs(dLon) > angularEps) {
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
      // Smooth logarithmic zoom interpolation (0.75x up to 2500x street
      // level, React reference). Linear steps feel dead at deep zoom;
      // multiplicative easing stays smooth at any scale.
      var zRatio = v.targetZoom / v.zoom;
      if (Math.abs(zRatio - 1) > 0.004) {
        v.zoom *= Math.pow(zRatio, 0.34);
        needsMoreFrames = true;
      } else {
        v.zoom = v.targetZoom;
      }
      setZoomLabel();

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

      // 2. Ocean sphere + OpenStreetMap tiles, clipped to the sphere.
      var osmZ = osmZoomFor(v.zoom);
      var targetOsmZ = osmZoomFor(v.targetZoom);
      var zoomRatio = v.targetZoom / v.zoom;
      var isZoomSettled = Math.abs(zoomRatio - 1) < 0.14;
      var maxScreenHalfDiag = Math.hypot(cx, cy);
      var isRimVisible = baseRadius < maxScreenHalfDiag * 1.05;

      ctx.save();
      if (isRimVisible) {
        ctx.beginPath();
        ctx.arc(cx, cy, baseRadius, 0, Math.PI * 2);
        ctx.clip();
      }

      // Ocean base fill inside the sphere (shows while tiles load).
      ctx.fillStyle = isDark ? '#0D151A' : '#AAD3DF';
      ctx.fillRect(0, 0, width, height);

      // Dark-mode filter for the tiles (same treatment as the 2D map).
      if (isDark) {
        ctx.filter = 'invert(0.86) hue-rotate(180deg) brightness(1.18) contrast(1.22) saturate(1.12)';
      }

      // Visible geographic bounding box.
      var sinAngularRadius = Math.min(1, (maxScreenHalfDiag / baseRadius) * 1.08);
      var angularRadiusDeg = Math.min(89.5, Math.asin(sinAngularRadius) * (180 / Math.PI) * 1.15);
      var minLat = Math.max(-MAX_MERCATOR_LAT, v.rotLat - angularRadiusDeg);
      var maxLat = Math.min(MAX_MERCATOR_LAT, v.rotLat + angularRadiusDeg);
      var nTiles = 1 << osmZ;
      var yMin = latToTileY(maxLat, osmZ);
      var yMax = latToTileY(minLat, osmZ);
      var xMin = 0;
      var xMax = nTiles - 1;
      // When zoomed in enough that the pole is not inside the view, restrict
      // the X tile range to the visible longitudes.
      if (Math.abs(v.rotLat) + angularRadiusDeg < 82) {
        var cosLatFactor = Math.max(0.15, Math.cos(v.rotLat * DEG2RAD));
        var lonSpan = Math.min(179, (angularRadiusDeg / cosLatFactor) * 1.2);
        xMin = Math.floor(((v.rotLon - lonSpan + 180) / 360) * nTiles);
        xMax = Math.floor(((v.rotLon + lonSpan + 180) / 360) * nTiles);
        if (xMax - xMin >= nTiles) { xMin = 0; xMax = nTiles - 1; }
      }

      // Subdivide low-zoom tiles into a small mesh so spherical curvature
      // stays smooth; at z >= 5 each tile spans < 11 degrees and is locally
      // planar on the sphere.
      var subdiv = osmZ <= 2 ? 4 : osmZ === 3 ? 3 : osmZ === 4 ? 2 : 1;
      var invSubdiv = 1 / subdiv;
      var allowTileFetch = isZoomSettled || osmZ === targetOsmZ || osmZ <= 3;

      for (var ty = yMin; ty <= yMax; ty += 1) {
        for (var tx = xMin; tx <= xMax; tx += 1) {
          var wrappedTx = ((tx % nTiles) + nTiles) % nTiles;
          var drawable = resolveTileDrawable(osmZ, wrappedTx, ty, allowTileFetch);
          if (!drawable) continue;
          for (var syIdx = 0; syIdx < subdiv; syIdx += 1) {
            var latN = tileYToLat(ty + syIdx * invSubdiv, osmZ);
            var latS = tileYToLat(ty + (syIdx + 1) * invSubdiv, osmZ);
            for (var sxIdx = 0; sxIdx < subdiv; sxIdx += 1) {
              var lonW = tileXToLon(wrappedTx + sxIdx * invSubdiv, osmZ);
              var lonE = tileXToLon(wrappedTx + (sxIdx + 1) * invSubdiv, osmZ);
              var pNW = project3D(latN, lonW, v.rotLat, v.rotLon, cx, cy, baseRadius, 1.0);
              var pNE = project3D(latN, lonE, v.rotLat, v.rotLon, cx, cy, baseRadius, 1.0);
              var pSW = project3D(latS, lonW, v.rotLat, v.rotLon, cx, cy, baseRadius, 1.0);
              var pSE = project3D(latS, lonE, v.rotLat, v.rotLon, cx, cy, baseRadius, 1.0);
              // Skip back-facing patches.
              if (pNW.z < -0.05 && pNE.z < -0.05 && pSW.z < -0.05 && pSE.z < -0.05) continue;
              // Frustum-cull patches completely outside the screen.
              var patchMinX = Math.min(pNW.x, pNE.x, pSW.x, pSE.x);
              var patchMaxX = Math.max(pNW.x, pNE.x, pSW.x, pSE.x);
              var patchMinY = Math.min(pNW.y, pNE.y, pSW.y, pSE.y);
              var patchMaxY = Math.max(pNW.y, pNE.y, pSW.y, pSE.y);
              if (patchMaxX < -32 || patchMinX > width + 32 || patchMaxY < -32 || patchMinY > height + 32) continue;
              var patchSx = drawable.sx + sxIdx * invSubdiv * drawable.sw;
              var patchSy = drawable.sy + syIdx * invSubdiv * drawable.sh;
              var patchSw = drawable.sw * invSubdiv;
              var patchSh = drawable.sh * invSubdiv;
              // Average the top/bottom and left/right vectors for a seamless
              // spherical quad fit.
              var ux = (pNE.x - pNW.x + (pSE.x - pSW.x)) * 0.5;
              var uy = (pNE.y - pNW.y + (pSE.y - pSW.y)) * 0.5;
              var vx = (pSW.x - pNW.x + (pSE.x - pNE.x)) * 0.5;
              var vy = (pSW.y - pNW.y + (pSE.y - pNE.y)) * 0.5;
              var ox = (pNW.x + pSE.x - ux - vx) * 0.5;
              var oy = (pNW.y + pSE.y - uy - vy) * 0.5;
              ctx.setTransform(
                dpr * (ux / patchSw),
                dpr * (uy / patchSw),
                dpr * (vx / patchSh),
                dpr * (vy / patchSh),
                dpr * ox,
                dpr * oy
              );
              // Slight overlap prevents 1px sub-pixel seams between patches.
              var bleed = subdiv > 1 ? 1.022 : 1.006;
              ctx.drawImage(drawable.img, patchSx, patchSy, patchSw, patchSh, 0, 0, patchSw * bleed, patchSh * bleed);
            }
          }
        }
      }

      // Reset transform & filter for the spherical lighting overlay.
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      ctx.filter = 'none';

      // 3. Spherical specular & limb-darkening overlay.
      var gradRadius = Math.min(baseRadius, maxScreenHalfDiag * 1.35);
      var shadeStrength = Math.max(0.14, Math.min(1, 3.2 / Math.sqrt(v.zoom)));
      var shadeGrad = ctx.createRadialGradient(
        cx - gradRadius * 0.25, cy - gradRadius * 0.25, gradRadius * 0.1,
        cx, cy, gradRadius
      );
      if (isDark) {
        shadeGrad.addColorStop(0, 'rgba(255, 255, 255, 0.02)');
        shadeGrad.addColorStop(0.72, 'rgba(8, 11, 12, 0.05)');
        shadeGrad.addColorStop(1, 'rgba(4, 6, 7, ' + (0.55 * shadeStrength).toFixed(2) + ')');
      } else {
        shadeGrad.addColorStop(0, 'rgba(255, 255, 255, 0.06)');
        shadeGrad.addColorStop(0.75, 'rgba(8, 11, 12, 0.02)');
        shadeGrad.addColorStop(1, 'rgba(8, 11, 12, ' + (0.26 * shadeStrength).toFixed(2) + ')');
      }
      ctx.fillStyle = shadeGrad;
      ctx.fillRect(0, 0, width, height);

      // Subtle graticule (global/regional zoom only).
      if (osmZ <= 6) {
        ctx.strokeStyle = isDark ? 'rgba(198, 244, 50, 0.08)' : 'rgba(8, 11, 12, 0.06)';
        ctx.lineWidth = 0.65;
        for (var glon = -180; glon < 180; glon += 30) {
          ctx.beginPath();
          var gStarted = false;
          for (var glat = -75; glat <= 75; glat += 6) {
            var gp = project3D(glat, glon, v.rotLat, v.rotLon, cx, cy, baseRadius, 1.0);
            if (gp.z > 0) {
              if (!gStarted) { ctx.moveTo(gp.x, gp.y); gStarted = true; }
              else ctx.lineTo(gp.x, gp.y);
            } else gStarted = false;
          }
          ctx.stroke();
        }
      }

      ctx.restore(); // End sphere clip

      // Rim ring
      ctx.beginPath();
      ctx.arc(cx, cy, baseRadius, 0, Math.PI * 2);
      ctx.strokeStyle = isDark ? 'rgba(198, 244, 50, 0.45)' : '#080B0C';
      ctx.lineWidth = isDark ? 1.5 : 1.75;
      ctx.stroke();

      // 5. Corridor trajectories
      var newHitTargets = [];
      // Scale 3D arc elevation smoothly down as the user zooms in close
      // to street level (React reference): at 2500x a fixed arch would
      // tower absurdly over the viewport.
      var archFactor = Math.min(1, 2.2 / Math.pow(v.zoom, 0.65));
      var sortedShipments = (shipments || []).slice().sort(function (a, b) {
        var aFocus = a.id === currentFocusId ? 1 : 0;
        var bFocus = b.id === currentFocusId ? 1 : 0;
        return aFocus - bFocus;
      });

      sortedShipments.forEach(function (shipment) {
        if (!shipment.origin || !shipment.destination) return;
        var cached = arcCache.get(shipment.id);
        if (!cached) return;
        var isFocused = currentFocusId !== 0 && shipment.id === currentFocusId;
        var isDimmed = currentFocusId !== 0 && shipment.id !== currentFocusId;
        var originCoord = cached.originCoord;
        var destCoord = cached.destCoord;
        var intermediateStops = cached.intermediateStops;

        var primaryColor = shipment.status === 'DELIVERED'
          ? (isDark ? '#10B981' : '#047857')
          : shipment.delayed
            ? (isDark ? '#F59E0B' : '#D97706')
            : (isDark ? '#C6F432' : '#080B0C');

        // Stroke precomputed great-circle points, clipped to the viewport
        // first: at deep zoom the raw coordinates sit tens of thousands
        // of px off-screen and Canvas 2D would rasterize the whole
        // dashed path (Liang-Barsky, React reference).
        var drawElevatedArcSegment = function (pts, dashed, color, lineWidth, alpha) {
          var vpPad = 80;
          var projectPts = function (withArch) {
            var out = [];
            for (var i = 0; i < pts.length; i += 1) {
              var t = i / (pts.length - 1);
              var archBoost = withArch
                ? Math.sin(t * Math.PI) * archFactor * (isFocused ? 0.055 : 0.025)
                : 0;
              out.push(project3D(pts[i][0], pts[i][1], v.rotLat, v.rotLon, cx, cy, baseRadius, 1.0 + archBoost));
            }
            return out;
          };
          var strokeClipped = function (projected) {
            ctx.beginPath();
            var hasSubpath = false;
            var lastX = 0;
            var lastY = 0;
            for (var i = 0; i < projected.length - 1; i += 1) {
              var a = projected[i];
              var b = projected[i + 1];
              if (a.z <= -0.02 || b.z <= -0.02) { hasSubpath = false; continue; }
              var clipped = clipSegmentToViewport(a.x, a.y, b.x, b.y, -vpPad, -vpPad, width + vpPad, height + vpPad);
              if (!clipped) { hasSubpath = false; continue; }
              if (!hasSubpath || Math.hypot(clipped[0] - lastX, clipped[1] - lastY) > 1.5) {
                ctx.moveTo(clipped[0], clipped[1]);
                hasSubpath = true;
              }
              ctx.lineTo(clipped[2], clipped[3]);
              lastX = clipped[2];
              lastY = clipped[3];
            }
          };
          if (isFocused && archFactor > 0.15) {
            ctx.save();
            strokeClipped(projectPts(false));
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
          strokeClipped(projectPts(true));
          ctx.stroke();
          ctx.restore();
        };

        var secondary = isDark ? '#525E64' : '#64748B';
        cached.segments.forEach(function (seg) {
          var isRemaining = seg.kind !== 'completed';
          var w;
          var al;
          if (seg.kind === 'completed') {
            w = isFocused ? (cached.delivered ? 3.0 : 3.2) : (cached.delivered ? 1.5 : 1.6);
            al = isDimmed ? (cached.delivered ? 0.25 : 0.28) : (cached.delivered ? 0.95 : 0.98);
          } else if (seg.kind === 'remaining') {
            w = isFocused ? 2.2 : 1.3;
            al = isDimmed ? 0.22 : 0.88;
          } else {
            w = isFocused ? 2.6 : 1.4;
            al = isDimmed ? 0.25 : (isFocused ? 0.92 : 0.55);
          }
          var col = isRemaining
            ? (isFocused && seg.kind === 'remaining' ? (isDark ? '#D0D8DC' : '#3D4850') : (isFocused ? primaryColor : secondary))
            : (isFocused ? primaryColor : secondary);
          drawElevatedArcSegment(seg.pts, seg.dashed, col, w, al);
        });

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
        scheduleRender();
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
      scheduleRender();
    }

    function resetView() {
      v.targetLat = 14; v.targetLon = 72; v.rotLat = 14; v.rotLon = 72;
      v.targetZoom = 1.08; v.zoom = 1.08;
      setZoomLabel();
      scheduleRender();
    }

    spinBtn.addEventListener('click', function () { setSpin(!autoSpin); });
    controls.querySelector('[data-globe-reset]').addEventListener('click', resetView);
    controls.querySelector('[data-globe-zoom-in]').addEventListener('click', function () { setZoom(v.targetZoom * 1.85); });
    controls.querySelector('[data-globe-zoom-out]').addEventListener('click', function () { setZoom(v.targetZoom / 1.85); });

    canvas.addEventListener('pointerdown', function (e) {
      v.dragging = true;
      v.dragStartX = e.clientX;
      v.dragStartY = e.clientY;
      v.startLat = v.rotLat;
      v.startLon = v.rotLon;
      v.movedDistance = 0;
      try { canvas.setPointerCapture(e.pointerId); } catch (err) { /* noop */ }
      scheduleRender();
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
      scheduleRender();
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
      scheduleRender();
    };
    canvas.addEventListener('pointerup', endDrag);
    canvas.addEventListener('pointercancel', endDrag);
    // Double-click zooms 2.4x deeper (React reference).
    canvas.addEventListener('dblclick', function () { setZoom(v.targetZoom * 2.4); });
    // Smooth logarithmic wheel zoom up to 2500x (React reference):
    // each tick multiplies the target, so zooming feels even from
    // 1x to street level instead of dying out at 2.2x.
    canvas.addEventListener('wheel', function (e) {
      e.preventDefault();
      var factor = Math.exp(-e.deltaY * 0.0022);
      setZoom(v.targetZoom * factor);
    }, { passive: false });

    var onResize = function () { scheduleRender(); };
    var onTheme = function () { scheduleRender(); };
    var onLoad = function () { scheduleRender(); };
    // Tile loads coalesce into one frame: a burst of tiles arriving
    // together used to trigger a full render per tile.
    var onTileLoaded = function () { scheduleRender(); };
    window.addEventListener('resize', onResize);
    window.addEventListener('ct:theme', onTheme);
    window.addEventListener('load', onLoad);
    tileListeners.add(onTileLoaded);
    preloadBaseGlobeTiles();

    buildArcCache();
    centerOnFocus();
    render();

    return {
      render: render,
      setFocus: function (id) {
        currentFocusId = Number(id) || 0;
        centerOnFocus();
        scheduleRender();
      },
      controls: controls,
      destroy: function () {
        destroyed = true;
        frameScheduled = false;
        if (animId) window.cancelAnimationFrame(animId);
        window.removeEventListener('resize', onResize);
        window.removeEventListener('ct:theme', onTheme);
        window.removeEventListener('load', onLoad);
        tileListeners.delete(onTileLoaded);
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
