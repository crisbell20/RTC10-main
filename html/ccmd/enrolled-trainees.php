<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'CCMD') {
    header('Location: ../../login.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../includes/sidebar-state-early.php'; ?>
    <title>Trainees - CCMD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/staff-responsive.css?v=3">
    <link rel="stylesheet" href="../../assets/css/ccmd.css">
    <link rel="stylesheet" href="../../assets/css/monitor.css">
    <style>
        .trainees-table th { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em; color: #6b7280; white-space: nowrap; }
        .trainees-table td { vertical-align: middle; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="top-nav">
        <?php
        $navBrandSubtitle = 'Trainees';
        require __DIR__ . '/../includes/nav-brand.php';
        ?>
        <div class="nav-user">
            <span><?= htmlspecialchars($userName) ?></span>
            <button class="btn-logout" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="profile.php"><i class="bi bi-person-circle"></i>Profile</a></li>
                <li><a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="monitor-examination.php"><i class="bi bi-activity"></i>Monitor Examinations</a></li>
                <li><a href="exams.php"><i class="bi bi-journal-text"></i>View Exams</a></li>
                <li><a href="enrolled-trainees.php" class="active"><i class="bi bi-people-fill"></i>View Trainees</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h2>Trainees</h2>
                    </div>
                    <button class="btn btn-add-user" id="exportTraineesBtn">
                        <i class="bi bi-download me-2"></i>Export
                    </button>
                </div>
            </div>

            <div class="filter-card monitor-filter-card">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label small" for="filterCourse">Course</label>
                        <select id="filterCourse" class="form-select form-select-sm">
                            <option value="">All courses</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label small" for="filterBatch">Batch</label>
                        <select id="filterBatch" class="form-select form-select-sm">
                            <option value="">All batches</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label small" for="filterSection">Section</label>
                        <select id="filterSection" class="form-select form-select-sm" disabled>
                            <option value="">All sections</option>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label small" for="searchTrainee">Search</label>
                        <input type="search" id="searchTrainee" class="form-control form-control-sm" placeholder="Name, rank, or badge number...">
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <p class="text-muted small mb-0" id="traineesSummary">Loading trainees...</p>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="clearFiltersBtn">
                        <i class="bi bi-x-circle"></i> Clear filters
                    </button>
                </div>
            </div>

            <div class="section-card">
                <div class="section-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover trainees-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Rank</th>
                                    <th>Trainee</th>
                                    <th>Badge No.</th>
                                    <th>Course</th>
                                    <th>Batch</th>
                                    <th>Section</th>
                                    <th>Assigned exams</th>
                                </tr>
                            </thead>
                            <tbody id="traineesTableBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="bi bi-hourglass-split"></i> Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center p-3 border-top">
                    <small class="text-muted" id="paginationInfo">—</small>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" id="prevPageBtn" disabled>Previous</button>
                        <button type="button" class="btn btn-outline-secondary" id="nextPageBtn" disabled>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="assignedExamsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="assignedExamsModalTitle">Assigned exams</h5>
                        <small class="text-muted" id="assignedExamsModalSubtitle"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="assignedExamsModalBody">
                    <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js?v=2"></script>
    <script src="../../js/ccmd/enrolled-trainees.js?v=9"></script>
</body>
</html>
