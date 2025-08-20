<script setup lang="ts">
const form = reactive({ title:'', location_name:'', latitude: 44.816, longitude: 20.460, start_at: '', description:'' })
const { $api } = useNuxtApp() as any
async function submit(){ await $api.post('/groups/1/events', form); return navigateTo('/') }
function onMapClick(e:any){
  form.latitude = +e.lngLat.lat.toFixed(7)
  form.longitude = +e.lngLat.lng.toFixed(7)
}
</script>
<template>
  <div class="p-6 grid lg:grid-cols-2 gap-6">
    <div class="space-y-3">
      <input v-model="form.title" class="input" placeholder="Naziv"/>
      <input v-model="form.location_name" class="input" placeholder="Lokacija (naziv)"/>
      <div class="grid grid-cols-2 gap-3">
        <input v-model.number="form.latitude" type="number" step="0.0000001" class="input" placeholder="Latitude"/>
        <input v-model.number="form.longitude" type="number" step="0.0000001" class="input" placeholder="Longitude"/>
      </div>
      <input v-model="form.start_at" type="datetime-local" class="input"/>
      <textarea v-model="form.description" class="textarea textarea-bordered w-full" placeholder="Opis"></textarea>
      <button class="btn" @click="submit">Sačuvaj</button>
    </div>
    <div class="card h-[420px]">
      <div class="card-body p-0">
        <MglMap style="height: 100%; width: 100%" :zoom="7" :center="[form.longitude, form.latitude]" @click="onMapClick">
          <MglMarker :lng-lat="[form.longitude, form.latitude]"/>
          <MglNavigationControl position="top-right"/>
        </MglMap>
      </div>
    </div>
  </div>
</template>
