<?php
?>
<script src="../../assets/js/sidebar-nav-transition.js?v=2"></script>
<script>
(function () {
    'use strict';
    function promoteEarlySidebarState() {
        if (!document.body) {
            return;
        }
        if (document.documentElement.classList.contains('staff-sidebar-collapsed-early')) {
            document.documentElement.classList.remove('staff-sidebar-collapsed-early');
            document.body.classList.add('staff-sidebar-collapsed');
        }
        if (document.documentElement.classList.contains('examinee-sidebar-collapsed-early')) {
            document.documentElement.classList.remove('examinee-sidebar-collapsed-early');
            document.body.classList.add('examinee-sidebar-collapsed');
        }
    }

    window.RTC10PromoteSidebarState = promoteEarlySidebarState;

    try {
        var path = (location.pathname || '').replace(/\\/g, '/');
        var isExaminee = /\/examinee\//.test(path);

        if (isExaminee) {
            if (!window.matchMedia('(max-width: 991.98px)').matches
                && localStorage.getItem('rtc10_examinee_sidebar_collapsed') === '1') {
                document.documentElement.classList.add('examinee-sidebar-collapsed-early');
            }
        } else if (!window.matchMedia('(max-width: 768px)').matches
            && localStorage.getItem('rtc10_staff_sidebar_collapsed') === '1') {
            document.documentElement.classList.add('staff-sidebar-collapsed-early');
        }
    } catch (e) {
    }

    if (document.body) {
        promoteEarlySidebarState();
    } else {
        document.addEventListener('DOMContentLoaded', promoteEarlySidebarState);
    }
})();
</script>
