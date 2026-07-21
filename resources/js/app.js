import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

document.addEventListener('DOMContentLoaded', function() {
  // Force reload on back/forward navigation to prevent bfcache issues
  window.addEventListener('pageshow', function(event) {
    if (event.persisted || performance.getEntriesByType("navigation")[0].type === "back_forward") {
      window.location.reload();
    }
  });
});
