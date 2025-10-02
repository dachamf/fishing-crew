<script setup lang="ts">
import type { FishingSessionLite } from "~/types/api";

const props = withDefaults(
  defineProps<{
    sessions?: FishingSessionLite[];
    limit?: number;
    styleUrl?: string;
    height?: number;
  }>(),
  {
    sessions: () => [],
    limit: 10,
    styleUrl: "https://tiles.openfreemap.org/styles/liberty",
    height: 260,
  },
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
</script>

<template>
  <MapCanvas
    :style-url="styleUrl"
    :height="height"
    :bounds="bounds"
    :points="pointMarkers"
  />
</template>
