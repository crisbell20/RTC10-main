        // Load dashboard statistics
        axios.get('../../api/admin/stats.php', { withCredentials: true })
            .then(function (response) {
                if (response.data.success && response.data.stats) {
                    const stats = response.data.stats;
                    
                    // Update Active Users
                    const totalUsersEl = document.getElementById('totalUsers');
                    if (totalUsersEl) {
                        totalUsersEl.textContent = stats.total_users || 0;
                    }

                    // Update Active Courses
                    const activeCoursesEl = document.getElementById('activeCourses');
                    if (activeCoursesEl) {
                        activeCoursesEl.textContent = stats.total_courses || 0;
                    }

                    // Update Active Exams
                    const activeExamsEl = document.getElementById('activeExams');
                    if (activeExamsEl) {
                        activeExamsEl.textContent = stats.active_exams ?? 0;
                    }

                    // Update Cheating Incidents
                    const cheatingEl = document.getElementById('cheatingIncidents');
                    if (cheatingEl) {
                        cheatingEl.textContent = stats.total_incidents ?? 0;
                    }
                    const cheatingLabel = document.getElementById('cheatingIncidentsLabel');
                    if (cheatingLabel) {
                        const examsWith = stats.exams_with_incidents ?? 0;
                        cheatingLabel.textContent = examsWith === 1
                            ? '1 exam with incidents'
                            : `${examsWith} exams with incidents`;
                    }
                    
                    // Update users by role if elements exist
                    if (stats.by_role) {
                        const adminCount = stats.by_role.Admin || 0;
                        const ccmdCount = stats.by_role.CCMD || 0;
                        const examineeCount = stats.by_role.Examinee || 0;
                        
                        // Update role-specific counts if elements exist
                        const adminEl = document.getElementById('statAdmin');
                        if (adminEl) adminEl.textContent = adminCount;
                        
                        const ccmdEl = document.getElementById('statCcmd');
                        if (ccmdEl) ccmdEl.textContent = ccmdCount;
                        
                        const examineeEl = document.getElementById('statExaminee');
                        if (examineeEl) examineeEl.textContent = examineeCount;
                    }
                }
            })
            .catch(function (error) {
                console.error('Error loading dashboard stats:', error);
                const totalUsersEl = document.getElementById('totalUsers');
                if (totalUsersEl) {
                    totalUsersEl.textContent = '0';
                }
                const activeCoursesEl = document.getElementById('activeCourses');
                if (activeCoursesEl) {
                    activeCoursesEl.textContent = '0';
                }
                const activeExamsEl = document.getElementById('activeExams');
                if (activeExamsEl) {
                    activeExamsEl.textContent = '0';
                }
                const cheatingEl = document.getElementById('cheatingIncidents');
                if (cheatingEl) {
                    cheatingEl.textContent = '0';
                }
                const cheatingLabel = document.getElementById('cheatingIncidentsLabel');
                if (cheatingLabel) {
                    cheatingLabel.textContent = '0 exams with incidents';
                }
            });

        axios.get('../../api/masterfiles/audit-logs.php?action=recent&limit=8', { withCredentials: true })
            .then(function (response) {
                const tbody = document.getElementById('recentAuditBody');
                if (!tbody) return;

                if (!response.data.success || !response.data.data.length) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No recent activity logged yet.</td></tr>';
                    return;
                }

                tbody.innerHTML = response.data.data.map(function (row) {
                    const statusBadge = (row.Status || 'SUCCESS') === 'FAILED'
                        ? '<span class="badge bg-danger">Failed</span>'
                        : '<span class="badge bg-success">Success</span>';
                    const when = row.Timestamp ? new Date(row.Timestamp).toLocaleString() : '—';
                    return '<tr>'
                        + '<td><strong>' + escapeHtml(row.user_display || 'Unknown') + '</strong></td>'
                        + '<td><small>' + escapeHtml(row.Action || '—') + '</small></td>'
                        + '<td><span class="badge bg-light text-dark border">' + escapeHtml(row.Module || '—') + '</span></td>'
                        + '<td><small>' + when + '</small></td>'
                        + '<td>' + statusBadge + '</td>'
                        + '</tr>';
                }).join('');
            })
            .catch(function () {
                const tbody = document.getElementById('recentAuditBody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Unable to load recent activity.</td></tr>';
                }
            });

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }
    