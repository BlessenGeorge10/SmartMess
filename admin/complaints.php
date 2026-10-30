<?php
session_start();

if (!isset($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /smartmess/login.php');
    exit;
}

require_once __DIR__ . '/../db_config.php';

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $ticket_id = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
    $new_status = trim((string)($_POST['status'] ?? ''));
    $remark = trim((string)($_POST['remark'] ?? ''));
    $allowed_statuses = ['Pending', 'In Progress', 'Resolved'];

    if (!$ticket_id || $ticket_id < 1) {
        $message = 'Please select a valid complaint.';
        $message_type = 'error';
    } elseif (!in_array($new_status, $allowed_statuses, true)) {
        $message = 'Please select a valid status.';
        $message_type = 'error';
    } else {
        $default_remark = $remark !== '' ? $remark : 'Reviewed by admin team.';
        $stmt = $conn->prepare(
            'UPDATE feedback
             SET status = ?, manager_response = ?
             WHERE id = ?'
        );

        if (!$stmt) {
            $message = 'Database error while updating complaint status.';
            $message_type = 'error';
        } else {
            $stmt->bind_param('ssi', $new_status, $default_remark, $ticket_id);

            if ($stmt->execute()) {
                $message = 'Complaint #' . $ticket_id . ' updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Unable to update the complaint.';
                $message_type = 'error';
            }

            $stmt->close();
        }
    }
}

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function badge_class($status)
{
    switch (strtolower(trim((string)$status))) {
        case 'in progress':
            return 'status-progress';
        case 'resolved':
            return 'status-resolved';
        default:
            return 'status-pending';
    }
}

$feedback = [];
$sql = "
    SELECT f.id, f.user_id, f.category, f.message, f.status, f.manager_response,
           f.created_at, u.name AS student_name, u.student_id AS student_id
    FROM feedback f
    LEFT JOIN users u ON u.id = f.user_id
    ORDER BY f.created_at DESC
";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $feedback[] = $row;
    }
} else {
    $message = 'Unable to load complaint data from the database.';
    $message_type = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback & Complaints | SmartMess Admin</title>
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
            --warning: #d97706;
            --success: #16a34a;
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
        button, select, textarea { font: inherit; }

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
        }

        .page-header {
            margin-bottom: 22px;
        }

        .page-header h2 {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.04em;
            margin-bottom: 8px;
        }

        .page-header p {
            color: var(--muted);
            font-size: 14px;
        }

        .card-panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow);
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-box {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-size: 13px;
            border-left: 4px solid var(--sky);
            background: var(--sky-soft);
            color: #0f172a;
        }

        .alert-box.error {
            background: #fff1f2;
            border-left-color: var(--danger);
            color: #7f1d1d;
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

        .status-progress {
            background: #e0f2fe;
            color: #0f766e;
        }

        .status-resolved {
            background: #dcfce7;
            color: #166534;
        }

        .ticket-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 220px;
        }

        .select-status,
        .remark-box {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 9px 10px;
            background: #f8fafc;
            color: var(--text);
        }

        .remark-box {
            min-height: 72px;
            resize: vertical;
        }

        .btn-update {
            background: var(--navy);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            padding: 9px 12px;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .btn-update:hover {
            background: var(--deep-navy);
        }

        .empty-state {
            color: var(--muted);
            background: #f8fafc;
            border: 1px dashed var(--line);
            border-radius: 12px;
            padding: 18px;
            text-align: center;
            font-size: 14px;
        }

        @media (max-width: 900px) {
            .app-window {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-width: auto;
            }

            .nav-list {
                padding: 0 14px 18px;
            }

            .main-workspace {
                padding: 22px 18px 28px;
            }
        }
    </style>
</head>
<body>
    <div class="app-window">
        <aside class="sidebar">
            <div class="sidebar-top">
                <div class="brand-logo">
                    <i class="fa-solid fa-utensils"></i>
                    SmartMess
                </div>

                <nav class="nav-list">
                    <a href="/smartmess/admin/dashboard.php" class="nav-link"><i class="fa-solid fa-border-all"></i>Dashboard</a>
                    <a href="/smartmess/admin/user-management.php" class="nav-link"><i class="fa-solid fa-users"></i>User Management</a>
                    <a href="/smartmess/admin/manage-menu.php" class="nav-link"><i class="fa-solid fa-calendar-days"></i>Menu Management</a>
                    <a href="/smartmess/admin/complaints.php" class="nav-link active"><i class="fa-solid fa-comment-dots"></i>Feedback</a>
                    <a href="/smartmess/admin/announcements.php" class="nav-link"><i class="fa-solid fa-bullhorn"></i>Announcements</a>
                    <a href="/smartmess/admin/reports.php" class="nav-link"><i class="fa-solid fa-chart-pie"></i>Reports</a>
                </nav>
            </div>

            <a href="/smartmess/logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i>Log Out</a>
        </aside>

        <main class="main-workspace">
            <div class="page-header">
                <h2>Student Feedback & Complaints</h2>
                <p>Review student concerns and update the case status.</p>
            </div>

            <div class="card-panel">
                <div class="card-title">
                    <i class="fa-solid fa-inbox" style="color: #3b52a0;"></i>
                    Feedback Tickets
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert-box <?php echo ($message_type === 'error') ? 'error' : ''; ?>">
                        <?php echo e($message); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($feedback)): ?>
                    <div class="empty-state">No complaint tickets have been submitted yet.</div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ticket ID</th>
                                <th>Student</th>
                                <th>Category</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Admin Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedback as $ticket): ?>
                                <tr>
                                    <td>#<?php echo (int)($ticket['id'] ?? 0); ?></td>
                                    <td>
                                        <strong><?php echo e($ticket['student_name'] ?: ('Student #' . ($ticket['user_id'] ?? ''))); ?></strong><br>
                                        <span style="color: var(--muted); font-size: 12px;">
                                            <?php echo e($ticket['student_id'] ?: 'No student ID'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo e($ticket['category'] ?? 'General'); ?></td>
                                    <td><?php echo nl2br(e($ticket['message'] ?? 'No message provided.')); ?></td>
                                    <td>
                                        <span class="badge-status <?php echo badge_class($ticket['status'] ?? 'Pending'); ?>">
                                            <?php echo e($ticket['status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form class="ticket-form" method="POST">
                                            <input type="hidden" name="ticket_id" value="<?php echo (int)($ticket['id'] ?? 0); ?>">
                                            <select name="status" class="select-status">
                                                <option value="Pending" <?php echo (strtolower((string)($ticket['status'] ?? 'Pending')) === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="In Progress" <?php echo (strtolower((string)($ticket['status'] ?? 'Pending')) === 'in progress') ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="Resolved" <?php echo (strtolower((string)($ticket['status'] ?? 'Pending')) === 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                                            </select>
                                            <textarea name="remark" class="remark-box" placeholder="Add admin response or case update..."><?php echo e($ticket['manager_response'] ?? ''); ?></textarea>
                                            <button type="submit" name="update_status" class="btn-update">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
