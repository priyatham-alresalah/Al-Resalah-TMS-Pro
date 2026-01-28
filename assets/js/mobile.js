/**
 * Mobile Navigation & UX Enhancements
 */

(function() {
  'use strict';

  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function init() {
    initMobileMenu();
    initDrawerClose();
    initStickyForms();
  }

  /**
   * Initialize mobile menu toggle
   */
  function initMobileMenu() {
    const toggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.drawer-overlay');

    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', function(e) {
      e.stopPropagation();
      sidebar.classList.toggle('drawer-open');
      if (overlay) {
        overlay.classList.toggle('active');
      }
      // Prevent body scroll when drawer is open
      if (sidebar.classList.contains('drawer-open')) {
        document.body.style.overflow = 'hidden';
      } else {
        document.body.style.overflow = '';
      }
    });

    // Close drawer when clicking overlay
    if (overlay) {
      overlay.addEventListener('click', function() {
        sidebar.classList.remove('drawer-open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
      });
    }
  }

  /**
   * Close drawer after navigation
   */
  function initDrawerClose() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.drawer-overlay');

    if (!sidebar) return;

    // Close drawer when clicking menu links
    const menuLinks = sidebar.querySelectorAll('a');
    menuLinks.forEach(function(link) {
      link.addEventListener('click', function() {
        sidebar.classList.remove('drawer-open');
        if (overlay) {
          overlay.classList.remove('active');
        }
        document.body.style.overflow = '';
      });
    });
  }

  /**
   * Initialize sticky form buttons
   */
  function initStickyForms() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(function(form) {
      const actions = form.querySelector('.form-actions');
      if (!actions) return;

      // Check if form is long enough to need sticky button
      if (form.scrollHeight > window.innerHeight * 0.8) {
        actions.classList.add('form-actions-sticky');
        form.closest('.content')?.classList.add('has-sticky-form');
      }
    });
  }

  /**
   * Handle window resize
   */
  window.addEventListener('resize', function() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.drawer-overlay');
    
    // Close drawer on resize to desktop
    if (window.innerWidth > 768) {
      if (sidebar) sidebar.classList.remove('drawer-open');
      if (overlay) overlay.classList.remove('active');
      document.body.style.overflow = '';
    }
  });
})();
