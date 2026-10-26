import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'

window.setupEditor = (initialContent) => {
    let editor;

    return {
        content: initialContent,
      updatedAt: 0,
        editorReady: false,

        init(element) {
            this.$nextTick(() => {
                if (editor) {
                    editor.destroy();
                }

                this.editorReady = false;

                try {
                    editor = new Editor({
                        element: element,
                        extensions: [
                            StarterKit.configure({
                                heading: {
                                    levels: [1, 2, 3, 4, 5, 6],
                                },
                            }),
                            Link.configure({
                                openOnClick: false,
                                HTMLAttributes: {
                                class: 'text-decoration-underline',
                                },
                            }),
                        ],
                        content: this.content,
                        onUpdate: ({ editor: e }) => {
                            this.content = e.getHTML();
                          this.updatedAt = Date.now();
                        },
                        onSelectionUpdate: () => {
                          this.updatedAt = Date.now();
                        },
                        onTransaction: () => {
                            this.updatedAt = Date.now();
                        },
                    });
                    this.editorReady = true;
                } catch (error) {
                    console.error('Tiptap: Initialization failed', error);
                }
            });

            return () => {
                if (editor) {
                    editor.destroy();
                    editor = null;
                }
            };
        },

        isActive(type, opts = {}) {
          this.updatedAt;
            return editor ? editor.isActive(type, opts) : false;
        },

        canUndo() { this.updatedAt; return editor ? editor.can().undo() : false; },
        canRedo() { this.updatedAt; return editor ? editor.can().redo() : false; },

        // Link Support
        setLink() {
            if (!editor) return;
            
            // 1. Check if there's an existing link
            const previousUrl = editor.getAttributes('link').href;
            
            // 2. Get the currently selected text
            const { from, to } = editor.state.selection;
            const selectedText = editor.state.doc.textBetween(from, to, ' ');
            
            // 3. Smart suggestion: use existing link OR selected text if it looks like a URL
            const isUrl = (str) => /^(https?:\/\/|www\.)[^\s]+$/.test(str);
            const defaultUrl = previousUrl || (isUrl(selectedText) ? (selectedText.startsWith('www.') ? 'https://' + selectedText : selectedText) : '');

            const url = window.prompt('URL', defaultUrl);

            // cancelled
            if (url === null) {
                return;
            }

            // empty
            if (url === '') {
                editor.chain().focus().extendMarkRange('link').unsetLink().run();
                return;
            }

            // update link
            editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
        },

        // Formatting
        toggleBold() { if (editor) editor.chain().focus().toggleBold().run(); },
        toggleItalic() { if (editor) editor.chain().focus().toggleItalic().run(); },
        toggleStrike() { if (editor) editor.chain().focus().toggleStrike().run(); },
        toggleCode() { if (editor) editor.chain().focus().toggleCode().run(); },
        toggleHeading(level) { if (editor) editor.chain().focus().toggleHeading({ level }).run(); },
        toggleBulletList() { if (editor) editor.chain().focus().toggleBulletList().run(); },
        toggleOrderedList() { if (editor) editor.chain().focus().toggleOrderedList().run(); },
        toggleBlockquote() { if (editor) editor.chain().focus().toggleBlockquote().run(); },
        toggleCodeBlock() { if (editor) editor.chain().focus().toggleCodeBlock().run(); },
        setHorizontalRule() { if (editor) editor.chain().focus().setHorizontalRule().run(); },
        undo() { if (editor) editor.commands.undo(); },
        redo() { if (editor) editor.commands.redo(); },
    }
}
  
  
  
  
  
  
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
