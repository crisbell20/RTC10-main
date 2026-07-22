
(function () {
    const base = '../../';

    axios.get(base + 'api/admin/stats.php', { withCredentials: true })
        .then(function (res) {
            if (res.data.success && res.data.stats) {
                var s = res.data.stats;
                document.getElementById('statTotal').textContent = s.total_users || 0;
                document.getElementById('statAdmin').textContent = (s.by_role && s.by_role.Admin) || 0;
                document.getElementById('statCcmd').textContent = (s.by_role && s.by_role.CCMD) || 0;
                document.getElementById('statExaminee').textContent = (s.by_role && s.by_role.Examinee) || 0;
            }
        })
        .catch(function () {
            document.getElementById('statTotal').textContent = '0';
            document.getElementById('statAdmin').textContent = '0';
            document.getElementById('statCcmd').textContent = '0';
            document.getElementById('statExaminee').textContent = '0';
        });
})();
