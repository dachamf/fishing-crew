export default defineNuxtPlugin(() => {
  window.addEventListener("focus", () => {
    // emit global event pa ga slušaj u karticama
    window.dispatchEvent(new CustomEvent("app:refocus"));
  });
});
