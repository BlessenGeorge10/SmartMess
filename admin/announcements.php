<?php
session_start();

if (!isset($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /smartmess/login.php');
    exit;
}

require_once __DIR__ . '/../db_config.php';

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
    $title = trim((string)($_POST['title'] ?? ''));
    $audience = trim((string)($_POST['audience'] ?? 'Students'));
    $content = trim((string)($_POST['content'] ?? ''));

    if ($title === '' || $content === '') {
        $message = 'Please fill in the title and announcement content.';
        $message_type = 'error';
    } elseif (!in_array($audience, ['Students', 'Managers', 'Everyone'], true)) {
        $message = 'Please select a valid audience.';
        $message_type = 'error';
    } else {
        $author = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin'));
        if ($author === '') {
            $author = 'Admin';
        }

        $stmt = $conn->prepare(
            'INSERT INTO announcements (title, audience, content, author) VALUES (?, ?, ?, ?)'
        );

        if (!$stmt) {
            $message = 'Unable to save the announcement to the database.';
            $message_type = 'error';
        } else {
            $stmt->bind_param('ssss', $title, $audience, $content, $author);

            if ($stmt->execute()) {
                $announcement_id = (int)$conn->insert_id;
                if ($audience === 'Everyone') {
                    $recipient_roles = ['student', 'manager'];
                } elseif ($audience === 'Students') {
                    $recipient_roles = ['student'];
                } else {
                    $recipient_roles = ['manager'];
                }
                $notification_stmt = $conn->prepare(
                    'INSERT INTO notifications (recipient_role, recipient_id, title, message, notification_type, related_id) VALUES (?, NULL, ?, ?, ?, ?)'
                );

                if ($notification_stmt) {
                    $notification_title = $title;
                    $notification_message = 'New mess announcement: ' . $title;
                    $notification_type = 'announcement';
                    $notification_related_id = (int)$announcement_id;
                    $notifications_sent = true;

                    foreach ($recipient_roles as $recipient_role) {
                        $notification_stmt->bind_param(
                            'ssssi',
                            $recipient_role,
                            $notification_title,
                            $notification_message,
                            $notification_type,
                            $notification_related_id
                        );

                        if (!$notification_stmt->execute()) {
                            $notifications_sent = false;
                        }
                    }

                    $notification_stmt->close();
                    if (!$notifications_sent) {
                        $message = 'Announcement saved, but one or more notifications could not be sent.';
                    }
                } else {
                    $message = 'Announcement saved, but notifications could not be sent.';
                }

                $message = $message === '' ? 'Announcement published successfully.' : $message;
                $message_type = 'success';
            } else {
                $message = 'Unable to publish the announcement. Please try again.';
                $message_type = 'error';
            }

            $stmt->close();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    $delete_id = (int)($_POST['announcement_id'] ?? 0);

    if ($delete_id <= 0) {
        $message = 'Invalid announcement selected for deletion.';
        $message_type = 'error';
    } else {
        $delete_stmt = $conn->prepare('DELETE FROM announcements WHERE id = ?');

        if ($delete_stmt) {
            $delete_stmt->bind_param('i', $delete_id);
            if ($delete_stmt->execute()) {
                $notification_delete_stmt = $conn->prepare("DELETE FROM notifications WHERE notification_type = 'announcement' AND related_id = ?");
                if ($notification_delete_stmt) {
                    $notification_delete_stmt->bind_param('i', $delete_id);
                    $notification_delete_stmt->execute();
                    $notification_delete_stmt->close();
                }
                $message = 'Announcement deleted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Unable to delete the announcement.';
                $message_type = 'error';
            }
            $delete_stmt->close();
        } else {
            $message = 'Unable to delete the announcement from the database.';
            $message_type = 'error';
        }
    }
}

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function format_notice_date($value)
{
    if (empty($value)) {
        return 'Recently';
    }

    $timestamp = strtotime((string)$value);
    if ($timestamp === false) {
        return e($value);
    }

    return date('d M Y', $timestamp);
}

$filter_audience = trim((string)($_GET['audience'] ?? 'all'));
if (!in_array($filter_audience, ['all', 'Students', 'Managers', 'Everyone'], true)) {
    $filter_audience = 'all';
}

$announcements = [];
$announcements_sql = 'SELECT id, title, audience, content, author, created_at FROM announcements';

if ($filter_audience !== 'all') {
    $announcements_sql .= ' WHERE audience = ?';
}

$announcements_sql .= ' ORDER BY created_at DESC';

$stmt = $conn->prepare($announcements_sql);
if ($stmt) {
    if ($filter_audience !== 'all') {
        $filter_value = $filter_audience;
        $stmt->bind_param('s', $filter_value);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
    $stmt->close();
} else {
    $message = 'Unable to load announcements from the database.';
    $message_type = 'error';
}

$announcement_count = count($announcements);
$recent_audience = 'Students';
if ($announcement_count > 0 && !empty($announcements[0]['audience'])) {
    $recent_audience = $announcements[0]['audience'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements | SmartMess Admin</title>
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
        button, input, select, textarea { font: inherit; }

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

        .content-grid {
            display: grid;
            grid-template-columns: minmax(310px, 420px) minmax(0, 1fr);
            gap: 22px;
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

        .header-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(160px, 1fr));
            gap: 14px;
            margin: 0 0 18px;
        }

        .stat-box {
            background: linear-gradient(135deg, #ffffff, #f4f9ff);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 15px 16px;
            box-shadow: var(--shadow);
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
            font-weight: 700;
        }

        .stat-value {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: var(--navy);
        }

        .filter-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
            padding: 0 2px;
        }

        .filter-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-wrap label {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
        }

        .filter-select {
            min-width: 180px;
            padding: 9px 12px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--text);
            font-size: 13px;
        }

        .filter-button,
        .filter-reset,
        .delete-button {
            border: none;
            border-radius: 10px;
            padding: 9px 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
        }

        .filter-button {
            background: var(--navy);
            color: #fff;
        }

        .filter-reset {
            background: #edf2ff;
            color: var(--navy);
        }

        .delete-button {
            background: #fee2e2;
            color: #991b1b;
            padding: 8px 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #4a5568;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--line);
            border-radius: 12px;
            font-size: 13px;
            background: #f8fafc;
            color: var(--text);
        }

        .form-control:focus {
            border-color: var(--sky);
            outline: none;
            background: #fff;
        }

        textarea.form-control {
            min-height: 130px;
            resize: vertical;
        }

        .btn-submit {
            width: 100%;
            background: var(--navy);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 13px;
            padding: 12px 16px;
            cursor: pointer;
        }

        .btn-submit:hover {
            background: var(--deep-navy);
        }

        .alert-box {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 13px;
            border-left: 4px solid var(--sky);
            background: var(--sky-soft);
            color: #0f172a;
        }

        .alert-box.error {
            background: #fff1f2;
            border-left-color: #ef4444;
            color: #7f1d1d;
        }

        .announcement-card {
            background: #f8fafc;
            border-left: 4px solid var(--sky);
            padding: 16px 18px;
            border-radius: 12px;
            margin-bottom: 14px;
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 12px;
            margin-bottom: 10px;
        }

        .announcement-meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .announcement-header h4 {
            font-size: 15px;
            color: #1f2937;
        }

        .announcement-tag {
            background: #ecfeff;
            color: #0f766e;
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .announcement-card p {
            color: #475569;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .announcement-meta {
            font-size: 11px;
            color: #94a3b8;
        }

        .empty-state {
            background: #f8fafc;
            border: 1px dashed var(--line);
            color: var(--muted);
            font-size: 14px;
            border-radius: 12px;
            padding: 18px;
            text-align: center;
        }

        @media (max-width: 900px) {
            .app-window {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-width: 100%;
            }

            .nav-list {
                padding: 0 14px 18px;
            }

            .main-workspace {
                padding: 20px 18px 28px;
            }

            .content-grid {
                grid-template-columns: 1fr;
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
                    <a href="/smartmess/admin/complaints.php" class="nav-link"><i class="fa-solid fa-comment-dots"></i>Feedback</a>
                    <a href="/smartmess/admin/announcements.php" class="nav-link active"><i class="fa-solid fa-bullhorn"></i>Announcements</a>
                    <a href="/smartmess/admin/reports.php" class="nav-link"><i class="fa-solid fa-chart-pie"></i>Reports</a>
                </nav>
            </div>

            <a href="/smartmess/logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i>Log Out</a>
        </aside>

        <main class="main-workspace">
            <div class="page-header">
                <h2>Broadcast Announcements</h2>
                <p>Post updates and notices for students and staff.</p>
            </div>

            <div class="header-stats">
                <div class="stat-box">
                    <div class="stat-label">Total Notices</div>
                    <div class="stat-value"><?php echo (int)$announcement_count; ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Latest Audience</div>
                    <div class="stat-value"><?php echo e($recent_audience); ?></div>
                </div>
            </div>

            <div class="content-grid">
                <div class="card-panel">
                    <div class="card-title">
                        <i class="fa-solid fa-bullhorn" style="color: #3b52a0;"></i>
                        Post New Notice
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert-box <?php echo ($message_type === 'error') ? 'error' : ''; ?>">
                            <?php echo e($message); ?>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="form-group">
                            <label>Notice Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Special Feast on Sunday" required>
                        </div>

                        <div class="form-group">
                            <label>Audience</label>
                            <select name="audience" class="form-control" required>
                                <option value="Students">Students</option>
                                <option value="Managers">Managers</option>
                                <option value="Everyone">Everyone</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Content / Description</label>
                            <textarea name="content" class="form-control" placeholder="Write notice details here..." required></textarea>
                        </div>

                        <button type="submit" name="post_announcement" class="btn-submit">
                            <i class="fa-solid fa-paper-plane"></i>
                            Publish Notice
                        </button>
                    </form>
                </div>

                <div class="card-panel">
                    <div class="card-title">
                        <i class="fa-solid fa-list" style="color: #3b52a0;"></i>
                        Published Notices
                    </div>

                    <div class="filter-row">
                        <div class="filter-wrap">
                            <label for="audienceFilter">Filter</label>
                            <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                                <select id="audienceFilter" name="audience" class="filter-select">
                                    <option value="all" <?php echo ($filter_audience === 'all') ? 'selected' : ''; ?>>All audiences</option>
                                    <option value="Students" <?php echo ($filter_audience === 'Students') ? 'selected' : ''; ?>>Students</option>
                                    <option value="Managers" <?php echo ($filter_audience === 'Managers') ? 'selected' : ''; ?>>Managers</option>
                                    <option value="Everyone" <?php echo ($filter_audience === 'Everyone') ? 'selected' : ''; ?>>Everyone</option>
                                </select>
                                <button type="submit" class="filter-button">Apply</button>
                                <?php if ($filter_audience !== 'all'): ?>
                                    <a href="/smartmess/admin/announcements.php" class="filter-reset" style="text-decoration:none; display:inline-block;">Reset</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <?php if (empty($announcements)): ?>
                        <div class="empty-state">No announcements published yet for this audience.</div>
                    <?php else: ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="announcement-card">
                                <div class="announcement-header">
                                    <h4><?php echo e($announcement['title'] ?? 'Untitled Announcement'); ?></h4>
                                    <span class="announcement-tag"><?php echo e($announcement['audience'] ?? 'Students'); ?></span>
                                </div>
                                <p><?php echo nl2br(e($announcement['content'] ?? 'No content provided.')); ?></p>
                                <div class="announcement-meta-row">
                                    <div class="announcement-meta">
                                        By <?php echo e($announcement['author'] ?? 'Admin'); ?> • <?php echo format_notice_date($announcement['created_at'] ?? ''); ?>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Delete this announcement?');">
                                        <input type="hidden" name="announcement_id" value="<?php echo (int)($announcement['id'] ?? 0); ?>">
                                        <button type="submit" name="delete_announcement" class="delete-button">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
