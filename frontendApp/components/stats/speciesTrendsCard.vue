<script setup lang="ts">
import {
  BarController,
  BarElement,
  CategoryScale,
  Chart,
  Legend,
  LinearScale,
  Tooltip,
} from "chart.js";

const props = withDefaults(defineProps<Props>(), {
  title: "Trendovi po vrstama",
  year: new Date().getFullYear(),
  limit: 5,
});

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

type Props = { title?: string; groupId?: number; year?: number; limit?: number };
const { items, loading, fetchTop } = useSpeciesTrends();
const canvasRef = ref<HTMLCanvasElement | null>(null);
let chart: Chart | null = null;

const labels = computed(() => items.value.map(r => r.label));
const counts = computed(() => items.value.map(r => r.cnt));
const weights = computed(() => items.value.map(r => r.total_kg));

function render() {
  if (!canvasRef.value)
    return;
  chart?.destroy();
  chart = new Chart(canvasRef.value, {
    type: "bar",
    data: {
      labels: labels.value,
      datasets: [
        { label: "# ulova", data: counts.value },
        { label: "Ukupno (kg)", data: weights.value },
      ],
    },
    options: {
      responsive: true,
      plugins: {
        tooltip: {
          callbacks: {
            label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y}`,
          },
        },
      },
      scales: { y: { beginAtZero: true } },
    },
  });
}

onMounted(async () => {
  await fetchTop(props.groupId, props.year, props.limit);
  if (items.value.length)
    render();
});
watch(items, () => render());
watch(
  () => [props.groupId, props.year, props.limit],
  async () => {
    await fetchTop(props.groupId, props.year, props.limit);
  },
);
useSWR(() => fetchTop(props.groupId, props.year, props.limit), {
  intervalMs: 90000,
  enabled: true,
});
</script>

<template>
  <div class="card bg-base-100 shadow-lg">
    <div class="card-body">
      <h2 class="card-title">
        {{ title }}
      </h2>

      <div v-if="loading" class="h-56 w-full animate-pulse bg-base-300 rounded" />
      <div v-else-if="!items.length" class="text-sm opacity-70">
        Nema podataka za izabranu godinu.
      </div>
      <canvas
        v-else
        ref="canvasRef"
        class="w-full h-56"
      />
    </div>
  </div>
</template>
