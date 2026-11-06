import React, { useEffect, useRef, useState } from 'react';
import { Compass, Pause, Play, Plus, Minus, RotateCcw, Crosshair } from 'lucide-react';
import { greatCirclePoints } from '../domain/rules';
import { ShipmentStatus } from '../domain/types';
import { MapShipmentEntry, MapShipmentPoint } from './WorldMap';

const DEG2RAD = Math.PI / 180;
const RAD2DEG = 180 / Math.PI;
const TWO_PI = Math.PI * 2;
const MAX_MERCATOR_LAT = 85.05112878;

/**
 * Multi-Zoom OpenStreetMap Slippy Tile Cache (z = 2 .. 16)
 * Uses official OpenStreetMap raster tiles: https://tile.openstreetmap.org/{z}/{x}/{y}.png
 */
interface CachedTile {
  img: HTMLImageElement;
  loaded: boolean;
  error: boolean;
  lastUsed: number;
}

const tileCache = new Map<string, CachedTile>();
const tileListeners = new Set<() => void>();
let activeRequests = 0;
const MAX_CONCURRENT_REQUESTS = 18;
const requestQueue: { key: string; z: number; x: number; y: number }[] = [];

function notifyTileLoaded() {
  tileListeners.forEach((cb) => cb());
}

function processTileQueue() {
  while (activeRequests < MAX_CONCURRENT_REQUESTS && requestQueue.length > 0) {
    const next = requestQueue.pop();
    if (!next) break;
    const existing = tileCache.get(next.key);
    if (!existing || existing.loaded || existing.error) continue;

    activeRequests += 1;
    const { img } = existing;
    img.onload = () => {
      activeRequests = Math.max(0, activeRequests - 1);
      existing.loaded = true;
      existing.lastUsed = performance.now();
      notifyTileLoaded();
      processTileQueue();
    };
    img.onerror = () => {
      activeRequests = Math.max(0, activeRequests - 1);
      existing.error = true;
      processTileQueue();
    };
    img.src = `https://tile.openstreetmap.org/${next.z}/${next.x}/${next.y}.png`;
  }
}

function requestOsmTile(z: number, x: number, y: number): CachedTile {
  const n = 1 << z;
  const wrappedX = ((x % n) + n) % n;
  const clampedY = Math.max(0, Math.min(n - 1, y));
  const key = `${z}/${wrappedX}/${clampedY}`;

  let entry = tileCache.get(key);
  if (entry) {
    entry.lastUsed = performance.now();
    return entry;
  }

  // Prune old tiles if cache grows beyond 600 tiles
  if (tileCache.size > 600) {
    const entries = Array.from(tileCache.entries())
      .filter(([k, v]) => !k.startsWith('2/') && !k.startsWith('3/') && v.loaded)
      .sort((a, b) => a[1].lastUsed - b[1].lastUsed);
    for (let i = 0; i < Math.min(120, entries.length); i += 1) {
      tileCache.delete(entries[i][0]);
    }
  }

  const img = new Image();
  entry = {
    img,
    loaded: false,
    error: false,
    lastUsed: performance.now(),
  };
  tileCache.set(key, entry);

  requestQueue.push({ key, z, x: wrappedX, y: clampedY });
  // Keep queue bounded so current viewport tiles always load first (LIFO priority)
  if (requestQueue.length > 64) {
    requestQueue.splice(0, requestQueue.length - 64);
  }
  processTileQueue();
  return entry;
}

// Preload base globe tiles (z = 2) once so the whole-earth 3D globe is ready immediately
let basePreloaded = false;
function preloadBaseGlobeTiles() {
  if (basePreloaded || typeof window === 'undefined') return;
  basePreloaded = true;
  for (let y = 0; y < 4; y += 1) {
    for (let x = 0; x < 4; x += 1) {
      requestOsmTile(2, x, y);
    }
  }
}

/**
 * Web Mercator tile coordinate helpers
 */
function lonToTileX(lon: number, z: number): number {
  const n = 1 << z;
  return Math.floor((((lon + 180) / 360) % 1 + 1) % 1 * n);
}

function latToTileY(lat: number, z: number): number {
  const clamped = Math.max(-MAX_MERCATOR_LAT, Math.min(MAX_MERCATOR_LAT, lat));
  const rad = clamped * DEG2RAD;
  const n = 1 << z;
  const merc = Math.log(Math.tan(Math.PI / 4 + rad / 2));
  return Math.max(0, Math.min(n - 1, Math.floor((1 - merc / Math.PI) * 0.5 * n)));
}

function tileXToLon(x: number, z: number): number {
  return (x / (1 << z)) * 360 - 180;
}

function tileYToLat(y: number, z: number): number {
  const n = Math.PI - (TWO_PI * y) / (1 << z);
  return RAD2DEG * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n)));
}

interface ProjectedPoint {
  x: number;
  y: number;
  z: number; // z > 0 means on front hemisphere facing camera
  visible: boolean;
}

/**
 * Orthographic 3D sphere projection with optional altitude (above surface radius = 1).
 */
function project3D(
  lat: number,
  lon: number,
  rotLat: number,
  rotLon: number,
  cx: number,
  cy: number,
  radius: number,
  altitude = 1.0,
): ProjectedPoint {
  const phi = lat * DEG2RAD;
  const lambda = (lon - rotLon) * DEG2RAD;
  const phi0 = rotLat * DEG2RAD;

  const cosPhi = Math.cos(phi);
  const sinPhi = Math.sin(phi);
  const cosPhi0 = Math.cos(phi0);
  const sinPhi0 = Math.sin(phi0);
  const cosLambda = Math.cos(lambda);
  const sinLambda = Math.sin(lambda);

  const xUnit = cosPhi * sinLambda;
  const yUnit = cosPhi0 * sinPhi - sinPhi0 * cosPhi * cosLambda;
  const zUnit = sinPhi0 * sinPhi + cosPhi0 * cosPhi * cosLambda;

  return {
    x: cx + xUnit * radius * altitude,
    y: cy - yUnit * radius * altitude,
    z: zUnit,
    visible: zUnit > -0.05,
  };
}

/**
 * Liang-Barsky 2D line segment clipper against viewport rectangle [minX, minY, maxX, maxY].
 * Prevents Canvas 2D from rasterizing 100,000px off-screen dashed paths during deep 2500x street zoom.
 */
function clipSegmentToViewport(
  x0: number,
  y0: number,
  x1: number,
  y1: number,
  minX: number,
  minY: number,
  maxX: number,
  maxY: number,
): [number, number, number, number] | null {
  const dx = x1 - x0;
  const dy = y1 - y0;
  let t0 = 0;
  let t1 = 1;

  const p = [-dx, dx, -dy, dy];
  const q = [x0 - minX, maxX - x0, y0 - minY, maxY - y0];

  for (let i = 0; i < 4; i += 1) {
    const pi = p[i];
    const qi = q[i];
    if (pi === 0) {
      if (qi < 0) return null;
    } else {
      const r = qi / pi;
      if (pi < 0) {
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

/**
 * Find the best available tile image in cache for (z, x, y), falling back to loaded parent/ancestor tiles
 * so the 3D globe never shows blank holes while zooming in or panning.
 */
function resolveTileDrawable(
  z: number,
  x: number,
  y: number,
  allowNetworkFetch = true,
): { img: HTMLImageElement; sx: number; sy: number; sw: number; sh: number } | null {
  const n = 1 << z;
  const wrappedX = ((x % n) + n) % n;
  const clampedY = Math.max(0, Math.min(n - 1, y));
  const exactKey = `${z}/${wrappedX}/${clampedY}`;
  const exact = allowNetworkFetch
    ? requestOsmTile(z, wrappedX, clampedY)
    : tileCache.get(exactKey);
  if (exact && exact.loaded && exact.img.complete && exact.img.naturalWidth > 0) {
    return { img: exact.img, sx: 0, sy: 0, sw: 256, sh: 256 };
  }

  // Fallback up the quadtree to the nearest loaded ancestor tile
  for (let ancZ = z - 1; ancZ >= 2; ancZ -= 1) {
    const shift = z - ancZ;
    const ancX = wrappedX >> shift;
    const ancY = clampedY >> shift;
    const ancKey = `${ancZ}/${ancX}/${ancY}`;
    const anc = tileCache.get(ancKey) ?? (ancZ === 2 ? requestOsmTile(2, ancX, ancY) : undefined);
    if (anc && anc.loaded && anc.img.complete && anc.img.naturalWidth > 0) {
      const scale = 1 << shift;
      const subW = 256 / scale;
      const subX = (wrappedX - (ancX << shift)) * subW;
      const subY = (clampedY - (ancY << shift)) * subW;
      return { img: anc.img, sx: subX, sy: subY, sw: subW, sh: subW };
    }
  }
  return null;
}

export const Globe3DCanvas: React.FC<{
  shipments: MapShipmentEntry[];
  focusId: number;
  theme: 'light' | 'dark';
  onSelectShipment?: (id: number) => void;
}> = ({ shipments, focusId, theme, onSelectShipment }) => {
  const wrapperRef = useRef<HTMLDivElement | null>(null);
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const requestDrawRef = useRef<() => void>(() => {});
  const hudBadgeRef = useRef<HTMLSpanElement | null>(null);
  const [autoSpin, setAutoSpin] = useState<boolean>(false);

  // Mutable camera state — updated at 60fps with ZERO React re-renders during drag/wheel
  const viewRef = useRef({
    rotLat: 15,
    rotLon: 65,
    targetLat: 15,
    targetLon: 65,
    zoom: 1.08,
    targetZoom: 1.08,
    dragging: false,
    dragStartX: 0,
    dragStartY: 0,
    startLat: 15,
    startLon: 65,
    movedDistance: 0,
    dashPhase: 0,
  });

  // Hit targets populated on each draw for click-to-select shipment or click-to-zoom port
  const hitTargetsRef = useRef<
    { id: number; x: number; y: number; r: number; lat?: number; lon?: number }[]
  >([]);

  // Center globe on focused shipment corridor when focusId/shipments change
  useEffect(() => {
    preloadBaseGlobeTiles();
    const target =
      focusId !== 0
        ? shipments.find((s) => s.id === focusId)
        : shipments[0];

    if (target) {
      const pts: [number, number][] = [];
      if (target.origin) pts.push([target.origin.latitude, target.origin.longitude]);
      if (target.current_position) {
        pts.push([target.current_position.latitude, target.current_position.longitude]);
      }
      if (target.destination) {
        pts.push([target.destination.latitude, target.destination.longitude]);
      }
      if (pts.length > 0) {
        const avgLat = pts.reduce((acc, p) => acc + p[0], 0) / pts.length;
        const avgLon = pts.reduce((acc, p) => acc + p[1], 0) / pts.length;
        const clampedLat = Math.max(-65, Math.min(65, avgLat));
        viewRef.current.targetLat = clampedLat;
        viewRef.current.targetLon = avgLon;
        viewRef.current.rotLat = clampedLat;
        viewRef.current.rotLon = avgLon;
        const nextZoom = focusId !== 0 ? 1.18 : 1.08;
        viewRef.current.targetZoom = nextZoom;
        viewRef.current.zoom = nextZoom;
      }
    }
    requestDrawRef.current();
  }, [focusId, shipments]);

  useEffect(() => {
    const wrapper = wrapperRef.current;
    const canvas = canvasRef.current;
    if (!wrapper || !canvas) return;
    const ctx = canvas.getContext('2d', { alpha: false });
    if (!ctx) return;

    let animId = 0;
    let frameScheduled = false;

    const scheduleFrame = () => {
      if (frameScheduled) return;
      frameScheduled = true;
      animId = requestAnimationFrame(renderFrame);
    };

    requestDrawRef.current = scheduleFrame;
    tileListeners.add(scheduleFrame);

    const renderFrame = () => {
      frameScheduled = false;

      const rect = wrapper.getBoundingClientRect();
      const width = Math.max(280, Math.min(2400, Math.floor(rect.width || 640)));
      const height = Math.max(280, Math.min(1800, Math.floor(rect.height || 460)));
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      const targetW = Math.floor(width * dpr);
      const targetH = Math.floor(height * dpr);

      if (canvas.width !== targetW || canvas.height !== targetH) {
        canvas.width = targetW;
        canvas.height = targetH;
      }

      const v = viewRef.current;
      let keepAnimating = false;

      if (!v.dragging) {
        if (autoSpin) {
          v.targetLon = ((v.targetLon + 0.22 / Math.max(1, Math.sqrt(v.zoom)) + 180) % 360) - 180;
          v.dashPhase = (v.dashPhase + 0.35) % 24;
          keepAnimating = true;
        }
        const dLat = v.targetLat - v.rotLat;
        let dLon = v.targetLon - v.rotLon;
        if (dLon > 180) dLon -= 360;
        if (dLon < -180) dLon += 360;

        const angularEps = 0.015 / Math.max(1, v.zoom);
        if (Math.abs(dLat) > angularEps || Math.abs(dLon) > angularEps) {
          v.rotLat += dLat * 0.22;
          v.rotLon = ((v.rotLon + dLon * 0.22 + 540) % 360) - 180;
          keepAnimating = true;
        } else {
          v.rotLat = v.targetLat;
          v.rotLon = v.targetLon;
        }
      }

      // Smooth logarithmic zoom interpolation (supports 0.75x up to 2500x street level!)
      const zoomRatio = v.targetZoom / v.zoom;
      const isZoomSettled = Math.abs(zoomRatio - 1) < 0.14;
      if (Math.abs(zoomRatio - 1) > 0.004) {
        v.zoom *= Math.pow(zoomRatio, 0.34);
        keepAnimating = true;
      } else {
        v.zoom = v.targetZoom;
      }

      // Select OpenStreetMap slippy tile zoom level z (from z = 2 up to z = 15 street/port level)
      const osmZ = Math.max(2, Math.min(15, Math.round(Math.log2(Math.max(0.75, v.zoom)) + 2.55)));
      const targetOsmZ = Math.max(
        2,
        Math.min(15, Math.round(Math.log2(Math.max(0.75, v.targetZoom)) + 2.55)),
      );
      if (hudBadgeRef.current) {
        const zoomText =
          v.zoom >= 10
            ? `${Math.round(v.zoom)}x · OSM z${osmZ}`
            : `${Math.round(v.zoom * 100)}% · OSM z${osmZ}`;
        hudBadgeRef.current.textContent = `© OpenStreetMap · 3D Globe (${zoomText})`;
      }

      const isDark = theme === 'dark';
      const cx = width / 2;
      const cy = height / 2;
      const baseRadius = Math.min(width, height) * 0.39 * v.zoom;

      ctx.save();
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

      // 1. Space / Map Backdrop
      ctx.fillStyle = isDark ? '#060809' : '#DDE5EA';
      ctx.fillRect(0, 0, width, height);

      // Radial halo when the globe rim is visible inside viewport
      const maxScreenHalfDiag = Math.hypot(cx, cy);
      const isRimVisible = baseRadius < maxScreenHalfDiag * 1.05;
      if (isRimVisible) {
        const haloGrad = ctx.createRadialGradient(
          cx,
          cy,
          baseRadius * 0.85,
          cx,
          cy,
          baseRadius * 1.2,
        );
        if (isDark) {
          haloGrad.addColorStop(0, 'rgba(198, 244, 50, 0.14)');
          haloGrad.addColorStop(1, 'rgba(8, 11, 12, 0)');
        } else {
          haloGrad.addColorStop(0, 'rgba(8, 11, 12, 0.14)');
          haloGrad.addColorStop(1, 'rgba(221, 229, 234, 0)');
        }
        ctx.fillStyle = haloGrad;
        ctx.beginPath();
        ctx.arc(cx, cy, baseRadius * 1.2, 0, TWO_PI);
        ctx.fill();
      }

      // 2. Clip to 3D Globe Sphere (only when sphere rim is inside viewport) & Render GPU-Accelerated OpenStreetMap Slippy Tiles
      ctx.save();
      if (isRimVisible) {
        ctx.beginPath();
        ctx.arc(cx, cy, baseRadius, 0, TWO_PI);
        ctx.clip();
      }

      // Ocean base fill inside sphere
      ctx.fillStyle = isDark ? '#0D151A' : '#AAD3DF';
      ctx.fillRect(0, 0, width, height);

      // Apply Dark Mode filter to OpenStreetMap tiles (matching 2D OpenStreetMap dark mode)
      if (isDark) {
        ctx.filter = 'invert(0.86) hue-rotate(180deg) brightness(1.18) contrast(1.22) saturate(1.12)';
      }

      // Determine visible geographic bounding box [minLat, maxLat], [minLon, maxLon]
      const sinAngularRadius = Math.min(1, (maxScreenHalfDiag / baseRadius) * 1.08);
      const angularRadiusDeg = Math.min(89.5, Math.asin(sinAngularRadius) * RAD2DEG * 1.15);

      const minLat = Math.max(-MAX_MERCATOR_LAT, v.rotLat - angularRadiusDeg);
      const maxLat = Math.min(MAX_MERCATOR_LAT, v.rotLat + angularRadiusDeg);

      const nTiles = 1 << osmZ;
      const yMin = latToTileY(maxLat, osmZ);
      const yMax = latToTileY(minLat, osmZ);

      let xMin = 0;
      let xMax = nTiles - 1;
      // When zoomed in enough that the pole is not inside the view, restrict X tile range to visible longitudes
      if (Math.abs(v.rotLat) + angularRadiusDeg < 82) {
        const cosLatFactor = Math.max(0.15, Math.cos(v.rotLat * DEG2RAD));
        const lonSpan = Math.min(179, (angularRadiusDeg / cosLatFactor) * 1.2);
        xMin = Math.floor(((v.rotLon - lonSpan + 180) / 360) * nTiles);
        xMax = Math.floor(((v.rotLon + lonSpan + 180) / 360) * nTiles);
        if (xMax - xMin >= nTiles) {
          xMin = 0;
          xMax = nTiles - 1;
        }
      }

      // Subdivide low-zoom tiles (z=2..4) into a small mesh so spherical curvature is smooth;
      // at z >= 5 each tile spans < 11 degrees and is locally planar on the tangent sphere
      const subdiv = osmZ <= 2 ? 4 : osmZ === 3 ? 3 : osmZ === 4 ? 2 : 1;
      const invSubdiv = 1 / subdiv;
      const allowTileFetch = isZoomSettled || osmZ === targetOsmZ || osmZ <= 3;

      for (let ty = yMin; ty <= yMax; ty += 1) {
        for (let tx = xMin; tx <= xMax; tx += 1) {
          const wrappedTx = ((tx % nTiles) + nTiles) % nTiles;
          const drawable = resolveTileDrawable(osmZ, wrappedTx, ty, allowTileFetch);
          if (!drawable) continue;

          for (let syIdx = 0; syIdx < subdiv; syIdx += 1) {
            const latN = tileYToLat(ty + syIdx * invSubdiv, osmZ);
            const latS = tileYToLat(ty + (syIdx + 1) * invSubdiv, osmZ);

            for (let sxIdx = 0; sxIdx < subdiv; sxIdx += 1) {
              const lonW = tileXToLon(wrappedTx + sxIdx * invSubdiv, osmZ);
              const lonE = tileXToLon(wrappedTx + (sxIdx + 1) * invSubdiv, osmZ);

              const pNW = project3D(latN, lonW, v.rotLat, v.rotLon, cx, cy, baseRadius);
              const pNE = project3D(latN, lonE, v.rotLat, v.rotLon, cx, cy, baseRadius);
              const pSW = project3D(latS, lonW, v.rotLat, v.rotLon, cx, cy, baseRadius);
              const pSE = project3D(latS, lonE, v.rotLat, v.rotLon, cx, cy, baseRadius);

              // Skip back-facing patches
              if (pNW.z < -0.05 && pNE.z < -0.05 && pSW.z < -0.05 && pSE.z < -0.05) {
                continue;
              }

              // Frustum cull patches completely outside screen bounds
              const minX = Math.min(pNW.x, pNE.x, pSW.x, pSE.x);
              const maxX = Math.max(pNW.x, pNE.x, pSW.x, pSE.x);
              const minY = Math.min(pNW.y, pNE.y, pSW.y, pSE.y);
              const maxY = Math.max(pNW.y, pNE.y, pSW.y, pSE.y);
              if (maxX < -32 || minX > width + 32 || maxY < -32 || minY > height + 32) {
                continue;
              }

              const patchSx = drawable.sx + sxIdx * invSubdiv * drawable.sw;
              const patchSy = drawable.sy + syIdx * invSubdiv * drawable.sh;
              const patchSw = drawable.sw * invSubdiv;
              const patchSh = drawable.sh * invSubdiv;

              // Average the top/bottom and left/right vectors for a seamless spherical quad fit
              const ux = (pNE.x - pNW.x + (pSE.x - pSW.x)) * 0.5;
              const uy = (pNE.y - pNW.y + (pSE.y - pSW.y)) * 0.5;
              const vx = (pSW.x - pNW.x + (pSE.x - pNE.x)) * 0.5;
              const vy = (pSW.y - pNW.y + (pSE.y - pNE.y)) * 0.5;

              const ox = (pNW.x + pSE.x - ux - vx) * 0.5;
              const oy = (pNW.y + pSE.y - uy - vy) * 0.5;

              ctx.setTransform(
                dpr * (ux / patchSw),
                dpr * (uy / patchSw),
                dpr * (vx / patchSh),
                dpr * (vy / patchSh),
                dpr * ox,
                dpr * oy,
              );

              // Slight 1.5% overlap prevents 1px sub-pixel seams between adjacent spherical patches
              const bleed = subdiv > 1 ? 1.022 : 1.006;
              ctx.drawImage(
                drawable.img,
                patchSx,
                patchSy,
                patchSw,
                patchSh,
                0,
                0,
                patchSw * bleed,
                patchSh * bleed,
              );
            }
          }
        }
      }

      // Reset transform & filter for 3D spherical lighting overlay
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      ctx.filter = 'none';

      // 3. 3D Spherical Specular & Limb-Darkening Atmosphere Overlay (capped radius for GPU safety)
      const gradRadius = Math.min(baseRadius, maxScreenHalfDiag * 1.35);
      const shadeStrength = Math.max(0.14, Math.min(1, 3.2 / Math.sqrt(v.zoom)));
      const shadeGrad = ctx.createRadialGradient(
        cx - gradRadius * 0.25,
        cy - gradRadius * 0.25,
        gradRadius * 0.1,
        cx,
        cy,
        gradRadius,
      );
      if (isDark) {
        shadeGrad.addColorStop(0, 'rgba(255, 255, 255, 0.02)');
        shadeGrad.addColorStop(0.72, 'rgba(8, 11, 12, 0.05)');
        shadeGrad.addColorStop(1, `rgba(4, 6, 7, ${(0.55 * shadeStrength).toFixed(2)})`);
      } else {
        shadeGrad.addColorStop(0, 'rgba(255, 255, 255, 0.06)');
        shadeGrad.addColorStop(0.75, 'rgba(8, 11, 12, 0.02)');
        shadeGrad.addColorStop(1, `rgba(8, 11, 12, ${(0.26 * shadeStrength).toFixed(2)})`);
      }
      ctx.fillStyle = shadeGrad;
      ctx.fillRect(0, 0, width, height);

      // Subtle Spherical Graticule Grid (only shown at global/regional zoom z <= 6)
      if (osmZ <= 6) {
        ctx.strokeStyle = isDark ? 'rgba(198, 244, 50, 0.08)' : 'rgba(8, 11, 12, 0.06)';
        ctx.lineWidth = 0.65;
        for (let lon = -180; lon < 180; lon += 30) {
          ctx.beginPath();
          let started = false;
          for (let lat = -75; lat <= 75; lat += 6) {
            const p = project3D(lat, lon, v.rotLat, v.rotLon, cx, cy, baseRadius);
            if (p.z > 0) {
              if (!started) {
                ctx.moveTo(p.x, p.y);
                started = true;
              } else {
                ctx.lineTo(p.x, p.y);
              }
            } else {
              started = false;
            }
          }
          ctx.stroke();
        }
      }

      ctx.restore(); // End sphere clip

      // 4. Crisp Globe Rim Border
      if (isRimVisible) {
        ctx.beginPath();
        ctx.arc(cx, cy, baseRadius, 0, TWO_PI);
        ctx.strokeStyle = isDark ? 'rgba(198, 244, 50, 0.55)' : '#080B0C';
        ctx.lineWidth = 1.75;
        ctx.stroke();
      }

      // 5. Render Multi-Leg 3D Corridor Trajectories (Origin ━━▶ Transit Stops ━━▶ Destination)
      const newHitTargets: {
        id: number;
        x: number;
        y: number;
        r: number;
        lat?: number;
        lon?: number;
      }[] = [];

      const isNearCoord = (a: [number, number], b: [number, number], eps = 0.02) =>
        Math.abs(a[0] - b[0]) < eps && Math.abs(a[1] - b[1]) < eps;

      // Scale 3D arc elevation smoothly down as the user zooms in close to street level
      const archFactor = Math.min(1, 2.2 / Math.pow(v.zoom, 0.65));

      const sortedShipments = [...shipments].sort((a, b) => {
        const aFocus = a.id === focusId ? 1 : 0;
        const bFocus = b.id === focusId ? 1 : 0;
        return aFocus - bFocus;
      });

      sortedShipments.forEach((shipment) => {
        if (!shipment.origin || !shipment.destination) return;

        const isFocused = focusId !== 0 && shipment.id === focusId;
        const isDimmed = focusId !== 0 && shipment.id !== focusId;

        const originCoord: [number, number] = [
          shipment.origin.latitude,
          shipment.origin.longitude,
        ];
        const destCoord: [number, number] = [
          shipment.destination.latitude,
          shipment.destination.longitude,
        ];

        const rawCandidates: MapShipmentPoint[] = [];
        if (shipment.checkpoints && shipment.checkpoints.length > 0) {
          rawCandidates.push(...[...shipment.checkpoints].reverse());
        }
        if (shipment.current_position) {
          rawCandidates.push(shipment.current_position);
        }

        const intermediateStops: MapShipmentPoint[] = [];
        rawCandidates.forEach((pt) => {
          const coord: [number, number] = [pt.latitude, pt.longitude];
          if (isNearCoord(coord, originCoord) || isNearCoord(coord, destCoord)) return;
          const dup = intermediateStops.some((ex) =>
            isNearCoord([ex.latitude, ex.longitude], coord),
          );
          if (!dup) intermediateStops.push(pt);
        });

        const orderedWaypoints: [number, number][] = [
          originCoord,
          ...intermediateStops.map((pt): [number, number] => [pt.latitude, pt.longitude]),
        ];
        const hasMovedFromOrigin = orderedWaypoints.length >= 2;

        const primaryColor =
          shipment.status === ShipmentStatus.Delivered
            ? isDark
              ? '#10B981'
              : '#047857'
            : shipment.delayed
            ? isDark
              ? '#F59E0B'
              : '#D97706'
            : isDark
            ? '#C6F432'
            : '#080B0C';

        const drawElevatedArcSegment = (
          start: [number, number],
          end: [number, number],
          dashed: boolean,
          color: string,
          lineWidth: number,
          alpha: number,
        ) => {
          const steps = 40;
          const pts = greatCirclePoints(start, end, steps);
          const vpPad = 80;

          const strokeClippedPolyline = (projectedPts: ProjectedPoint[]) => {
            let hasSubpath = false;
            let lastX = 0;
            let lastY = 0;
            for (let i = 0; i < projectedPts.length - 1; i += 1) {
              const a = projectedPts[i];
              const b = projectedPts[i + 1];
              if (a.z <= -0.02 || b.z <= -0.02) {
                hasSubpath = false;
                continue;
              }
              const clipped = clipSegmentToViewport(
                a.x,
                a.y,
                b.x,
                b.y,
                -vpPad,
                -vpPad,
                width + vpPad,
                height + vpPad,
              );
              if (!clipped) {
                hasSubpath = false;
                continue;
              }
              const [cx0, cy0, cx1, cy1] = clipped;
              if (!hasSubpath || Math.hypot(cx0 - lastX, cy0 - lastY) > 1.5) {
                ctx.moveTo(cx0, cy0);
                hasSubpath = true;
              }
              ctx.lineTo(cx1, cy1);
              lastX = cx1;
              lastY = cy1;
            }
          };

          // Surface shadow line on OpenStreetMap globe (altitude = 1.0)
          if (isFocused && archFactor > 0.15) {
            const shadowPts = pts.map(([lat, lon]) =>
              project3D(lat, lon, v.rotLat, v.rotLon, cx, cy, baseRadius, 1.0),
            );
            ctx.save();
            ctx.beginPath();
            strokeClippedPolyline(shadowPts);
            ctx.strokeStyle = isDark ? 'rgba(0,0,0,0.55)' : 'rgba(8,11,12,0.22)';
            ctx.lineWidth = lineWidth + 0.5;
            ctx.stroke();
            ctx.restore();
          }

          // Elevated 3D Great-Circle Arc
          const arcPts = pts.map(([lat, lon], idx) => {
            const t = idx / steps;
            const archBoost =
              (isFocused ? Math.sin(t * Math.PI) * 0.05 : Math.sin(t * Math.PI) * 0.022) *
              archFactor;
            return project3D(
              lat,
              lon,
              v.rotLat,
              v.rotLon,
              cx,
              cy,
              baseRadius,
              1.0 + archBoost,
            );
          });

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
          strokeClippedPolyline(arcPts);
          ctx.stroke();
          ctx.restore();
        };

        if (shipment.status === ShipmentStatus.Delivered) {
          const allPts = [...orderedWaypoints, destCoord];
          for (let i = 0; i < allPts.length - 1; i += 1) {
            drawElevatedArcSegment(
              allPts[i],
              allPts[i + 1],
              false,
              isFocused ? primaryColor : isDark ? '#525E64' : '#475569',
              isFocused ? 3.2 : 1.5,
              isDimmed ? 0.3 : 0.96,
            );
          }
        } else if (hasMovedFromOrigin) {
          for (let i = 0; i < orderedWaypoints.length - 1; i += 1) {
            drawElevatedArcSegment(
              orderedWaypoints[i],
              orderedWaypoints[i + 1],
              false,
              isFocused ? primaryColor : isDark ? '#525E64' : '#475569',
              isFocused ? 3.4 : 1.6,
              isDimmed ? 0.32 : 0.98,
            );
          }
          const latestStop = orderedWaypoints[orderedWaypoints.length - 1];
          drawElevatedArcSegment(
            latestStop,
            destCoord,
            true,
            isFocused ? (isDark ? '#E2E8F0' : '#1E293B') : isDark ? '#525E64' : '#475569',
            isFocused ? 2.4 : 1.3,
            isDimmed ? 0.28 : 0.9,
          );
        } else {
          drawElevatedArcSegment(
            originCoord,
            destCoord,
            true,
            isFocused ? primaryColor : isDark ? '#525E64' : '#475569',
            isFocused ? 2.8 : 1.4,
            isDimmed ? 0.28 : isFocused ? 0.94 : 0.58,
          );
        }

        // Draw Port & Transit Stop Nodes on Front Hemisphere
        const drawNode = (
          coord: [number, number],
          radius: number,
          fill: string,
          stroke: string,
          badgeText?: string,
          badgeTone: 'volt' | 'surface' = 'surface',
        ) => {
          const p = project3D(coord[0], coord[1], v.rotLat, v.rotLon, cx, cy, baseRadius, 1.0);
          if (p.z <= 0) return null;
          if (p.x < -60 || p.x > width + 60 || p.y < -60 || p.y > height + 60) return null;

          ctx.save();
          ctx.globalAlpha = isDimmed ? 0.38 : 1;
          ctx.beginPath();
          ctx.arc(p.x, p.y, radius, 0, TWO_PI);
          ctx.fillStyle = fill;
          ctx.fill();
          ctx.lineWidth = 2;
          ctx.strokeStyle = stroke;
          ctx.stroke();

          if (badgeText && isFocused) {
            ctx.font = '700 10px "IBM Plex Mono", monospace';
            const textW = ctx.measureText(badgeText).width;
            const padX = 6;
            const boxW = textW + padX * 2;
            const boxH = 18;
            const boxX = p.x - boxW / 2;
            const boxY = p.y - radius - boxH - 5;

            ctx.fillStyle =
              badgeTone === 'volt' ? '#C6F432' : isDark ? '#0D1214' : '#FFFFFF';
            ctx.strokeStyle = '#080B0C';
            ctx.lineWidth = 1.25;
            ctx.beginPath();
            ctx.roundRect(boxX, boxY, boxW, boxH, 4);
            ctx.fill();
            ctx.stroke();

            ctx.fillStyle =
              badgeTone === 'volt' ? '#080B0C' : isDark ? '#FFFFFF' : '#080B0C';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(badgeText, p.x, boxY + boxH / 2 + 0.5);
          }

          ctx.restore();
          newHitTargets.push({
            id: shipment.id,
            x: p.x,
            y: p.y,
            r: 14,
            lat: coord[0],
            lon: coord[1],
          });
          return p;
        };

        // Origin Node
        drawNode(
          originCoord,
          isFocused ? 5.5 : 3.5,
          isDark ? '#080B0C' : '#FFFFFF',
          isDark ? '#FFFFFF' : '#080B0C',
          shipment.origin.code ? `Origin: ${shipment.origin.code}` : undefined,
          'surface',
        );

        // Destination Node
        drawNode(
          destCoord,
          isFocused ? 5.5 : 3.5,
          isFocused ? primaryColor : isDark ? '#172024' : '#FFFFFF',
          isDark ? '#FFFFFF' : '#080B0C',
          shipment.destination.code ? `Dest: ${shipment.destination.code}` : undefined,
          'surface',
        );

        // Intermediate Transit Checkpoints
        if (isFocused) {
          intermediateStops.forEach((cp, idx) => {
            drawNode(
              [cp.latitude, cp.longitude],
              5.5,
              '#C6F432',
              '#080B0C',
              `#${idx + 1} ${cp.location_name.split('·')[0].trim()}`,
              'volt',
            );
          });
        }

        // Current Telemetry Vessel / Aircraft Marker
        if (shipment.current_position) {
          const curP = project3D(
            shipment.current_position.latitude,
            shipment.current_position.longitude,
            v.rotLat,
            v.rotLon,
            cx,
            cy,
            baseRadius,
            1 + 0.018 * archFactor,
          );
          if (curP.z > 0 && curP.x >= -40 && curP.x <= width + 40 && curP.y >= -40 && curP.y <= height + 40) {
            ctx.save();
            ctx.globalAlpha = isDimmed ? 0.4 : 1;
            ctx.beginPath();
            ctx.arc(curP.x, curP.y, isFocused ? 8 : 6, 0, TWO_PI);
            ctx.fillStyle = isFocused ? '#C6F432' : '#080B0C';
            ctx.fill();
            ctx.lineWidth = 2;
            ctx.strokeStyle = isFocused
              ? '#080B0C'
              : shipment.delayed
              ? '#F59E0B'
              : '#C6F432';
            ctx.stroke();

            ctx.beginPath();
            ctx.arc(curP.x, curP.y, 2.5, 0, TWO_PI);
            ctx.fillStyle = isFocused ? '#080B0C' : '#C6F432';
            ctx.fill();
            ctx.restore();

            newHitTargets.push({
              id: shipment.id,
              x: curP.x,
              y: curP.y,
              r: 16,
              lat: shipment.current_position.latitude,
              lon: shipment.current_position.longitude,
            });
          }
        }
      });

      hitTargetsRef.current = newHitTargets;
      ctx.restore();

      if (keepAnimating) {
        scheduleFrame();
      }
    };

    scheduleFrame();

    const ro = new ResizeObserver(() => scheduleFrame());
    ro.observe(wrapper);

    // Non-passive wheel listener for smooth logarithmic zoom up to 2500x (street/port level)
    const onWheel = (e: WheelEvent) => {
      e.preventDefault();
      const v = viewRef.current;
      const factor = Math.exp(-e.deltaY * 0.0022);
      v.targetZoom = Math.max(0.75, Math.min(2500, v.targetZoom * factor));
      scheduleFrame();
    };
    canvas.addEventListener('wheel', onWheel, { passive: false });

    return () => {
      cancelAnimationFrame(animId);
      ro.disconnect();
      tileListeners.delete(scheduleFrame);
      canvas.removeEventListener('wheel', onWheel);
    };
  }, [shipments, focusId, theme, autoSpin]);

  // Pointer drag + double-click zoom + click hit-testing (ZERO React state updates during drag!)
  const handlePointerDown = (e: React.PointerEvent<HTMLCanvasElement>) => {
    const v = viewRef.current;
    v.dragging = true;
    v.dragStartX = e.clientX;
    v.dragStartY = e.clientY;
    v.startLat = v.rotLat;
    v.startLon = v.rotLon;
    v.movedDistance = 0;
    e.currentTarget.setPointerCapture(e.pointerId);
    requestDrawRef.current();
  };

  const handlePointerMove = (e: React.PointerEvent<HTMLCanvasElement>) => {
    const v = viewRef.current;
    if (!v.dragging) return;
    const dx = e.clientX - v.dragStartX;
    const dy = e.clientY - v.dragStartY;
    v.movedDistance = Math.hypot(dx, dy);

    // Scale drag sensitivity smoothly with current zoom so street-level panning is 1:1 with cursor
    const sensitivity = 0.24 / v.zoom;
    const cosLat = Math.max(0.2, Math.cos(v.rotLat * DEG2RAD));
    v.rotLon = ((v.startLon - (dx * sensitivity) / cosLat + 540) % 360) - 180;
    v.rotLat = Math.max(-82, Math.min(82, v.startLat + dy * sensitivity));
    v.targetLon = v.rotLon;
    v.targetLat = v.rotLat;
    requestDrawRef.current();
  };

  const handlePointerUp = (e: React.PointerEvent<HTMLCanvasElement>) => {
    const v = viewRef.current;
    if (!v.dragging) return;
    v.dragging = false;
    e.currentTarget.releasePointerCapture(e.pointerId);

    if (v.movedDistance < 6 && canvasRef.current) {
      const rect = canvasRef.current.getBoundingClientRect();
      const clickX = e.clientX - rect.left;
      const clickY = e.clientY - rect.top;
      const hit = hitTargetsRef.current.find(
        (t) => Math.hypot(t.x - clickX, t.y - clickY) <= t.r,
      );
      if (hit) {
        if (onSelectShipment) {
          onSelectShipment(hit.id);
        }
        if (hit.lat !== undefined && hit.lon !== undefined) {
          v.targetLat = hit.lat;
          v.targetLon = hit.lon;
        }
      }
    }
    requestDrawRef.current();
  };

  const handleDoubleClick = () => {
    const v = viewRef.current;
    v.targetZoom = Math.min(2500, v.targetZoom * 2.4);
    requestDrawRef.current();
  };

  const zoomToPoint = (lat: number, lon: number, targetZoom: number) => {
    const v = viewRef.current;
    setAutoSpin(false);
    v.targetLat = lat;
    v.targetLon = lon;
    v.targetZoom = targetZoom;
    requestDrawRef.current();
  };

  const focusedShipment =
    focusId !== 0 ? shipments.find((s) => s.id === focusId) : shipments[0];

  return (
    <div
      ref={wrapperRef}
      data-testid="globe-3d-container"
      className="absolute inset-0 z-10 h-full w-full select-none overflow-hidden bg-map-bg"
    >
      <canvas
        ref={canvasRef}
        data-testid="globe-3d-canvas"
        onPointerDown={handlePointerDown}
        onPointerMove={handlePointerMove}
        onPointerUp={handlePointerUp}
        onDoubleClick={handleDoubleClick}
        className="absolute inset-0 block h-full w-full cursor-grab active:cursor-grabbing"
      />

      {/* Bottom-Left 3D Globe Controls (Quick Port Zoom, Auto-Spin, Reset, Deep Zoom +/-, OSM Attribution) */}
      <div className="absolute bottom-3 left-3 z-[450] flex flex-col items-start gap-2">
        <div className="flex flex-col overflow-hidden rounded-md border border-line-strong bg-overlay shadow-md">
          <button
            type="button"
            aria-label="Zoom in 3D globe"
            onClick={() => {
              const v = viewRef.current;
              v.targetZoom = Math.min(2500, v.targetZoom * 1.85);
              requestDrawRef.current();
            }}
            className="flex size-8 items-center justify-center border-b border-line text-ink hover:bg-surface cursor-pointer"
          >
            <Plus className="size-3.5" />
          </button>
          <button
            type="button"
            aria-label="Zoom out 3D globe"
            onClick={() => {
              const v = viewRef.current;
              v.targetZoom = Math.max(0.75, v.targetZoom / 1.85);
              requestDrawRef.current();
            }}
            className="flex size-8 items-center justify-center text-ink hover:bg-surface cursor-pointer"
          >
            <Minus className="size-3.5" />
          </button>
        </div>

        {/* Quick Deep-Zoom Presets (Globe -> Origin Port -> Current/Transit -> Dest Port) */}
        <div className="flex flex-wrap items-center gap-1 rounded-md border border-line-strong bg-overlay p-1 shadow-md">
          {focusedShipment?.origin && (
            <button
              type="button"
              onClick={() =>
                zoomToPoint(
                  focusedShipment.origin!.latitude,
                  focusedShipment.origin!.longitude,
                  180,
                )
              }
              title={`Deep zoom 3D globe to Origin (${focusedShipment.origin.location_name})`}
              className="inline-flex h-7 items-center gap-1 rounded bg-surface px-2 font-mono text-[10px] font-semibold text-ink hover:bg-raised cursor-pointer"
            >
              <Crosshair className="size-3 text-ink-2" />
              <span>{focusedShipment.origin.code || 'Origin'}</span>
            </button>
          )}

          {focusedShipment?.current_position && (
            <button
              type="button"
              onClick={() =>
                zoomToPoint(
                  focusedShipment.current_position!.latitude,
                  focusedShipment.current_position!.longitude,
                  260,
                )
              }
              title={`Deep zoom 3D globe to Current Checkpoint (${focusedShipment.current_position.location_name})`}
              className="inline-flex h-7 items-center gap-1 rounded border border-[#080B0C] bg-[#C6F432] px-2 font-mono text-[10px] font-bold text-[#080B0C] cursor-pointer"
            >
              <Crosshair className="size-3" />
              <span>Port / City</span>
            </button>
          )}

          <button
            type="button"
            onClick={() => setAutoSpin((s) => !s)}
            title={autoSpin ? 'Pause 3D globe rotation' : 'Auto-rotate 3D globe'}
            className={`inline-flex h-7 items-center gap-1 rounded px-2 text-[11px] font-semibold transition-colors cursor-pointer ${
              autoSpin
                ? 'bg-[#C6F432] text-[#080B0C]'
                : 'bg-surface text-ink-2 hover:text-ink'
            }`}
          >
            {autoSpin ? <Pause className="size-3" /> : <Play className="size-3" />}
            <span>{autoSpin ? 'Spinning' : 'Auto-spin'}</span>
          </button>

          <button
            type="button"
            onClick={() => {
              const target = focusedShipment;
              if (target?.origin && target?.destination) {
                const avgLat = (target.origin.latitude + target.destination.latitude) / 2;
                const avgLon = (target.origin.longitude + target.destination.longitude) / 2;
                zoomToPoint(avgLat, avgLon, 1.18);
              } else {
                zoomToPoint(15, 65, 1.08);
              }
            }}
            title="Reset 3D globe to full corridor"
            className="inline-flex h-7 items-center gap-1 rounded bg-surface px-2 text-[11px] font-medium text-ink-2 hover:text-ink cursor-pointer"
          >
            <RotateCcw className="size-3" />
            <span className="hidden sm:inline">Reset</span>
          </button>
        </div>

        <div className="rounded border border-line bg-overlay px-2 py-0.5 font-mono text-[10px] text-ink-2 shadow-sm">
          <Compass className="mr-1 inline size-3" />
          <span ref={hudBadgeRef}>&copy; OpenStreetMap · 3D Globe (108% · OSM z3)</span>
        </div>
      </div>
    </div>
  );
};
