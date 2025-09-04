<script setup lang="ts">
type Props = { title?: string; groupId?: number; year?: number; limit?: number };
const props = withDefaults(defineProps<Props>(), {
  title: "Trendovi po vrstama",
  year: new Date().getFullYear(),
  limit: 5,
});

const { items, loading, fetchTop } = useSpeciesTrends();

const canvasRef = ref<HTMLCanvasElement | null>(null);
let chart: any = null; // Chart.js instance
let ChartJS: any = null; // Chart ctor (lazy load)

async function ensureChartLib() {
  if (ChartJS)
    return;
  // Client-only dynamic import (SSR-safe) i auto registracija svih elemenata
  const mod = await import("chart.js/auto");
  ChartJS = mod.Chart || mod.default;
}

const labels = computed(() => items.value.map(r => r.label));
const counts = computed(() => items.value.map(r => r.cnt));
const weights = computed(() => items.value.map(r => r.total_kg));

function buildData() {
  return {
    labels: labels.value,
    datasets: [
      { label: "# ulova", data: counts.value },
      { label: "Ukupno (kg)", data: weights.value },
    ],
  };
}

async function draw() {
  if (!canvasRef.value)
    return;
  await nextTick();
  await new Promise(r => requestAnimationFrame(r));
  await ensureChartLib();

  const ctx = canvasRef.value.getContext("2d");
  if (!ctx)
    return;

  const data = buildData();

  if (!chart) {
    chart = new ChartJS(ctx, {
      type: "bar",
      data,
      options: {
        responsive: true,
        maintainAspectRatio: false, // ključ da radi uz fiksnu visinu kontejnera
        animation: false,
        scales: { y: { beginAtZero: true } },
        plugins: {
          legend: { display: true, position: "bottom" },
          tooltip: {
            callbacks: {
              label: (ctx: any) => `${ctx.dataset.label}: ${ctx.parsed.y}`,
            },
          },
        },
      },
    });
  }
  else {
    chart.data = data;
    chart.update("none"); // bez animacije
  }
}

onMounted(async () => {
  await fetchTop(props.groupId, props.year, props.limit);
  if (items.value.length)
    await draw();
});

watch(items, async (arr) => {
  if (!canvasRef.value) {
    return;
  }
  if (arr.length) {
    await draw();
  }
  else {
    chart?.destroy();
    chart = null;
  }
});

watch(
  () => [props.groupId, props.year, props.limit] as const,
  async () => {
    await fetchTop(props.groupId, props.year, props.limit);
  },
);

onBeforeUnmount(() => {
  chart?.destroy();
  chart = null;
});

// SWR refetch (klijent, on-focus i interval već rešava tvoj useSWR)
useSWR(() => fetchTop(props.groupId, props.year, props.limit), {
  intervalMs: 90_000,
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

      <!-- ClientOnly + kontejner sa fiksnom visinom i absolute canvas fill -->
      <ClientOnly v-else>
        <div class="relative w-full h-56">
          <canvas ref="canvasRef" class="absolute inset-0 w-full h-full" />
        </div>
      </ClientOnly>
    </div>
  </div>
</template>
