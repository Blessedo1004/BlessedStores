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
    initializeProductGallery();
  })
 

document.addEventListener('DOMContentLoaded', function() {
  sideBarNavigation();
  initializeProductGallery();

  const productGalleryObserver = new MutationObserver(function() {
    initializeProductGallery();
  });

  productGalleryObserver.observe(document.body, {
    childList: true,
    subtree: true,
  });
});

function initializeProductGallery() {
  const gallery = document.querySelector('[data-product-gallery]');

  if (!gallery || !window.jQuery) return;

  if (!window.jQuery.fn.slick) return;

  const main = window.jQuery(gallery).find('[data-product-gallery-main]');
  const previous = window.jQuery(gallery).find('[data-product-gallery-prev]');
  const next = window.jQuery(gallery).find('[data-product-gallery-next]');
  const dots = window.jQuery(gallery).find('[data-product-gallery-dots]');
  const dotButtons = dots.find('[data-product-gallery-dot]');

  if (!main.length || main.hasClass('slick-initialized')) return;

  main.slick({
    arrows: true,
    dots: false,
    adaptiveHeight: true,
    prevArrow: previous[0],
    nextArrow: next[0],
  });

    const updateActiveDot = function(event, slick, currentSlide) {
      dotButtons.removeClass('is-active').eq(currentSlide).addClass('is-active');
    };

    main.on('afterChange', updateActiveDot);
    dotButtons.on('click', function() {
      main.slick('slickGoTo', Number(window.jQuery(this).data('product-gallery-dot')));
    });
    updateActiveDot(null, null, 0);
}

function registerProductGalleryListeners() {
  if (!window.Livewire) return;

  const initializeAfterMorph = function() {
    requestAnimationFrame(function() {
      requestAnimationFrame(initializeProductGallery);
    });
  };

  window.Livewire.on('product-gallery-updated', initializeAfterMorph);
  window.Livewire.hook('morph.updated', initializeAfterMorph);
  window.Livewire.hook('morphed', initializeAfterMorph);
}

if (window.Livewire) {
  registerProductGalleryListeners();
} else {
  document.addEventListener('livewire:init', registerProductGalleryListeners, { once: true });
}

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
