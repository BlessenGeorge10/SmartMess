```php
<?php
session_start();

// Ensure user is logged in as a student
if (
    !isset($_SESSION['logged_in']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'student'
) {
    header("Location: /smartmess/login.php");
    exit;
}

// Connect to the database
require_once "../db_config.php";

// ======================================
// MARK STUDENT NOTIFICATIONS AS READ
// ======================================

$student_id = (int)($_SESSION['user_id'] ?? 0);

if ($student_id > 0) {
    $read_stmt = $conn->prepare(
        "UPDATE notifications
         SET is_read = 1
         WHERE recipient_role = 'student'
           AND is_read = 0
           AND (recipient_id IS NULL OR recipient_id = ?)"
    );

    if ($read_stmt) {
        $read_stmt->bind_param("i", $student_id);
        $read_stmt->execute();
        $read_stmt->close();
    }
}

// ======================================
// FETCH ANNOUNCEMENTS FROM DATABASE
// ======================================

$announcements = [];

$sql = "SELECT id, title, audience, content, author, created_at
        FROM announcements
        WHERE audience IN ('Students', 'Everyone', 'All Students', 'Hostel A', 'Hostel B')
        ORDER BY created_at DESC";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {

        // Convert database fields for the existing design
        $row['category'] = $row['audience'];

        $row['date'] = date(
            'M d, Y',
            strtotime($row['created_at'])
        );

        $row['time'] = date(
            'h:i A',
            strtotime($row['created_at'])
        );

        // Default category badge colours
        $row['badge_bg'] = '#cbe3f7';
        $row['badge_color'] = '#3a5ba0';

        // Special Menu badge
        if (strtolower($row['audience']) === 'special menu') {
            $row['badge_bg'] = '#dcfce7';
            $row['badge_color'] = '#15803d';
        }
        // Maintenance badge
        elseif (strtolower($row['audience']) === 'maintenance') {
            $row['badge_bg'] = '#fef3c7';
            $row['badge_color'] = '#b45309';
        }

        $announcements[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Announcements - Student | SmartMess</title>

    <!-- FontAwesome Icons -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
        }

        body {
            background-color: #dce7f0;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* =========================
           APP WINDOW
        ========================= */

        .app-window {
            background-color: #dce7f0;
            width: 100%;
            min-height: 100vh;
            display: block;
            overflow: hidden;
        }

        /* =========================
           MAIN WORKSPACE
        ========================= */

        .main-workspace {
            margin-left: 250px;
            width: calc(100% - 250px);
            min-height: 100vh;
            padding: 28px 35px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            overflow-y: auto;
        }

        /* =========================
           PAGE TITLE BANNER
        ========================= */

        .page-title-banner {
            background: #eef5fb;
            padding: 24px 28px;
            border-radius: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        }

        .page-title-banner h2 {
            font-size: 22px;
            color: #1e2b37;
            font-weight: 800;
        }

        .page-title-banner p {
            font-size: 13px;
            color: #5a6e85;
            margin-top: 4px;
        }

        .page-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge-count {
            background: #cbe3f7;
            color: #3a5ba0;
            padding: 8px 16px;
            border-radius: 14px;
            font-size: 12px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dashboard-btn {
            background: #3a5ba0;
            color: #ffffff;
            text-decoration: none;
            padding: 9px 15px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: background 0.2s ease;
        }

        .dashboard-btn:hover {
            background: #2d4880;
        }

        /* =========================
           ANNOUNCEMENTS
        ========================= */

        .announcements-feed {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .announcement-card {
            background: #eef5fb;
            border-radius: 22px;
            padding: 22px 26px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
            gap: 12px;
            border-left: 5px solid #3a5ba0;
        }

        .card-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .category-badge {
            font-size: 11px;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .post-time {
            font-size: 12px;
            color: #5a6e85;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .announcement-title {
            font-size: 18px;
            color: #1e2b37;
            font-weight: 800;
        }

        .announcement-body {
            font-size: 13px;
            color: #4a5d73;
            line-height: 1.6;
            font-weight: 500;
            overflow-wrap: anywhere;
        }

        .card-footer-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            border-top: 1px solid rgba(58, 91, 160, 0.1);
            padding-top: 12px;
            font-size: 12px;
            color: #5a6e85;
            font-weight: 700;
        }

        .card-footer-meta i {
            color: #3a5ba0;
        }

        /* Empty state */
        .empty-state {
            background: #eef5fb;
            border-radius: 22px;
            padding: 45px 25px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        }

        .empty-state i {
            font-size: 35px;
            color: #3a5ba0;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            font-size: 18px;
            color: #1e2b37;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 13px;
            color: #5a6e85;
            line-height: 1.6;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 900px) {
            .main-workspace {
                margin-left: 220px;
                width: calc(100% - 220px);
                padding: 22px 20px;
            }

            .page-title-banner {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .page-header-actions {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }

            .card-header-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }

        @media (max-width: 600px) {
            .main-workspace {
                margin-left: 0;
                width: 100%;
                padding: 18px 14px;
            }

            .page-title-banner {
                padding: 20px;
            }

            .announcement-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<div class="app-window">

    <!-- COMMON STUDENT SIDEBAR -->
    <?php include "../includes/student_sidebar.php"; ?>

    <!-- MAIN WORKSPACE -->
    <main class="main-workspace">

        <!-- PAGE TITLE BANNER -->
        <div class="page-title-banner">

            <div>
                <h2>Announcements</h2>
                <p>
                    Stay updated with the latest mess notices and announcements.
                </p>
            </div>

            <div class="page-header-actions">

                <span class="badge-count">
                    <i class="fa-solid fa-bullhorn"></i>
                    <?php echo count($announcements); ?> Active Notices
                </span>

                <a href="dashboard.php" class="dashboard-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    Dashboard
                </a>

            </div>
        </div>

        <!-- ANNOUNCEMENTS FEED -->
        <div class="announcements-feed">

            <?php if (!empty($announcements)): ?>

                <?php foreach ($announcements as $item): ?>

                    <div class="announcement-card">

                        <div class="card-header-top">

                            <span
                                class="category-badge"
                                style="
                                    background-color: <?php echo htmlspecialchars($item['badge_bg']); ?>;
                                    color: <?php echo htmlspecialchars($item['badge_color']); ?>;
                                "
                            >
                                <?php echo htmlspecialchars($item['category']); ?>
                            </span>

                            <span class="post-time">
                                <i class="fa-regular fa-clock"></i>
                                <?php echo htmlspecialchars($item['date']); ?>
                                at
                                <?php echo htmlspecialchars($item['time']); ?>
                            </span>

                        </div>

                        <h3 class="announcement-title">
                            <?php echo htmlspecialchars($item['title']); ?>
                        </h3>

                        <p class="announcement-body">
                            <?php echo nl2br(htmlspecialchars($item['content'])); ?>
                        </p>

                        <div class="card-footer-meta">
                            <i class="fa-solid fa-user-shield"></i>
                            <span>
                                Posted by
                                <?php echo htmlspecialchars($item['author']); ?>
                            </span>
                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-state">
                    <i class="fa-solid fa-bullhorn"></i>
                    <h3>No announcements yet</h3>
                    <p>
                        New mess notices will appear here when the manager publishes them.
                    </p>
                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

</body>
</html>
```