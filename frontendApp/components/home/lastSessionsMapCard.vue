<script setup lang="ts">
import type { FishingSessionLite } from "~/types/api";

const props = withDefaults(
  defineProps<{
    sessions?: FishingSessionLite[];
    limit?: number;
    height?: number;
  }>(),
  {
    sessions: () => [],
    limit: 10,
    height: 260,
  },
);

// Dark / light stil za mapu
const { resolved } = useTheme();
const styleUrl = computed(() =>
  resolved.value === "dark" ? "/styles/dark.json" : "https://tiles.openfreemap.org/styles/liberty",
);

const points = computed(() => props.sessions!.slice(0, props.limit));

type BoundsTuple = [[number, number], [number, number]];
const bounds = computed<BoundsTuple | null>(() => {
  if (!points.value.length)
    return null;

  let minLng = Infinity;
  let minLat = Infinity;
  let maxLng = -Infinity;
  let maxLat = -Infinity;

  for (const s of points.value) {
    const lng = s.longitude ?? null;
    const lat = s.latitude ?? null;
    if (lng == null || lat == null)
      continue;
    minLng = Math.min(minLng, lng);
    minLat = Math.min(minLat, lat);
    maxLng = Math.max(maxLng, lng);
    maxLat = Math.max(maxLat, lat);
  }
  if (!Number.isFinite(minLng))
    return null;

  return [
    [minLng, minLat],
    [maxLng, maxLat],
  ];
});

const pointMarkers = computed(() =>
  (props.sessions ?? [])
    .slice(0, props.limit)
    .filter(s => s.longitude != null && s.latitude != null)
    .map(s => ({
      id: s.id,
      coordinates: [s.longitude as number, s.latitude as number] as [number, number],
    })),
);

const belgradeCenter = [20.4489, 44.7866] as [number, number];
const center = computed<[number, number] | null>(() =>
  pointMarkers.value.length ? null : belgradeCenter,
);
const zoom = computed<number>(() => (pointMarkers.value.length ? 9 : 10));
</script>

<template>
  <MapCanvas
    :style-url="styleUrl"
    :height="height"
    :bounds="bounds"
    :center="center"
    :zoom="zoom"
    :points="pointMarkers"
    :fit-bounds-max-zoom="8"
  />
</template>
