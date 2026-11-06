import React, { useEffect, useRef, useState } from 'react';
import { Compass, Pause, Play, Plus, Minus, RotateCcw } from 'lucide-react';
import { greatCirclePoints } from '../domain/rules';
import { ShipmentStatus } from '../domain/types';
import { MapShipmentEntry, MapShipmentPoint } from './WorldMap';

/**
 * Simplified high-recognition world continental & archipelago polygons [lat, lon][]
 * including detailed Southeast Asia (Sumatra/Aceh, Java, Kalimantan, Sulawesi, Papua, Singapore),
 * Eurasia, Europe (Rotterdam/Hamburg), Arabia/Suez, Africa, Australia, and Americas.
 */
const WORLD_LAND_POLYGONS: [number, number][][] = [
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

const DEG2RAD = Math.PI / 180;

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

export const Globe3DCanvas: React.FC<{
  shipments: MapShipmentEntry[];
  focusId: number;
  theme: 'light' | 'dark';
  onSelectShipment?: (id: number) => void;
}> = ({ shipments, focusId, theme, onSelectShipment }) => {
  const wrapperRef = useRef<HTMLDivElement | null>(null);
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const [autoSpin, setAutoSpin] = useState<boolean>(false);
  const [zoomScale, setZoomScale] = useState<number>(1.08);
  const [renderTick, setRenderTick] = useState<number>(0);

  // Mutable camera rotation ref for 60fps drag & smooth damping without re-creating listeners
  const viewRef = useRef({
    rotLat: 14,
    rotLon: 72,
    targetLat: 14,
    targetLon: 72,
    zoom: 1.08,
    targetZoom: 1.08,
    dragging: false,
    dragStartX: 0,
    dragStartY: 0,
    startLat: 14,
    startLon: 72,
    movedDistance: 0,
    dashPhase: 0,
  });

  // Hit targets populated on each draw for click-to-select shipment
  const hitTargetsRef = useRef<
    { id: number; x: number; y: number; r: number; label: string }[]
  >([]);

  // Center globe on focused shipment corridor (or active fleet centroid) when focusId/shipments change
  useEffect(() => {
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
        const clampedLat = Math.max(-50, Math.min(55, avgLat));
        viewRef.current.targetLat = clampedLat;
        viewRef.current.targetLon = avgLon;
        viewRef.current.rotLat = clampedLat;
        viewRef.current.rotLon = avgLon;
        const nextZoom = focusId !== 0 ? 1.18 : 1.08;
        viewRef.current.targetZoom = nextZoom;
        viewRef.current.zoom = nextZoom;
        setZoomScale(nextZoom);
      }
    } else {
      viewRef.current.targetLat = 14;
      viewRef.current.targetLon = 72;
      viewRef.current.rotLat = 14;
      viewRef.current.rotLon = 72;
      viewRef.current.targetZoom = 1.08;
      viewRef.current.zoom = 1.08;
      setZoomScale(1.08);
    }
    setRenderTick((t) => t + 1);
  }, [focusId, shipments]);

  useEffect(() => {
    const wrapper = wrapperRef.current;
    const canvas = canvasRef.current;
    if (!wrapper || !canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    let animId = 0;

    const renderFrame = () => {
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

      ctx.save();
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

      const v = viewRef.current;
      let needsMoreFrames = false;

      if (!v.dragging) {
        if (autoSpin) {
          v.targetLon = ((v.targetLon + 0.22 + 180) % 360) - 180;
          v.dashPhase = (v.dashPhase + 0.35) % 24;
          needsMoreFrames = true;
        }
        const dLat = v.targetLat - v.rotLat;
        let dLon = v.targetLon - v.rotLon;
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

      const dZoom = v.targetZoom - v.zoom;
      if (Math.abs(dZoom) > 0.003) {
        v.zoom += dZoom * 0.2;
        needsMoreFrames = true;
      } else {
        v.zoom = v.targetZoom;
      }

      const isDark = theme === 'dark';
      const cx = width / 2;
      const cy = height / 2;
      const baseRadius = Math.min(width, height) * 0.38 * v.zoom;

      // 1. Background Canvas Fill (strictly matching #080B0C / #FFFFFF / #C6F432 system)
      ctx.fillStyle = isDark ? '#060809' : '#E6ECEF';
      ctx.fillRect(0, 0, width, height);

      // Subtle radial background halo behind the 3D globe
      const haloGrad = ctx.createRadialGradient(
        cx,
        cy,
        baseRadius * 0.75,
        cx,
        cy,
        baseRadius * 1.25,
      );
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

      // 2. 3D Ocean Sphere with Spherical Specular Lighting
      const sphereGrad = ctx.createRadialGradient(
        cx - baseRadius * 0.3,
        cy - baseRadius * 0.3,
        baseRadius * 0.08,
        cx,
        cy,
        baseRadius,
      );
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

      // Clip to Globe Sphere for Graticules & Continents
      ctx.save();
      ctx.beginPath();
      ctx.arc(cx, cy, baseRadius, 0, Math.PI * 2);
      ctx.clip();

      // 3. Spherical Graticule Grid (Meridians & Parallels every 15°)
      ctx.strokeStyle = isDark ? 'rgba(255, 255, 255, 0.07)' : 'rgba(8, 11, 12, 0.08)';
      ctx.lineWidth = 0.8;

      for (let lon = -180; lon < 180; lon += 15) {
        ctx.beginPath();
        let started = false;
        for (let lat = -75; lat <= 75; lat += 5) {
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

      for (let lat = -60; lat <= 60; lat += 15) {
        ctx.beginPath();
        let started = false;
        for (let lon = -180; lon <= 180; lon += 5) {
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
        ctx.lineWidth = lat === 0 ? 1.2 : 0.8;
        ctx.strokeStyle =
          lat === 0
            ? isDark
              ? 'rgba(198, 244, 50, 0.22)'
              : 'rgba(8, 11, 12, 0.16)'
            : isDark
            ? 'rgba(255, 255, 255, 0.07)'
            : 'rgba(8, 11, 12, 0.08)';
        ctx.stroke();
      }

      // 4. World Continental & Indonesian Archipelago Polygons
      WORLD_LAND_POLYGONS.forEach((poly) => {
        ctx.beginPath();
        let visibleCount = 0;
        poly.forEach(([lat, lon], idx) => {
          const p = project3D(lat, lon, v.rotLat, v.rotLon, cx, cy, baseRadius);
          if (p.z > -0.15) visibleCount += 1;
          if (idx === 0) ctx.moveTo(p.x, p.y);
          else ctx.lineTo(p.x, p.y);
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

      ctx.restore(); // End sphere clip so elevated 3D arcs & labels can rise above the rim

      // Globe Rim Ring Border
      ctx.beginPath();
      ctx.arc(cx, cy, baseRadius, 0, Math.PI * 2);
      ctx.strokeStyle = isDark ? 'rgba(198, 244, 50, 0.45)' : '#080B0C';
      ctx.lineWidth = isDark ? 1.5 : 1.75;
      ctx.stroke();

      // 5. Render Multi-Leg 3D Corridor Trajectories (Origin ━━▶ Transit Stops ━━▶ Destination)
      const newHitTargets: { id: number; x: number; y: number; r: number; label: string }[] = [];

      const isNearCoord = (a: [number, number], b: [number, number], eps = 0.02) =>
        Math.abs(a[0] - b[0]) < eps && Math.abs(a[1] - b[1]) < eps;

      // Draw non-focused shipments first, then focused shipment on top
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
          const steps = 36;
          const pts = greatCirclePoints(start, end, steps);

          // 5a. Surface shadow line on globe (altitude = 1.0)
          if (isFocused) {
            ctx.save();
            ctx.beginPath();
            let startedShadow = false;
            pts.forEach(([lat, lon]) => {
              const p = project3D(lat, lon, v.rotLat, v.rotLon, cx, cy, baseRadius, 1.0);
              if (p.z > 0) {
                if (!startedShadow) {
                  ctx.moveTo(p.x, p.y);
                  startedShadow = true;
                } else {
                  ctx.lineTo(p.x, p.y);
                }
              } else {
                startedShadow = false;
              }
            });
            ctx.strokeStyle = isDark ? 'rgba(0,0,0,0.45)' : 'rgba(8,11,12,0.14)';
            ctx.lineWidth = lineWidth;
            ctx.stroke();
            ctx.restore();
          }

          // 5b. Elevated 3D Great-Circle Arc (rises up to +6.5% radius at midpoint)
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
          let started = false;
          pts.forEach(([lat, lon], idx) => {
            const t = idx / steps;
            const archBoost = isFocused ? Math.sin(t * Math.PI) * 0.055 : Math.sin(t * Math.PI) * 0.025;
            const p = project3D(
              lat,
              lon,
              v.rotLat,
              v.rotLon,
              cx,
              cy,
              baseRadius,
              1.0 + archBoost,
            );
            if (p.z > -0.02) {
              if (!started) {
                ctx.moveTo(p.x, p.y);
                started = true;
              } else {
                ctx.lineTo(p.x, p.y);
              }
            } else {
              started = false;
            }
          });
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
              isFocused ? primaryColor : isDark ? '#525E64' : '#64748B',
              isFocused ? 3.0 : 1.5,
              isDimmed ? 0.25 : 0.95,
            );
          }
        } else if (hasMovedFromOrigin) {
          for (let i = 0; i < orderedWaypoints.length - 1; i += 1) {
            drawElevatedArcSegment(
              orderedWaypoints[i],
              orderedWaypoints[i + 1],
              false,
              isFocused ? primaryColor : isDark ? '#525E64' : '#64748B',
              isFocused ? 3.2 : 1.6,
              isDimmed ? 0.28 : 0.98,
            );
          }
          const latestStop = orderedWaypoints[orderedWaypoints.length - 1];
          drawElevatedArcSegment(
            latestStop,
            destCoord,
            true,
            isFocused ? (isDark ? '#D0D8DC' : '#3D4850') : isDark ? '#525E64' : '#64748B',
            isFocused ? 2.2 : 1.3,
            isDimmed ? 0.22 : 0.88,
          );
        } else {
          drawElevatedArcSegment(
            originCoord,
            destCoord,
            true,
            isFocused ? primaryColor : isDark ? '#525E64' : '#64748B',
            isFocused ? 2.6 : 1.4,
            isDimmed ? 0.25 : isFocused ? 0.92 : 0.55,
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

          ctx.save();
          ctx.globalAlpha = isDimmed ? 0.35 : 1;
          ctx.beginPath();
          ctx.arc(p.x, p.y, radius, 0, Math.PI * 2);
          ctx.fillStyle = fill;
          ctx.fill();
          ctx.lineWidth = 2;
          ctx.strokeStyle = stroke;
          ctx.stroke();

          if (badgeText && isFocused) {
            ctx.font = '700 10px "JetBrains Mono", monospace';
            const textW = ctx.measureText(badgeText).width;
            const padX = 6;
            const boxW = textW + padX * 2;
            const boxH = 18;
            const boxX = p.x - boxW / 2;
            const boxY = p.y - radius - boxH - 5;

            ctx.fillStyle =
              badgeTone === 'volt' ? '#C6F432' : isDark ? '#101518' : '#FFFFFF';
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
            1.02,
          );
          if (curP.z > 0) {
            ctx.save();
            ctx.globalAlpha = isDimmed ? 0.4 : 1;
            ctx.beginPath();
            ctx.arc(curP.x, curP.y, isFocused ? 8 : 6, 0, Math.PI * 2);
            ctx.fillStyle = isFocused ? '#C6F432' : '#080B0C';
            ctx.fill();
            ctx.lineWidth = 2;
            ctx.strokeStyle = isFocused
              ? '#080B0C'
              : shipment.delayed
              ? '#F59E0B'
              : '#C6F432';
            ctx.stroke();

            // Inner core dot
            ctx.beginPath();
            ctx.arc(curP.x, curP.y, 2.5, 0, Math.PI * 2);
            ctx.fillStyle = isFocused ? '#080B0C' : '#C6F432';
            ctx.fill();
            ctx.restore();

            newHitTargets.push({
              id: shipment.id,
              x: curP.x,
              y: curP.y,
              r: 14,
              label: shipment.shipment_number,
            });
          }
        }
      });

      hitTargetsRef.current = newHitTargets;
      ctx.restore();

      if (needsMoreFrames) {
        animId = requestAnimationFrame(renderFrame);
      }
    };

    renderFrame();

    const onResize = () => renderFrame();
    window.addEventListener('resize', onResize);
    return () => {
      cancelAnimationFrame(animId);
      window.removeEventListener('resize', onResize);
    };
  }, [shipments, focusId, theme, autoSpin, zoomScale, renderTick]);

  // Pointer drag + wheel zoom + click hit-testing
  const handlePointerDown = (e: React.PointerEvent<HTMLCanvasElement>) => {
    const v = viewRef.current;
    v.dragging = true;
    v.dragStartX = e.clientX;
    v.dragStartY = e.clientY;
    v.startLat = v.rotLat;
    v.startLon = v.rotLon;
    v.movedDistance = 0;
    e.currentTarget.setPointerCapture(e.pointerId);
    setRenderTick((t) => t + 1);
  };

  const handlePointerMove = (e: React.PointerEvent<HTMLCanvasElement>) => {
    const v = viewRef.current;
    if (!v.dragging) return;
    const dx = e.clientX - v.dragStartX;
    const dy = e.clientY - v.dragStartY;
    v.movedDistance = Math.hypot(dx, dy);

    const sensitivity = 0.28 / v.zoom;
    v.rotLon = ((v.startLon - dx * sensitivity + 540) % 360) - 180;
    v.rotLat = Math.max(-70, Math.min(70, v.startLat + dy * sensitivity));
    v.targetLon = v.rotLon;
    v.targetLat = v.rotLat;
    setRenderTick((t) => t + 1);
  };

  const handlePointerUp = (e: React.PointerEvent<HTMLCanvasElement>) => {
    const v = viewRef.current;
    if (!v.dragging) return;
    v.dragging = false;
    e.currentTarget.releasePointerCapture(e.pointerId);

    // If user clicked without dragging, check if they clicked a shipment marker
    if (v.movedDistance < 6 && onSelectShipment && canvasRef.current) {
      const rect = canvasRef.current.getBoundingClientRect();
      const clickX = e.clientX - rect.left;
      const clickY = e.clientY - rect.top;
      const hit = hitTargetsRef.current.find(
        (t) => Math.hypot(t.x - clickX, t.y - clickY) <= t.r,
      );
      if (hit) {
        onSelectShipment(hit.id);
      }
    }
    setRenderTick((t) => t + 1);
  };

  const handleWheel = (e: React.WheelEvent<HTMLCanvasElement>) => {
    e.preventDefault();
    const v = viewRef.current;
    const nextZoom = Math.max(0.75, Math.min(2.2, v.targetZoom - e.deltaY * 0.0012));
    v.targetZoom = nextZoom;
    v.zoom = nextZoom;
    setZoomScale(Number(nextZoom.toFixed(2)));
  };

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
        onWheel={handleWheel}
        className="absolute inset-0 block h-full w-full cursor-grab active:cursor-grabbing"
      />

      {/* Bottom-Right 3D Globe Controls (Auto-Spin, Reset Corridor, Zoom In/Out) */}
      <div className="absolute bottom-4 right-3 z-[450] flex flex-col items-end gap-2 sm:right-4">
        <div className="flex items-center gap-1.5 rounded-md border border-line-strong bg-overlay p-1 shadow-md">
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
              viewRef.current.targetLat = 14;
              viewRef.current.targetLon = 72;
              viewRef.current.rotLat = 14;
              viewRef.current.rotLon = 72;
              viewRef.current.targetZoom = 1.08;
              viewRef.current.zoom = 1.08;
              setZoomScale(1.08);
              setRenderTick((t) => t + 1);
            }}
            title="Reset 3D globe orientation"
            className="inline-flex h-7 items-center gap-1 rounded bg-surface px-2 text-[11px] font-medium text-ink-2 hover:text-ink cursor-pointer"
          >
            <RotateCcw className="size-3" />
            <span className="hidden sm:inline">Reset</span>
          </button>
        </div>

        <div className="flex flex-col overflow-hidden rounded-md border border-line-strong bg-overlay shadow-md">
          <button
            type="button"
            aria-label="Zoom in 3D globe"
            onClick={() => {
              const next = Math.min(2.2, viewRef.current.targetZoom + 0.2);
              viewRef.current.targetZoom = next;
              viewRef.current.zoom = next;
              setZoomScale(Number(next.toFixed(2)));
            }}
            className="flex size-8 items-center justify-center border-b border-line text-ink hover:bg-surface cursor-pointer"
          >
            <Plus className="size-3.5" />
          </button>
          <button
            type="button"
            aria-label="Zoom out 3D globe"
            onClick={() => {
              const next = Math.max(0.75, viewRef.current.targetZoom - 0.2);
              viewRef.current.targetZoom = next;
              viewRef.current.zoom = next;
              setZoomScale(Number(next.toFixed(2)));
            }}
            className="flex size-8 items-center justify-center text-ink hover:bg-surface cursor-pointer"
          >
            <Minus className="size-3.5" />
          </button>
        </div>

        <div className="rounded border border-line bg-overlay px-2 py-0.5 font-mono text-[10px] text-ink-3">
          <Compass className="mr-1 inline size-3" />
          3D Orthographic Globe · {Math.round(zoomScale * 100)}% · Drag to rotate
        </div>
      </div>
    </div>
  );
};
