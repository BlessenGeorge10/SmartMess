<?php
session_start();

if (!isset($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /smartmess/login.php');
    exit;
}

require_once __DIR__ . '/../db_config.php';

$admin_name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin';

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function format_date($value)
{
    if (empty($value)) {
        return '—';
    }

    $timestamp = strtotime((string)$value);

    if ($timestamp === false) {
        return e($value);
    }

    return date('d M Y', $timestamp);
}

$total_students = 0;
$approved_students = 0;
$pending_students = 0;
$total_managers = 0;
$total_complaints = 0;
$pending_complaints = 0;
$avg_meal_rating = 0.0;
$total_meal_skips = 0;
$active_meal_skips = 0;
$recent_pending_registrations = [];
$recent_complaints = [];
$recent_announcements = [];

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'student'");
if ($result) {
    $row = $result->fetch_assoc();
    $total_students = (int)($row['total'] ?? 0);
}

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'student' AND status = 'approved'");
if ($result) {
    $row = $result->fetch_assoc();
    $approved_students = (int)($row['total'] ?? 0);
}

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'student' AND status = 'pending'");
if ($result) {
    $row = $result->fetch_assoc();
    $pending_students = (int)($row['total'] ?? 0);
}

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'manager'");
if ($result) {
    $row = $result->fetch_assoc();
    $total_managers = (int)($row['total'] ?? 0);
}

$result = $conn->query("SELECT COUNT(*) AS total FROM feedback");
if ($result) {
    $row = $result->fetch_assoc();
    $total_complaints = (int)($row['total'] ?? 0);
}

$result = $conn->query("SELECT COUNT(*) AS total FROM feedback WHERE status IN ('Pending', 'In Progress')");
if ($result) {
    $row = $result->fetch_assoc();
    $pending_complaints = (int)($row['total'] ?? 0);
}

$result = $conn->query("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM food_item_ratings");
if ($result) {
    $row = $result->fetch_assoc();
    $avg_meal_rating = (float)($row['avg_rating'] ?? 0);
}

$result = $conn->query("SELECT COUNT(*) AS total FROM meal_skips");
if ($result) {
    $row = $result->fetch_assoc();
    $total_meal_skips = (int)($row['total'] ?? 0);
}

$result = $conn->query("SELECT COUNT(*) AS total FROM meal_skips WHERE LOWER(status) = 'active'");
if ($result) {
    $row = $result->fetch_assoc();
    $active_meal_skips = (int)($row['total'] ?? 0);
}

$pending_stmt = $conn->prepare(
    "SELECT id, name, student_id, email, username, created_at
     FROM users
     WHERE role = 'student' AND status = 'pending'
     ORDER BY created_at DESC
     LIMIT 5"
);
if ($pending_stmt) {
    $pending_stmt->execute();
    $pending_result = $pending_stmt->get_result();
    while ($row = $pending_result->fetch_assoc()) {
        $recent_pending_registrations[] = $row;
    }
    $pending_stmt->close();
}

$complaints_stmt = $conn->prepare(
    "SELECT id, user_id, category, message, status, created_at
     FROM feedback
     ORDER BY created_at DESC
     LIMIT 5"
);
if ($complaints_stmt) {
    $complaints_stmt->execute();
    $complaints_result = $complaints_stmt->get_result();
    while ($row = $complaints_result->fetch_assoc()) {
        $recent_complaints[] = $row;
    }
    $complaints_stmt->close();
}

$announcements_stmt = $conn->prepare(
    "SELECT id, title, content, author, created_at
     FROM announcements
     ORDER BY created_at DESC
     LIMIT 4"
);
if ($announcements_stmt) {
    $announcements_stmt->execute();
    $announcements_result = $announcements_stmt->get_result();
    while ($row = $announcements_result->fetch_assoc()) {
        $recent_announcements[] = $row;
    }
    $announcements_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SmartMess</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy: #2c3e50;
            --deep-navy: #1e2b37;
            --sky: #38bdf8;
            --sky-soft: #e8f8ff;
            --bg: #f0f2f8;
            --panel: #ffffff;
            --text: #1a202c;
            --muted: #718096;
            --line: #e2e8f0;
            --danger: #dc2626;
            --warning: #d97706;
            --success: #16a34a;
            --shadow: 0 10px 28px rgba(30, 43, 55, 0.08);
            --radius: 20px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
        }

        body {
            background: var(--bg);
            min-height: 100vh;
            color: var(--text);
            overflow-x: hidden;
        }

        a { text-decoration: none; }

        .app-window {
            display: flex;
            width: 100%;
            min-height: 100vh;
            background: var(--bg);
        }

        .sidebar {
            width: 240px;
            min-width: 240px;
            background: linear-gradient(180deg, var(--navy), var(--deep-navy));
            color: #fff;
            padding: 28px 0 22px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 24px 28px;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .brand-logo i {
            width: 34px;
            height: 34px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(56, 189, 248, 0.14);
            color: var(--sky);
            font-size: 16px;
        }

        .nav-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 0 18px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            color: rgba(255,255,255,0.78);
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .nav-link i {
            width: 18px;
            text-align: center;
        }

        .nav-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.04);
        }

        .nav-link.active {
            background: #eef4fb;
            color: var(--deep-navy);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.1);
        }

        .logout-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 24px 0;
            color: #f7b7b7;
            font-size: 14px;
            font-weight: 600;
        }

        .logout-link:hover {
            color: #ffd9d9;
        }

        .main-workspace {
            flex: 1;
            padding: 28px 30px 34px;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            width: min(360px, 100%);
            padding: 11px 14px;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 999px;
            box-shadow: var(--shadow);
        }

        .search-box i {
            color: var(--muted);
            font-size: 14px;
        }

        .search-box input {
            border: none;
            outline: none;
            width: 100%;
            background: transparent;
            font-size: 13px;
            color: var(--text);
        }

        .top-right-tools {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .icon-btn {
            position: relative;
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--panel);
            border: 1px solid var(--line);
            color: var(--navy);
            box-shadow: var(--shadow);
        }

        .badge-dot {
            position: absolute;
            top: 9px;
            right: 9px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ef4444;
            border: 2px solid var(--panel);
        }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 6px 12px 6px 7px;
            box-shadow: var(--shadow);
        }

        .avatar-img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--navy);
            color: #fff;
            font-size: 12px;
        }

        .user-pill span {
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 14px;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 18px 20px;
            box-shadow: var(--shadow);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            font-size: 20px;
        }

        .stat-icon.blue { background: #ebf3ff; color: #3b52a0; }
        .stat-icon.green { background: #eafaf3; color: var(--success); }
        .stat-icon.orange { background: #fff2df; color: var(--warning); }
        .stat-icon.purple { background: #f1ebff; color: #7c3aed; }
        .stat-icon.cyan { background: var(--sky-soft); color: #0ea5e9; }
        .stat-icon.red { background: #ffefef; color: var(--danger); }

        .stat-info {
            min-width: 0;
        }

        .stat-info h4 {
            font-size: 26px;
            line-height: 1.1;
            margin-bottom: 4px;
            letter-spacing: -0.04em;
            color: var(--text);
        }

        .stat-info p {
            font-size: 12px;
            color: var(--muted);
        }

        .main-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.8fr) minmax(300px, 1fr);
            gap: 22px;
        }

        .card-panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow);
        }

        .report-overview-card .panel-head {
            margin-bottom: 0;
        }

        .report-overview-card .muted-text {
            max-width: 720px;
            margin-top: 6px;
            line-height: 1.6;
        }

        .report-overview-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            padding: 11px 16px;
            border-radius: 10px;
            background: var(--navy);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
        }

        .report-overview-link:hover {
            background: var(--deep-navy);
        }

        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .panel-head h3 {
            font-size: 16px;
            color: var(--text);
        }

        .panel-head a {
            font-size: 12px;
            color: #3b52a0;
            font-weight: 700;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #edf2f7;
            text-align: left;
            vertical-align: top;
            font-size: 13px;
        }

        .data-table th {
            color: #8a9bb5;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .data-table tbody tr:hover {
            background: #fafcff;
        }

        .muted-text {
            color: var(--muted);
        }

        .badge-status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .status-pending {
            background: #fff0d8;
            color: #a85b00;
        }

        .status-approved {
            background: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-in-progress {
            background: #e0f2fe;
            color: #0f766e;
        }

        .status-resolved {
            background: #dcfce7;
            color: #166534;
        }

        .status-other {
            background: #e5e7eb;
            color: #374151;
        }

        .action-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .action-card {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f8fafc;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
            color: var(--text);
            transition: 0.2s ease;
        }

        .action-card:hover {
            transform: translateY(-1px);
            border-color: #b9d9f6;
            background: #ffffff;
        }

        .action-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef3ff;
            color: #3b52a0;
            font-size: 14px;
        }

        .action-text h5 {
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 2px;
        }

        .action-text p {
            font-size: 11px;
            color: var(--muted);
        }

        .announcement-list,
        .list-stack {
            display: grid;
            gap: 12px;
        }

        .announcement-item {
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #f8fafc;
        }

        .announcement-item h5 {
            font-size: 14px;
            color: var(--text);
            margin-bottom: 4px;
        }

        .announcement-item p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .announcement-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 11px;
            color: var(--muted);
            margin-top: 8px;
        }

        .no-result {
            display: none;
            padding-top: 12px;
            color: var(--muted);
            font-size: 13px;
            text-align: center;
        }

        @media (max-width: 1100px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 820px) {
            .app-window {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-width: 100%;
            }

            .nav-list {
                padding-bottom: 10px;
            }

            .main-workspace {
                padding: 20px 16px 28px;
            }

            .header-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .top-right-tools {
                justify-content: flex-end;
            }
        }

        @media (max-width: 560px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 16px;
            }

            .panel-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .card-panel {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="app-window">
        <aside class="sidebar">
            <div>
                <div class="brand-logo">
                    <i class="fa-solid fa-utensils"></i>
                    <span>SmartMess</span>
                </div>

                <nav class="nav-list" aria-label="Admin sidebar navigation">
                    <a href="/smartmess/admin/dashboard.php" class="nav-link active"><i class="fa-solid fa-border-all"></i> Dashboard</a>
                    <a href="/smartmess/admin/user-management.php" class="nav-link"><i class="fa-solid fa-users"></i> User Management</a>
                    <a href="/smartmess/admin/manage-menu.php" class="nav-link"><i class="fa-solid fa-calendar-days"></i> Menu Management</a>
                    <a href="/smartmess/admin/complaints.php" class="nav-link"><i class="fa-solid fa-comment-dots"></i> Feedback</a>
                    <a href="/smartmess/admin/announcements.php" class="nav-link"><i class="fa-solid fa-bullhorn"></i> Announcements</a>
                    <a href="/smartmess/admin/reports.php" class="nav-link"><i class="fa-solid fa-chart-pie"></i> Reports</a>
                </nav>
            </div>

            <a href="/smartmess/logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
        </aside>

        <main class="main-workspace">
            <header class="header-bar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="dashboardSearch" placeholder="Search recent activity..." onkeyup="searchDashboard()">
                </div>

                <div class="top-right-tools">
                    <a href="/smartmess/admin/user-management.php" class="icon-btn" title="Pending approvals">
                        <i class="fa-regular fa-bell"></i>
                        <span class="badge-dot"></span>
                    </a>

                    <div class="user-pill">
                        <div class="avatar-img"><i class="fa-solid fa-user-gear"></i></div>
                        <span><?php echo e($admin_name); ?></span>
                    </div>
                </div>
            </header>

            <section class="stats-grid">
                <article class="stat-card">
                    <div class="stat-icon blue"><i class="fa-solid fa-user-graduate"></i></div>
                    <div class="stat-info">
                        <h4><?php echo $total_students; ?></h4>
                        <p>Total Students</p>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="stat-info">
                        <h4><?php echo $approved_students; ?></h4>
                        <p>Approved Students</p>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon orange"><i class="fa-solid fa-user-clock"></i></div>
                    <div class="stat-info">
                        <h4><?php echo $pending_students; ?></h4>
                        <p>Pending Approvals</p>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon purple"><i class="fa-solid fa-people-roof"></i></div>
                    <div class="stat-info">
                        <h4><?php echo $total_managers; ?></h4>
                        <p>Total Managers</p>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon cyan"><i class="fa-solid fa-comment-dots"></i></div>
                    <div class="stat-info">
                        <h4><?php echo $total_complaints; ?></h4>
                        <p>Total Complaints</p>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div class="stat-info">
                        <h4><?php echo $pending_complaints; ?></h4>
                        <p>Open Complaints</p>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon purple"><i class="fa-solid fa-star"></i></div>
                    <div class="stat-info">
                        <h4><?php echo number_format($avg_meal_rating, 1); ?> / 5</h4>
                        <p>Avg Meal Rating</p>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon cyan"><i class="fa-solid fa-utensils"></i></div>
                    <div class="stat-info">
                        <h4><?php echo $active_meal_skips; ?></h4>
                        <p>Active Meal Skips</p>
                    </div>
                </article>
            </section>

            <section class="card-panel report-overview-card">
                <div class="panel-head">
                    <div>
                        <h3>Reports Overview</h3>
                        <p class="muted-text">
                            View monthly food ratings, meal skips and complaints to support mess-management decisions.
                        </p>
                    </div>
                    <a href="/smartmess/admin/reports.php" class="report-overview-link">
                        View Reports
                    </a>
                </div>
            </section>

            <section class="main-grid">
                <div class="card-panel">
                    <div class="panel-head">
                        <h3>Pending Student Registrations</h3>
                        <a href="/smartmess/admin/user-management.php">View all</a>
                    </div>

                    <?php if (empty($recent_pending_registrations)): ?>
                        <p class="muted-text">No pending student registrations at the moment.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="data-table" id="registrationTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Student ID</th>
                                        <th>Email</th>
                                        <th>Submitted</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_pending_registrations as $student): ?>
                                        <tr>
                                            <td><?php echo e($student['name']); ?></td>
                                            <td><?php echo e($student['student_id']); ?></td>
                                            <td><?php echo e($student['email']); ?></td>
                                            <td><?php echo format_date($student['created_at']); ?></td>
                                            <td><span class="badge-status status-pending">Pending</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-panel">
                    <div class="panel-head">
                        <h3>Quick Actions</h3>
                    </div>

                    <div class="action-list">
                        <a href="/smartmess/admin/user-management.php" class="action-card">
                            <div class="action-icon"><i class="fa-solid fa-user-check"></i></div>
                            <div class="action-text">
                                <h5>Approve Student</h5>
                                <p>Review pending accounts</p>
                            </div>
                        </a>

                        <a href="/smartmess/admin/manage-menu.php" class="action-card">
                            <div class="action-icon"><i class="fa-solid fa-calendar-days"></i></div>
                            <div class="action-text">
                                <h5>Manage Menu</h5>
                                <p>Update meal schedule</p>
                            </div>
                        </a>

                        <a href="/smartmess/admin/complaints.php" class="action-card">
                            <div class="action-icon"><i class="fa-solid fa-comment-dots"></i></div>
                            <div class="action-text">
                                <h5>Review Feedback</h5>
                                <p>Track complaints and replies</p>
                            </div>
                        </a>

                        <a href="/smartmess/admin/announcements.php" class="action-card">
                            <div class="action-icon"><i class="fa-solid fa-bullhorn"></i></div>
                            <div class="action-text">
                                <h5>Post Announcements</h5>
                                <p>Broadcast updates</p>
                            </div>
                        </a>
                    </div>
                </div>
            </section>

            <section class="main-grid">
                <div class="card-panel">
                    <div class="panel-head">
                        <h3>Recent Complaints</h3>
                        <a href="/smartmess/admin/complaints.php">View all</a>
                    </div>

                    <?php if (empty($recent_complaints)): ?>
                        <p class="muted-text">No complaints have been submitted yet.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="data-table" id="complaintTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Category</th>
                                        <th>Message</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_complaints as $complaint): ?>
                                        <tr>
                                            <td>#<?php echo (int)$complaint['id']; ?></td>
                                            <td><?php echo e($complaint['category']); ?></td>
                                            <td><?php echo e(substr((string)$complaint['message'], 0, 70)); ?><?php echo strlen((string)$complaint['message']) > 70 ? '...' : ''; ?></td>
                                            <td>
                                                <?php
                                                    $status = (string)($complaint['status'] ?? '');
                                                    $status_class = 'status-other';
                                                    if ($status === 'Pending') { $status_class = 'status-pending'; }
                                                    elseif ($status === 'In Progress') { $status_class = 'status-in-progress'; }
                                                    elseif ($status === 'Resolved') { $status_class = 'status-resolved'; }
                                                ?>
                                                <span class="badge-status <?php echo $status_class; ?>"><?php echo e($status ?: 'Unknown'); ?></span>
                                            </td>
                                            <td><?php echo format_date($complaint['created_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-panel">
                    <div class="panel-head">
                        <h3>Latest Announcements</h3>
                        <a href="/smartmess/admin/announcements.php">Manage</a>
                    </div>

                    <?php if (empty($recent_announcements)): ?>
                        <p class="muted-text">No announcements posted yet.</p>
                    <?php else: ?>
                        <div class="announcement-list">
                            <?php foreach ($recent_announcements as $announcement): ?>
                                <article class="announcement-item">
                                    <h5><?php echo e($announcement['title']); ?></h5>
                                    <p><?php echo e(substr((string)$announcement['content'], 0, 150)); ?><?php echo strlen((string)$announcement['content']) > 150 ? '...' : ''; ?></p>
                                    <div class="announcement-meta">
                                        <span>By <?php echo e($announcement['author']); ?></span>
                                        <span><?php echo format_date($announcement['created_at']); ?></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script>
        function searchDashboard() {
            const input = document.getElementById('dashboardSearch');
            const query = input.value.trim().toLowerCase();
            const rows = document.querySelectorAll('#registrationTable tbody tr, #complaintTable tbody tr');
            const noResult = document.createElement('div');
            noResult.className = 'no-result';
            noResult.textContent = 'No matching result found.';

            let visible = 0;

            rows.forEach(function(row) {
                const text = row.textContent.toLowerCase();
                const show = query === '' || text.includes(query);
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            const existingNoResult = document.querySelector('.no-result');
            if (existingNoResult) existingNoResult.remove();

            const tables = document.querySelectorAll('#registrationTable, #complaintTable');
            if (query !== '' && visible === 0) {
                tables.forEach(function(table) {
                    table.parentNode.appendChild(noResult.cloneNode(true));
                });
            }
        }
    </script>
</body>
</html>
