<?php
session_start();

date_default_timezone_set('Asia/Kolkata');

// Ensure the user is logged in as manager
if (
    empty($_SESSION['logged_in']) ||
    ($_SESSION['role'] ?? '') !== 'manager'
) {
    header("Location: /smartmess/login.php");
    exit;
}

// Database connection
require_once __DIR__ . '/../db_config.php';

$message = "";
$message_type = "";

// Publish a new announcement from the manager.
// Managers can publish announcements for students only.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['post_announcement'])
) {
    $title = trim($_POST['title'] ?? '');
    $audience = trim($_POST['audience'] ?? 'Students');
    $content = trim($_POST['content'] ?? '');

    if ($title === '' || $content === '') {
        $message = "Please fill in all required fields.";
        $message_type = "error";
    } elseif ($audience !== 'Students') {
        $message = "Managers can publish announcements for students only.";
        $message_type = "error";
    } else {
        $author = trim($_SESSION['name'] ?? 'Mess Manager');

        if ($author === '') {
            $author = 'Mess Manager';
        }

        $stmt = $conn->prepare(
            "INSERT INTO announcements
                (title, audience, content, author)
             VALUES (?, ?, ?, ?)"
        );

        if ($stmt) {
            $stmt->bind_param(
                "ssss",
                $title,
                $audience,
                $content,
                $author
            );

            if ($stmt->execute()) {
                $announcement_id = (int)$conn->insert_id;

                // Notify students about the manager's announcement.
                $notification_stmt = $conn->prepare(
                    "INSERT INTO notifications
                        (recipient_role, recipient_id, title, message,
                         notification_type, related_id)
                     VALUES ('student', NULL, ?, ?, 'announcement', ?)"
                );

                $notifications_sent = false;

                if ($notification_stmt) {
                    $notification_title = $title;
                    $notification_message =
                        "New mess announcement: " . $title;

                    $notification_stmt->bind_param(
                        "ssi",
                        $notification_title,
                        $notification_message,
                        $announcement_id
                    );

                    $notifications_sent = $notification_stmt->execute();
                    $notification_stmt->close();
                }

                if ($notifications_sent) {
                    $message = "Announcement published successfully!";
                } else {
                    $message =
                        "Announcement published, but the student notification could not be sent.";
                }

                $message_type = "success";

                // Clear form values after successful submission.
                $_POST = [];
            } else {
                $message = "Unable to publish the announcement. Please try again.";
                $message_type = "error";
            }

            $stmt->close();
        } else {
            $message = "Unable to prepare the database query.";
            $message_type = "error";
        }
    }
}

// Fetch announcements visible to managers.
//
// FIX: Include 'Managers' so announcements published by the admin
// for managers are displayed here. 'Everyone' announcements are
// also visible to managers.
//
// Legacy student announcement audience values are retained.
$announcements = [];

$list_sql = "
    SELECT id, title, audience, content, author, created_at
    FROM announcements
    WHERE audience IN (
        'Managers',
        'Everyone',
        'Manager',
        'All Managers',
        'Students',
        'All Students',
        'Hostel A',
        'Hostel B'
    )
    ORDER BY created_at DESC
";

$list_result = $conn->query($list_sql);

if ($list_result) {
    while ($row = $list_result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

// Safe HTML output helper
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Announcements | Manager Portal</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont,
                         Roboto, sans-serif;
        }

        body {
            background-color: #2c3e50;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .app-window {
            background-color: #f0f2f8;
            width: 100%;
            max-width: 1250px;
            min-height: 750px;
            border-radius: 28px;
            display: flex;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
        }

        .sidebar {
            width: 230px;
            background-color: #1e2b37;
            padding: 30px 0 25px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #ffffff;
        }

        .main-workspace {
            flex: 1;
            min-width: 0;
            padding: 30px;
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h2 {
            font-size: 24px;
            color: #1a202c;
            font-weight: 700;
        }

        .page-header p {
            font-size: 13px;
            color: #718096;
            margin-top: 5px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 380px minmax(0, 1fr);
            gap: 25px;
            align-items: start;
        }

        .card-panel {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
            min-width: 0;
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 13px;
            outline: none;
            background: #f8fafc;
        }

        .form-control:focus {
            border-color: #2c3e50;
            background: #ffffff;
        }

        textarea.form-control {
            height: 100px;
            resize: vertical;
        }

        .btn-submit {
            width: 100%;
            background-color: #2c3e50;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background-color: #1e2b37;
        }

        .alert-box {
            padding: 10px 14px;
            font-size: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .alert-success {
            background: #e6fffa;
            border-left: 4px solid #319795;
            color: #234e52;
        }

        .alert-error {
            background: #fff5f5;
            border-left: 4px solid #e53e3e;
            color: #9b2c2c;
        }

        .notice-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .notice-card {
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 14px;
            padding: 16px;
            position: relative;
        }

        .notice-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .notice-tag {
            background: #ebf8ff;
            color: #2b6cb0;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .notice-date {
            font-size: 11px;
            color: #718096;
            font-weight: 600;
        }

        .notice-title {
            font-size: 14px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 6px;
            overflow-wrap: anywhere;
        }

        .notice-body {
            font-size: 12px;
            color: #718096;
            line-height: 1.6;
            overflow-wrap: anywhere;
            white-space: normal;
        }

        .notice-author {
            font-size: 11px;
            color: #718096;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #edf2f7;
        }

        .empty-state {
            text-align: center;
            padding: 35px 15px;
            color: #718096;
            font-size: 13px;
            line-height: 1.6;
        }

        .empty-state i {
            display: block;
            font-size: 28px;
            color: #a0aec0;
            margin-bottom: 12px;
        }

        @media (max-width: 1000px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 650px) {
            body {
                padding: 0;
                display: block;
            }

            .app-window {
                min-height: 100vh;
                border-radius: 0;
            }

            .main-workspace {
                padding: 22px 16px;
            }

            .page-header h2 {
                font-size: 21px;
            }

            .card-panel {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<div class="app-window">

    <?php
    $manager_sidebar_theme = 'legacy';
    include __DIR__ . '/../includes/manager_sidebar.php';
    ?>

    <main class="main-workspace">

        <div class="page-header">
            <h2>Mess Notices &amp; Announcements</h2>
            <p>
                View important mess updates and publish announcements for students.
            </p>
        </div>

        <div class="content-grid">

            <!-- Publish announcement panel -->
            <div class="card-panel">

                <div class="card-title">
                    <i class="fa-solid fa-bullhorn" style="color: #2c3e50;"></i>
                    Post New Notice
                </div>

                <?php if ($message !== ''): ?>
                    <div class="alert-box <?php
                        echo $message_type === 'error'
                            ? 'alert-error'
                            : 'alert-success';
                    ?>">
                        <?php echo e($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">

                    <div class="form-group">
                        <label for="title">Notice Title</label>

                        <input
                            type="text"
                            id="title"
                            name="title"
                            class="form-control"
                            placeholder="e.g. Revised Dinner Timings"
                            maxlength="255"
                            value="<?php echo e($_POST['title'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="audience">Target Audience</label>

                        <!-- Managers publish announcements for students only -->
                        <select
                            id="audience"
                            name="audience"
                            class="form-control"
                            required
                        >
                            <option value="Students" selected>
                                Students
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="content">Message / Details</label>

                        <textarea
                            id="content"
                            name="content"
                            class="form-control"
                            placeholder="Write announcement details here..."
                            required
                        ><?php echo e($_POST['content'] ?? ''); ?></textarea>
                    </div>

                    <button
                        type="submit"
                        name="post_announcement"
                        value="1"
                        class="btn-submit"
                    >
                        <i class="fa-solid fa-bullhorn"></i>
                        Broadcast Notice
                    </button>

                </form>
            </div>

            <!-- Published announcements panel -->
            <div class="card-panel">

                <div class="card-title">
                    <i class="fa-solid fa-clock-rotate-left"
                       style="color: #2c3e50;"></i>
                    Published Notices
                    (<?php echo count($announcements); ?>)
                </div>

                <div class="notice-list">

                    <?php if (!empty($announcements)): ?>

                        <?php foreach ($announcements as $notice): ?>

                            <div class="notice-card">

                                <div class="notice-meta">

                                    <span class="notice-tag">
                                        <?php echo e($notice['audience'] ?? 'Students'); ?>
                                    </span>

                                    <span class="notice-date">
                                        <?php
                                        $created_at = $notice['created_at'] ?? '';

                                        if ($created_at !== '') {
                                            echo e(date(
                                                'd M Y, h:i A',
                                                strtotime($created_at)
                                            ));
                                        } else {
                                            echo 'Date unavailable';
                                        }
                                        ?>
                                    </span>

                                </div>

                                <div class="notice-title">
                                    <?php echo e($notice['title'] ?? 'Untitled Announcement'); ?>
                                </div>

                                <div class="notice-body">
                                    <?php
                                    echo nl2br(
                                        e($notice['content'] ?? '')
                                    );
                                    ?>
                                </div>

                                <div class="notice-author">
                                    Published by:
                                    <?php echo e($notice['author'] ?? 'Admin'); ?>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="empty-state">
                            <i class="fa-regular fa-bell"></i>
                            <p>No announcements are available yet.</p>
                        </div>

                    <?php endif; ?>

                </div>
            </div>

        </div>
    </main>
</div>

</body>
</html>