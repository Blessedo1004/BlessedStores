  // panel sidebar navigation
  function sideBarNavigation (){
    const sidebar = document.getElementById('dashboardSidebar');
    const openBtn = document.getElementById('openSidebarBtn');
    const closeBtn = document.getElementById('closeSidebarBtn');

    if (sidebar && openBtn && closeBtn) {
        openBtn.addEventListener('click', function() {
            sidebar.classList.add('show');
        });

        closeBtn.addEventListener('click', function() {
            sidebar.classList.remove('show');
        });
    }
  } 

  // Force reload on back/forward navigation to prevent bfcache issues
    window.addEventListener('pageshow', function(event) {
      if (event.persisted || performance.getEntriesByType("navigation")[0].type === "back_forward") {
        window.location.reload();
      }
    });

  document.addEventListener('livewire:navigated', function() {
    sideBarNavigation();
  })
 

document.addEventListener('DOMContentLoaded', function() {
  sideBarNavigation();
});

//Resend code countdown
window.resendCooldown = function (initialSeconds) {
  return {
    remaining: Number(initialSeconds) || 0,
    deadline: null,
    timer: null,
    sending: false,

    init() {
      this.restart(this.remaining);
    },

    restart(seconds) {
      this.stop();
      this.remaining = Math.max(0, Number(seconds) || 0);

      if (this.remaining === 0) return;

      this.deadline = Date.now() + (this.remaining * 1000);
      this.timer = setInterval(() => this.tick(), 250);
    },

    tick() {
      this.remaining = Math.max(0, Math.ceil((this.deadline - Date.now()) / 1000));

      if (this.remaining === 0) this.stop();
    },

    send() {
      if (this.remaining > 0 || this.sending) return;

      this.sending = true;
      const componentId = this.$root.closest('[wire\\:id]').getAttribute('wire:id');

      Promise.resolve(window.Livewire.find(componentId).resendCode())
        .finally(() => this.sending = false);
    },

    stop() {
      if (this.timer) clearInterval(this.timer);
      this.timer = null;
    },

    destroy() {
      this.stop();
    },
  };

   
};
