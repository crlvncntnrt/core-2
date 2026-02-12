<!-- footer.php -->
</div> <!-- end row -->
</div> <!-- end container-fluid -->

<!-- ✅ jQuery FIRST (before all other scripts) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- jsPDF for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<!-- ✅ COMBINED SESSION MANAGEMENT -->
<script>
// ========================================
// GLOBAL SESSION MANAGEMENT
// ========================================
(function() {
    'use strict';
    
    // ✅ SHARED STATE - accessible by both handlers
    window.sessionManager = {
        isLoggingOut: false,
        sessionExpiredShown: false,
        
        // Mark as logging out
        markLogout: function() {
            this.isLoggingOut = true;
            this.sessionExpiredShown = true;
        },
        
        // Check if can show alert
        canShowAlert: function() {
            return !this.isLoggingOut && !this.sessionExpiredShown;
        },
        
        // Show session expired alert
        showExpiredAlert: function(redirectUrl) {
            if (!this.canShowAlert()) return;
            
            this.sessionExpiredShown = true;
            
            Swal.fire({
                icon: 'warning',
                title: 'Session Expired',
                html: '<p style="color: #856404; font-weight: bold; font-size: 1rem; margin: 10px 0;">Your session has expired due to inactivity.</p><p style="color: #6c757d; font-size: 0.95rem; margin: 10px 0;">Please log in again to continue.</p>',
                confirmButtonText: 'Go to Login',
                confirmButtonColor: '#3085d6',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(function() {
                window.location.href = redirectUrl || '/admin/login.php';
            });
        }
    };
    
})();

// ========================================
// AJAX SESSION EXPIRY HANDLER (jQuery)
// ========================================
$(document).ready(function() {
    
    // ✅ Detect logout clicks
    $(document).on('click', 'a[href*="logout.php"], button[onclick*="logout"]', function() {
        window.sessionManager.markLogout();
    });
    
    // ✅ Handle AJAX errors (401 Unauthorized)
    $(document).ajaxError(function(event, xhr, settings) {
        if (window.sessionManager.isLoggingOut) return;
        
        if (xhr.status === 401) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.session_expired || response.error === 'Session expired') {
                    var redirectUrl = response.redirect || '/admin/login.php';
                    window.sessionManager.showExpiredAlert(redirectUrl);
                }
            } catch (e) {
                window.sessionManager.showExpiredAlert('/admin/login.php');
            }
        }
    });

    // ✅ Handle AJAX success responses with session expiry flag
    $(document).ajaxSuccess(function(event, xhr, settings) {
        if (window.sessionManager.isLoggingOut) return;
        
        try {
            var response = JSON.parse(xhr.responseText);
            
            if (response.session_expired === true || 
                (response.error && response.error === 'Session expired')) {
                var redirectUrl = response.redirect || '/admin/login.php';
                window.sessionManager.showExpiredAlert(redirectUrl);
            }
        } catch (e) {
            // Not JSON or no session issue, continue normally
        }
    });

});

// ========================================
// INACTIVITY TIMEOUT HANDLER (Vanilla JS)
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    let sessionTimeout = 120; // 2 minutes in seconds
    let countdownInterval;
    let lastActivity = Date.now();

    // ✅ Stop session checker when logout button is clicked
    document.addEventListener('click', function(e) {
        const target = e.target.closest('a[href*="logout.php"], button[onclick*="logout"]');
        if (target) {
            window.sessionManager.markLogout();
            clearInterval(countdownInterval);
        }
    });

    // Reset activity timer on user interaction
    function resetActivityTimer() {
        if (window.sessionManager.isLoggingOut) return;
        
        lastActivity = Date.now();
        
        // Send AJAX request to update session
        fetch('/admin/inc/update_session_activity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update_activity'
            })
        })
        .then(response => response.json())
        .then(data => {
            // ✅ Check if session is still valid
            if (data.session_valid === false && window.sessionManager.canShowAlert()) {
                clearInterval(countdownInterval);
                window.sessionManager.showExpiredAlert('/admin/login.php');
            }
        })
        .catch(() => {
            // Silently fail if server not reachable
        });
    }

    // Check session timeout (client-side)
    function checkSessionTimeout() {
        if (!window.sessionManager.canShowAlert()) return;
        
        const elapsed = Math.floor((Date.now() - lastActivity) / 1000);
        const remaining = sessionTimeout - elapsed;

        if (remaining <= 0) {
            clearInterval(countdownInterval);
            window.sessionManager.showExpiredAlert('/admin/login.php');
        }
    }

    // Activity events
    const events = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];
    events.forEach(event => {
        document.addEventListener(event, resetActivityTimer, {
            passive: true
        });
    });

    // Check every second
    countdownInterval = setInterval(checkSessionTimeout, 1000);

    // Initial activity
    resetActivityTimer();
});
</script>

</body>
</html>