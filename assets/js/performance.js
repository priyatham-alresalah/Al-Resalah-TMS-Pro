/**
 * Performance & UX Helper Functions
 */

/**
 * Prevent double form submissions
 */
(function() {
  document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
      form.addEventListener('submit', function(e) {
        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitBtn && !submitBtn.disabled) {
          submitBtn.disabled = true;
          submitBtn.textContent = submitBtn.textContent.replace(/^(.*?)(\s*\(.*\))?$/, '$1 (Processing...)');
          
          // Re-enable after 5 seconds as fallback
          setTimeout(function() {
            submitBtn.disabled = false;
            submitBtn.textContent = submitBtn.textContent.replace(/\s*\(Processing\.\.\.\)/, '');
          }, 5000);
        }
      });
    });
  });
})();

/**
 * Show loading indicator for async operations
 */
function showLoading(element) {
  if (!element) return;
  
  const loading = document.createElement('div');
  loading.className = 'loading-indicator';
  loading.style.cssText = 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1000; background: rgba(255,255,255,0.9); padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);';
  loading.innerHTML = '<div style="text-align: center;"><div style="border: 3px solid #f3f4f6; border-top: 3px solid #3b82f6; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 10px;"></div><div>Loading...</div></div>';
  
  const style = document.createElement('style');
  style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
  if (!document.querySelector('style[data-loading-style]')) {
    style.setAttribute('data-loading-style', 'true');
    document.head.appendChild(style);
  }
  
  element.style.position = 'relative';
  element.appendChild(loading);
  
  return loading;
}

function hideLoading(loadingElement) {
  if (loadingElement && loadingElement.parentNode) {
    loadingElement.parentNode.removeChild(loadingElement);
  }
}

/**
 * Add loading indicator to links that navigate to new pages
 */
document.addEventListener('DOMContentLoaded', function() {
  const links = document.querySelectorAll('a[href]:not([href^="#"]):not([href^="javascript:"])');
  links.forEach(function(link) {
    link.addEventListener('click', function(e) {
      // Only show loading for same-origin links
      if (link.hostname === window.location.hostname) {
        const loading = showLoading(document.body);
        // Remove loading if navigation doesn't happen (e.g., preventDefault)
        setTimeout(function() {
          hideLoading(loading);
        }, 3000);
      }
    });
  });
});
