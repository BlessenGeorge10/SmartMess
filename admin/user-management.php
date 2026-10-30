<?php
session_start();

if (!isset($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /smartmess/login.php');
    exit;
}

require_once __DIR__ . '/../db_config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';

    if ($user_id > 0 && in_array($action, ['approve', 'reject'], true)) {
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';

        $stmt = $conn->prepare(
            "UPDATE users
             SET status = ?
             WHERE id = ? AND role = 'student'"
        );

        if ($stmt) {
            $stmt->bind_param('si', $new_status, $user_id);

            if ($stmt->execute()) {
                $message = ($action === 'approve')
                    ? 'Student approved successfully.'
                    : 'Student registration rejected.';
            } else {
                $message = 'Unable to update student status.';
            }

            $stmt->close();
        } else {
            $message = 'Database error while updating student status.';
        }
    }
}

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function status_badge($role, $status)
{
    $role = strtolower(trim((string)$role));
    $status = strtolower(trim((string)$status));

    if ($role === 'admin') {
        return 'badge-admin';
    }

    if ($role === 'manager') {
        return 'badge-manager';
    }

    if ($status === 'approved') {
        return 'badge-approved';
    }

    if ($status === 'pending') {
        return 'badge-pending';
    }

    if ($status === 'rejected') {
        return 'badge-rejected';
    }

    return 'badge-student';
}

function status_label($role, $status)
{
    $role = strtolower(trim((string)$role));
    $status = strtolower(trim((string)$status));

    if ($role === 'admin') {
        return 'Admin';
    }

    if ($role === 'manager') {
        return 'Manager';
    }

    if ($status === 'approved') {
        return 'Approved';
    }

    if ($status === 'pending') {
        return 'Pending';
    }

    if ($status === 'rejected') {
        return 'Rejected';
    }

    return 'Student';
}

$filter_role = isset($_GET['role']) ? strtolower(trim((string)$_GET['role'])) : 'all';
$filter_status = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : 'all';
$search_term = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

$summary_sql = "
    SELECT
        SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) AS total_students,
        SUM(CASE WHEN role = 'student' AND status = 'approved' THEN 1 ELSE 0 END) AS approved_students,
        SUM(CASE WHEN role = 'student' AND status = 'pending' THEN 1 ELSE 0 END) AS pending_students,
        SUM(CASE WHEN role = 'manager' THEN 1 ELSE 0 END) AS total_managers,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS total_admins
    FROM users";

$summary_result = $conn->query($summary_sql);
$summary = [
    'total_students' => 0,
    'approved_students' => 0,
    'pending_students' => 0,
    'total_managers' => 0,
    'total_admins' => 0,
];

if ($summary_result && $summary_result->num_rows > 0) {
    $summary_row = $summary_result->fetch_assoc();
    $summary['total_students'] = (int)($summary_row['total_students'] ?? 0);
    $summary['approved_students'] = (int)($summary_row['approved_students'] ?? 0);
    $summary['pending_students'] = (int)($summary_row['pending_students'] ?? 0);
    $summary['total_managers'] = (int)($summary_row['total_managers'] ?? 0);
    $summary['total_admins'] = (int)($summary_row['total_admins'] ?? 0);
}

$pending_students = [];
$pending_stmt = $conn->prepare(
    "SELECT id, name, student_id, email, username, status, created_at
     FROM users
     WHERE role = 'student' AND status = 'pending'
     ORDER BY created_at DESC"
);
if ($pending_stmt) {
    $pending_stmt->execute();
    $pending_students = $pending_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pending_stmt->close();
}

$all_users = [];

$query = "
    SELECT id, name, student_id, email, username, role, status
    FROM users
    WHERE 1 = 1";

$params = [];
$types = '';

if ($search_term !== '') {
    $query .= " AND (
        LOWER(name) LIKE ? OR
        LOWER(student_id) LIKE ? OR
        LOWER(email) LIKE ? OR
        LOWER(username) LIKE ?
    )";
    $search_like = '%' . strtolower($search_term) . '%';
    $params = [$search_like, $search_like, $search_like, $search_like];
    $types = 'ssss';
}

if (in_array($filter_role, ['student', 'manager', 'admin'], true)) {
    $query .= ' AND role = ?';
    $params[] = $filter_role;
    $types .= 's';
}

if (in_array($filter_status, ['pending', 'approved', 'rejected'], true)) {
    $query .= ' AND status = ?';
    $params[] = $filter_status;
    $types .= 's';
}

$query .= ' ORDER BY id ASC';

$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $all_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | SmartMess Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy: #2c3e50;
            --deep-navy: #1e2b37;
            --bg: #f0f2f8;
            --panel: #ffffff;
            --line: #e2e8f0;
            --text: #1a202c;
            --muted: #718096;
            --sky: #38bdf8;
            --sky-soft: #eaf9ff;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
            --shadow: 0 10px 30px rgba(30, 43, 55, 0.05);
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
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        a { text-decoration: none; }
        button, input, select { font: inherit; }

        .app-window {
            display: flex;
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
            background: rgba(56, 189, 248, 0.14);
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
            padding: 30px;
        }

        .page-header {
            margin-bottom: 22px;
        }

        .page-header h2 {
            font-size: 28px;
            letter-spacing: -0.04em;
            margin-bottom: 6px;
        }

        .page-header p {
            color: var(--muted);
            font-size: 14px;
        }

        .alert-box {
            background: #e6fffa;
            border-left: 4px solid #2bb4a9;
            color: #185d63;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 22px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .stat-card {
            display: flex;
            gap: 14px;
            align-items: center;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 18px 18px;
            box-shadow: var(--shadow);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-icon.blue { background: #ebf3ff; color: #3b52a0; }
        .stat-icon.green { background: #eafaf3; color: var(--success); }
        .stat-icon.orange { background: #fff2df; color: var(--warning); }
        .stat-icon.cyan { background: var(--sky-soft); color: var(--sky); }

        .stat-info h4 {
            font-size: 24px;
            letter-spacing: -0.04em;
            margin-bottom: 4px;
        }

        .stat-info p {
            color: var(--muted);
            font-size: 12px;
        }

        .card-panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
        }

        .card-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 700;
        }

        .card-title i {
            color: var(--navy);
        }

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
            align-items: center;
        }

        .search-box {
            flex: 1 1 220px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fafcff;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 10px 12px;
        }

        .search-box input {
            width: 100%;
            border: none;
            background: transparent;
            outline: none;
            color: var(--text);
        }

        select {
            min-width: 150px;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fafcff;
            color: var(--text);
            outline: none;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--navy);
            color: #fff;
        }

        .btn-secondary {
            background: #eef3ff;
            color: #3b52a0;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 920px;
        }

        .data-table th,
        .data-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #edf2f7;
            text-align: left;
            font-size: 13px;
            vertical-align: middle;
        }

        .data-table th {
            color: #8a9bb5;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.06em;
            font-weight: 700;
        }

        .data-table tbody tr:hover {
            background: #fafcff;
        }

        .badge-role {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge-admin { background: #f1e7ff; color: #5b3ca8; }
        .badge-manager { background: #dff9eb; color: #1b7d4d; }
        .badge-student { background: #ecf1f5; color: #475569; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fff0d6; color: #a85c00; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        .badge-active { background: #dbeafe; color: #1d4ed8; }

        .action-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .approve-btn, .reject-btn {
            border: none;
            border-radius: 10px;
            color: #fff;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .approve-btn {
            background: #2f9e66;
        }

        .reject-btn {
            background: #d92d3a;
        }

        .empty-message {
            color: var(--muted);
            font-size: 13px;
            padding: 10px 0 6px;
        }

        .status-label {
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 1100px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
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

            .main-workspace {
                padding: 16px;
            }
        }

        @media (max-width: 560px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .card-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
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
                    <a href="/smartmess/admin/dashboard.php" class="nav-link"><i class="fa-solid fa-border-all"></i> Dashboard</a>
                    <a href="/smartmess/admin/user-management.php" class="nav-link active"><i class="fa-solid fa-users"></i> User Management</a>
                    <a href="/smartmess/admin/manage-menu.php" class="nav-link"><i class="fa-solid fa-calendar-days"></i> Menu Management</a>
                    <a href="/smartmess/admin/complaints.php" class="nav-link"><i class="fa-solid fa-comment-dots"></i> Feedback</a>
                    <a href="/smartmess/admin/announcements.php" class="nav-link"><i class="fa-solid fa-bullhorn"></i> Announcements</a>
                    <a href="/smartmess/admin/reports.php" class="nav-link"><i class="fa-solid fa-chart-pie"></i> Reports</a>
                </nav>
            </div>

            <a href="/smartmess/logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
        </aside>

        <main class="main-workspace">
            <div class="page-header">
                <h2>User Management</h2>
                <p>Manage SmartMess accounts, approvals, and system users.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert-box">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?php echo e($message); ?></span>
                </div>
            <?php endif; ?>

            <section class="stats-grid">
                <article class="stat-card">
                    <div class="stat-icon blue"><i class="fa-solid fa-user-graduate"></i></div>
                    <div class="stat-info">
                        <h4><?php echo $summary['total_students']; ?></h4>
                        <p>Total Students</p>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="stat-info">
                        <h4><?php echo $summary['approved_students']; ?></h4>
                        <p>Approved</p>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon orange"><i class="fa-solid fa-user-clock"></i></div>
                    <div class="stat-info">
                        <h4><?php echo $summary['pending_students']; ?></h4>
                        <p>Pending</p>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon cyan"><i class="fa-solid fa-people-roof"></i></div>
                    <div class="stat-info">
                        <h4><?php echo $summary['total_managers']; ?></h4>
                        <p>Managers</p>
                    </div>
                </article>
            </section>

            <section class="card-panel">
                <div class="card-head">
                    <div class="card-title">
                        <i class="fa-solid fa-user-clock"></i>
                        <span>Pending Student Registrations</span>
                    </div>
                </div>

                <?php if (empty($pending_students)): ?>
                    <p class="empty-message"><i class="fa-solid fa-circle-check"></i> No pending student registrations.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Student ID</th>
                                    <th>Email</th>
                                    <th>Username</th>
                                    <th>Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_students as $student): ?>
                                    <tr>
                                        <td><strong><?php echo e($student['name']); ?></strong></td>
                                        <td><?php echo e($student['student_id']); ?></td>
                                        <td><?php echo e($student['email']); ?></td>
                                        <td><?php echo e($student['username']); ?></td>
                                        <td><?php echo !empty($student['created_at']) ? date('d M Y', strtotime((string)$student['created_at'])) : '—'; ?></td>
                                        <td>
                                            <form method="POST" class="action-form" onsubmit="return confirm('Approve this student registration?');">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$student['id']; ?>">
                                                <button type="submit" name="action" value="approve" class="approve-btn"><i class="fa-solid fa-check"></i> Approve</button>
                                                <button type="submit" name="action" value="reject" class="reject-btn" onclick="this.form.onsubmit = function(){ return confirm('Reject this student registration?'); }"><i class="fa-solid fa-xmark"></i> Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card-panel">
                <div class="card-head">
                    <div class="card-title">
                        <i class="fa-solid fa-users"></i>
                        <span>All System Users</span>
                    </div>
                </div>

                <form method="GET" class="filter-bar">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="search" value="<?php echo e($search_term); ?>" placeholder="Search user by name, email, student ID...">
                    </div>

                    <select name="role">
                        <option value="all" <?php echo ($filter_role === 'all') ? 'selected' : ''; ?>>All Roles</option>
                        <option value="student" <?php echo ($filter_role === 'student') ? 'selected' : ''; ?>>Student</option>
                        <option value="manager" <?php echo ($filter_role === 'manager') ? 'selected' : ''; ?>>Manager</option>
                        <option value="admin" <?php echo ($filter_role === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>

                    <select name="status">
                        <option value="all" <?php echo ($filter_status === 'all') ? 'selected' : ''; ?>>All Status</option>
                        <option value="approved" <?php echo ($filter_status === 'approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="rejected" <?php echo ($filter_status === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                    </select>

                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="/smartmess/admin/user-management.php" class="btn btn-secondary"><i class="fa-solid fa-rotate-right"></i> Reset</a>
                </form>

                <?php if (empty($all_users)): ?>
                    <p class="empty-message">No users match the current filter.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Student ID</th>
                                    <th>Email</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_users as $user): ?>
                                    <tr>
                                        <td><strong><?php echo e($user['name']); ?></strong></td>
                                        <td><?php echo !empty($user['student_id']) ? e($user['student_id']) : '-'; ?></td>
                                        <td><?php echo e($user['email']); ?></td>
                                        <td><?php echo e($user['username']); ?></td>
                                        <td>
                                            <?php
                                                $role = strtolower(trim((string)($user['role'] ?? '')));
                                                if ($role === 'admin') {
                                                    echo '<span class="badge-role badge-admin">Admin</span>';
                                                } elseif ($role === 'manager') {
                                                    echo '<span class="badge-role badge-manager">Manager</span>';
                                                } else {
                                                    echo '<span class="badge-role badge-student">Student</span>';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                                $status = strtolower(trim((string)($user['status'] ?? '')));
                                                if ($role === 'admin' || $role === 'manager') {
                                                    echo '<span class="badge-role badge-active">Active</span>';
                                                } elseif ($status === 'approved') {
                                                    echo '<span class="badge-role badge-approved">Approved</span>';
                                                } elseif ($status === 'pending') {
                                                    echo '<span class="badge-role badge-pending">Pending</span>';
                                                } elseif ($status === 'rejected') {
                                                    echo '<span class="badge-role badge-rejected">Rejected</span>';
                                                } else {
                                                    echo '<span class="badge-role badge-student">Student</span>';
                                                }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
