<script lang="ts" setup>
defineOptions({ name: "SessionsAssignedToMeRedirect" });

await navigateTo("/sessions/assigned", { redirectCode: 301 });
</script>
