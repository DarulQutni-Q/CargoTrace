import React, { useEffect, useRef } from 'react';
import L from 'leaflet';
import { bearingDeg, greatCirclePoints } from '../domain/rules';
import { ShipmentStatus } from '../domain/types';
import { useStore } from '../domain/store';

export interface MapShipmentPoint {
  latitude: number;
  longitude: number;
  location_name: string;
  code?: string;
  time?: string;
}

export interface MapShipmentEntry {
  id: number;
  shipment_number: string;
  status: ShipmentStatus;
  carrier_name?: string;
  service_type?: string;
  delayed?: boolean;
  origin: MapShipmentPoint | null;
  destination: MapShipmentPoint | null;
  current_position: MapShipmentPoint | null;
  previous_position: { latitude: number; longitude: number } | null;
  checkpoints?: MapShipmentPoint[];
}

const DARK_TILES_URL =
  'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}';
const LIGHT_TILES_URL =
  'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}';
const ATTRIBUTION = 'Tiles &copy; Esri &middot; OpenStreetMap contributors';

function pointPopup(shipment: MapShipmentEntry, kind: string, point: MapShipmentPoint): string {
  return `
    <div class="min-w-48 font-sans" style="color:var(--color-ink)">
      <div class="flex items-center justify-between gap-2 pb-1.5" style="border-bottom:1px solid var(--color-line)">
        <span class="font-mono text-xs font-bold">${shipment.shipment_number}</span>
        <span class="font-mono text-[10px] font-semibold" style="color:var(--color-ink-2)">${shipment.status}</span>
      </div>
      <p class="mt-2 text-xs font-semibold">${kind}: ${point.location_name}</p>
      <p class="mt-0.5 font-mono text-[10px]" style="color:var(--color-ink-3)">${point.latitude.toFixed(4)}°, ${point.longitude.toFixed(4)}°</p>
      ${point.time ? `<p class="mt-1 font-mono text-[10px] font-medium" style="color:var(--color-ink-2)">${point.time}</p>` : ''}
    </div>
  `;
}

export const WorldMap: React.FC<{
  shipments: MapShipmentEntry[];
  focusId: number;
  onSelectShipment?: (id: number) => void;
}> = ({ shipments, focusId, onSelectShipment }) => {
  const { theme } = useStore();
  const containerRef = useRef<HTMLDivElement | null>(null);
  const mapRef = useRef<L.Map | null>(null);
  const tileLayerRef = useRef<L.TileLayer | null>(null);
  const layerGroupRef = useRef<L.LayerGroup | null>(null);

  useEffect(() => {
    if (!containerRef.current || mapRef.current) return;

    const map = L.map(containerRef.current, {
      worldCopyJump: true,
      attributionControl: true,
      zoomControl: false,
      minZoom: 2,
      maxZoom: 10,
      maxBounds: [
        [-72, -180],
        [78, 180],
      ],
      maxBoundsViscosity: 1.0,
    }).setView([20, 55], 3);

    L.control.zoom({ position: 'bottomright' }).addTo(map);
    const tileLayer = L.tileLayer(theme === 'dark' ? DARK_TILES_URL : LIGHT_TILES_URL, {
      attribution: ATTRIBUTION,
      maxZoom: 10,
      minZoom: 2,
    }).addTo(map);

    const group = L.layerGroup().addTo(map);
    mapRef.current = map;
    tileLayerRef.current = tileLayer;
    layerGroupRef.current = group;

    return () => {
      map.remove();
      mapRef.current = null;
      tileLayerRef.current = null;
      layerGroupRef.current = null;
    };
  }, []);

  // Dynamically switch tile layer when toggling between Dark Mode (#080B0C) and White Mode (#FFFFFF)
  useEffect(() => {
    if (!tileLayerRef.current) return;
    tileLayerRef.current.setUrl(theme === 'dark' ? DARK_TILES_URL : LIGHT_TILES_URL);
  }, [theme]);

  useEffect(() => {
    const map = mapRef.current;
    const group = layerGroupRef.current;
    if (!map || !group) return;

    group.clearLayers();
    const bounds: [number, number][] = [];
    const renderedPorts = new Set<string>();
    const isDark = theme === 'dark';

    shipments.forEach((shipment) => {
      const isFocused = focusId !== 0 && shipment.id === focusId;
      const isDimmed = focusId !== 0 && shipment.id !== focusId;

      const activeLineColor =
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

      const secondaryLineColor = isDark ? '#525E64' : '#64748B';

      if (shipment.origin && shipment.destination) {
        const originCoord: [number, number] = [
          shipment.origin.latitude,
          shipment.origin.longitude,
        ];
        const destCoord: [number, number] = [
          shipment.destination.latitude,
          shipment.destination.longitude,
        ];

        // Collect all intermediate waypoints that are strictly between Origin and Destination
        // (never re-insert Origin or Destination in the middle of the route, which would cause JKT -> LSW -> JKT -> RTM backtracking)
        const isNearCoord = (a: [number, number], b: [number, number], eps = 0.02) =>
          Math.abs(a[0] - b[0]) < eps && Math.abs(a[1] - b[1]) < eps;

        const rawCandidates: MapShipmentPoint[] = [];
        if (shipment.checkpoints && shipment.checkpoints.length > 0) {
          // checkpoints are passed newest-first; reverse to chronological order (oldest-first)
          rawCandidates.push(...[...shipment.checkpoints].reverse());
        }
        if (shipment.current_position) {
          rawCandidates.push(shipment.current_position);
        }

        const intermediateStops: MapShipmentPoint[] = [];
        rawCandidates.forEach((pt) => {
          const coord: [number, number] = [pt.latitude, pt.longitude];
          if (isNearCoord(coord, originCoord) || isNearCoord(coord, destCoord)) {
            return;
          }
          const alreadyAdded = intermediateStops.some((existing) =>
            isNearCoord([existing.latitude, existing.longitude], coord),
          );
          if (!alreadyAdded) {
            intermediateStops.push(pt);
          }
        });

        // Build chronological waypoint chain: Origin (e.g. JKT) -> Intermediate Transit Stops (e.g. LSW)
        const orderedWaypoints: [number, number][] = [
          originCoord,
          ...intermediateStops.map((pt): [number, number] => [pt.latitude, pt.longitude]),
        ];

        const hasMovedFromOrigin = orderedWaypoints.length >= 2;

        if (shipment.status === ShipmentStatus.Delivered) {
          // Delivered: stitch Origin (JKT) -> all Intermediate Transit Stops (LSW) -> Destination (RTM) as a solid completed trajectory
          orderedWaypoints.push(destCoord);
          const deliveredArc: [number, number][] = [];
          for (let i = 0; i < orderedWaypoints.length - 1; i += 1) {
            const seg = greatCirclePoints(orderedWaypoints[i], orderedWaypoints[i + 1], 28);
            if (i > 0) seg.shift();
            deliveredArc.push(...seg);
          }
          L.polyline(deliveredArc, {
            color: isFocused ? activeLineColor : secondaryLineColor,
            weight: isFocused ? 3 : 1.5,
            opacity: isDimmed ? 0.28 : 0.98,
            lineCap: 'round',
          }).addTo(group);
        } else if (hasMovedFromOrigin) {
          // Active with at least 1 intermediate stop away from Origin (e.g. JKT -> LSW -> RTM):
          // 1) Solid Completed Leg: Origin (JKT) -> ... -> Latest Intermediate Stop (LSW)
          const completedArc: [number, number][] = [];
          for (let i = 0; i < orderedWaypoints.length - 1; i += 1) {
            const seg = greatCirclePoints(orderedWaypoints[i], orderedWaypoints[i + 1], 24);
            if (i > 0) seg.shift();
            completedArc.push(...seg);
          }

          const latestStopCoord = orderedWaypoints[orderedWaypoints.length - 1];
          // 2) Dashed Remaining Leg: Latest Intermediate Stop (LSW) -> Destination (RTM)
          const remainingArc = greatCirclePoints(latestStopCoord, destCoord, 36);

          if (completedArc.length >= 2) {
            L.polyline(completedArc, {
              color: isFocused ? activeLineColor : secondaryLineColor,
              weight: isFocused ? 3.2 : 1.5,
              opacity: isDimmed ? 0.28 : 0.98,
              lineCap: 'round',
            }).addTo(group);
          }

          L.polyline(remainingArc, {
            color: isFocused ? (isDark ? '#B0BEC4' : '#3D4850') : secondaryLineColor,
            weight: isFocused ? 2.1 : 1.25,
            dashArray: '5 6',
            opacity: isDimmed ? 0.25 : 0.9,
          }).addTo(group);
        } else {
          // No intermediate checkpoints away from Origin yet: dashed planned corridor Origin -> Destination
          const fullArc = greatCirclePoints(originCoord, destCoord, 48);
          L.polyline(fullArc, {
            color: isFocused ? activeLineColor : secondaryLineColor,
            weight: isFocused ? 2.8 : 1.3,
            dashArray: '4 6',
            opacity: isDimmed ? 0.25 : isFocused ? 0.96 : 0.5,
          }).addTo(group);
        }

        // Intermediate Transit Checkpoints along the trajectory (distinct from Origin and Destination)
        if (isFocused && intermediateStops.length > 0) {
          intermediateStops.forEach((cp, idx) => {
            L.circleMarker([cp.latitude, cp.longitude], {
              radius: 5.5,
              color: '#080B0C',
              weight: 2,
              fillColor: '#C6F432',
              fillOpacity: 1,
            })
              .addTo(group)
              .bindPopup(pointPopup(shipment, `Transit Stop #${idx + 1}`, cp))
              .bindTooltip(
                `Transit #${idx + 1}: ${cp.location_name}${cp.time ? ` · ${cp.time}` : ''}`,
                {
                  permanent: isFocused,
                  direction: 'top',
                  offset: [0, -6],
                  className: 'ct-tooltip',
                },
              );
          });
        }

        // Origin Port Ring + Focused Code Label (e.g. "KRG")
        const originKey = `${originCoord[0].toFixed(3)},${originCoord[1].toFixed(3)}`;
        if (!renderedPorts.has(originKey) || isFocused) {
          renderedPorts.add(originKey);
          const originMarker = L.circleMarker(originCoord, {
            radius: isFocused ? 6 : 4,
            color: isDark ? '#FFFFFF' : '#080B0C',
            weight: 2.2,
            fillColor: isDark ? '#080B0C' : '#FFFFFF',
            fillOpacity: 1,
            opacity: isDimmed ? 0.4 : 0.98,
          })
            .addTo(group)
            .bindPopup(pointPopup(shipment, 'Origin port', shipment.origin))
            .bindTooltip(
              isFocused && shipment.origin.code
                ? `Origin: ${shipment.origin.code}`
                : shipment.origin.location_name,
              {
                permanent: isFocused,
                direction: 'left',
                offset: [-8, 0],
                className: 'ct-tooltip',
              },
            );
          void originMarker;
        }

        // Destination Port Ring + Focused Code Label (e.g. "SUB")
        const destKey = `dest:${destCoord[0].toFixed(3)},${destCoord[1].toFixed(3)}`;
        if (!renderedPorts.has(destKey) || isFocused) {
          renderedPorts.add(destKey);
          const destMarker = L.circleMarker(destCoord, {
            radius: isFocused ? 6.2 : 4.2,
            color: isFocused ? activeLineColor : isDark ? '#B0BEC4' : '#1A2125',
            weight: 2.2,
            fillColor: isFocused ? activeLineColor : isDark ? '#172024' : '#FFFFFF',
            fillOpacity: 0.98,
            opacity: isDimmed ? 0.4 : 0.98,
          })
            .addTo(group)
            .bindPopup(pointPopup(shipment, 'Destination port', shipment.destination))
            .bindTooltip(
              isFocused && shipment.destination.code
                ? `Dest: ${shipment.destination.code}`
                : shipment.destination.location_name,
              {
                permanent: isFocused,
                direction: 'right',
                offset: [8, 0],
                className: 'ct-tooltip',
              },
            );
          void destMarker;
        }

        bounds.push(originCoord);
        bounds.push(destCoord);
      }

      if (shipment.current_position) {
        const position: [number, number] = [
          shipment.current_position.latitude,
          shipment.current_position.longitude,
        ];

        // Calculate bearing toward Destination when in transit so arrow points toward its target port
        let heading = 45;
        if (
          shipment.destination &&
          shipment.status !== ShipmentStatus.Delivered &&
          (Math.abs(position[0] - shipment.destination.latitude) > 0.02 ||
            Math.abs(position[1] - shipment.destination.longitude) > 0.02)
        ) {
          heading = bearingDeg(position, [
            shipment.destination.latitude,
            shipment.destination.longitude,
          ]);
        } else if (shipment.previous_position) {
          heading = bearingDeg(
            [shipment.previous_position.latitude, shipment.previous_position.longitude],
            position,
          );
        }

        const badgeBg = isFocused ? '#C6F432' : '#080B0C';
        const badgeBorder = isFocused
          ? '#080B0C'
          : shipment.delayed
          ? '#F59E0B'
          : '#C6F432';
        const arrowFill = isFocused
          ? '#080B0C'
          : shipment.delayed
          ? '#F59E0B'
          : '#C6F432';

        const icon = L.divIcon({
          className: '',
          html: `
            <div class="relative flex items-center justify-center ${
              isDimmed ? 'opacity-40' : 'opacity-100'
            }">
              <div class="rounded-full p-1 shadow-md" style="background:${badgeBg};border:1.5px solid ${badgeBorder};transform:rotate(${heading}deg)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="${arrowFill}">
                  <path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z"/>
                </svg>
              </div>
              ${
                isFocused
                  ? `<span class="ct-port-label absolute left-6 -top-1 whitespace-nowrap">${shipment.shipment_number}</span>`
                  : ''
              }
            </div>
          `,
          iconSize: [24, 24],
          iconAnchor: [12, 12],
        });

        const marker = L.marker(position, {
          icon,
          zIndexOffset: isFocused ? 1000 : 500,
        })
          .addTo(group)
          .bindPopup(pointPopup(shipment, 'Current position', shipment.current_position))
          .bindTooltip(`${shipment.shipment_number} · ${shipment.current_position.location_name}`, {
            direction: 'top',
            className: 'ct-tooltip',
          });

        if (onSelectShipment) {
          marker.on('click', () => onSelectShipment(shipment.id));
        }

        bounds.push(position);
      }
    });

    if (focusId !== 0 && bounds.length > 0) {
      const focused = shipments.find((s) => s.id === focusId);
      const focusBounds: [number, number][] = [];
      if (focused?.origin) {
        focusBounds.push([focused.origin.latitude, focused.origin.longitude]);
      }
      if (focused?.checkpoints) {
        focused.checkpoints.forEach((cp) => {
          focusBounds.push([cp.latitude, cp.longitude]);
        });
      }
      if (focused?.destination) {
        focusBounds.push([focused.destination.latitude, focused.destination.longitude]);
      }
      if (focused?.current_position) {
        focusBounds.push([
          focused.current_position.latitude,
          focused.current_position.longitude,
        ]);
      }
      map.fitBounds(focusBounds.length > 0 ? focusBounds : bounds, {
        padding: [70, 70],
        maxZoom: 5,
      });
    } else if (bounds.length > 0) {
      map.fitBounds(bounds, { padding: [55, 55], maxZoom: 4 });
    }
  }, [shipments, focusId, onSelectShipment, theme]);

  return (
    <div
      id="world-map"
      data-testid="world-map"
      ref={containerRef}
      className="isolate relative z-0 h-full w-full bg-map-bg"
    />
  );
};
