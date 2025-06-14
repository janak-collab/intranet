// ============================================
// GMPM Application JavaScript
// Generated: $(date)
// ============================================

// Namespace for GMPM application
window.GMPM = window.GMPM || {};

// ============================================
// Utility Functions
// ============================================
GMPM.Utils = {
    // Show alert message
    showAlert: function(container, type, message, duration = 5000) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        
        const icons = {
            'error': '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path 
fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 
1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
            'success': '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path 
fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 
001.414 0l4-4z" clip-rule="evenodd"/></svg>',
            'info': '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path 
fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 
00-1-1H9z" clip-rule="evenodd"/></svg>'
        };
        
        alertDiv.innerHTML = `${icons[type] || ''} ${message}`;
        
        container.innerHTML = '';
        container.appendChild(alertDiv);
        
        if (duration > 0) {
            setTimeout(() => {
                alertDiv.remove();
            }, duration);
        }
    }
};

// Initialize modules based on page
document.addEventListener('DOMContentLoaded', function() {
    console.log('GMPM Application Initialized');
});
