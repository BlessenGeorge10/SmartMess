
<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

if (
    empty($_SESSION['logged_in']) ||
    strtolower($_SESSION['role'] ?? '') !== 'manager'
) {
    header('Location: /smartmess/login.php');
    exit;
}

require_once __DIR__ . '/../db_config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is unavailable. Check db_config.php.');
}

$conn->set_charset('utf8mb4');

$manager_id = (int)($_SESSION['user_id'] ?? 0);
$today = date('l');

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['manager_notification_token'])) {
    $_SESSION['manager_notification_token'] = bin2hex(random_bytes(32));
}

/*
 * Mark notification as read and redirect to its correct page.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['mark_notification_read'])
) {
    $posted_token = (string)($_POST['csrf_token'] ?? '');
    $session_token = (string)($_SESSION['manager_notification_token'] ?? '');
    $notification_id = (int)($_POST['notification_id'] ?? 0);

    if (
        $posted_token !== '' &&
        $session_token !== '' &&
        hash_equals($session_token, $posted_token) &&
        $notification_id > 0
    ) {
        $type_stmt = $conn->prepare(
            "SELECT notification_type
             FROM notifications
             WHERE id = ?
               AND recipient_role = 'manager'
               AND (recipient_id IS NULL OR recipient_id = ?)
             LIMIT 1"
        );

        $notification_type = '';

        if ($type_stmt) {
            $type_stmt->bind_param('ii', $notification_id, $manager_id);
            $type_stmt->execute();
            $type_result = $type_stmt->get_result();
            $type_row = $type_result->fetch_assoc();

            if ($type_row) {
                $notification_type = (string)$type_row['notification_type'];
            }

            $type_stmt->close();
        }

        if ($notification_type !== '') {
            $mark = $conn->prepare(
                "UPDATE notifications
                 SET is_read = 1
                 WHERE id = ?
                   AND recipient_role = 'manager'
                   AND (recipient_id IS NULL OR recipient_id = ?)"
            );

            if ($mark) {
                $mark->bind_param('ii', $notification_id, $manager_id);
                $mark->execute();
                $mark->close();
            }

            /*
             * IMPORTANT:
             * The correct meal-skip page is manager/mealskip.php.
             */
            if ($notification_type === 'meal_skip') {
                header('Location: /smartmess/manager/mealskip.php');
                exit;
            }

            if ($notification_type === 'announcement') {
                header('Location: /smartmess/manager/announcements.php');
                exit;
            }

            header('Location: /smartmess/manager/dashboard.php');
            exit;
        }
    }

    $_SESSION['manager_notification_error'] =
        'Unable to open this notification. Please try again.';

    header('Location: /smartmess/manager/dashboard.php');
    exit;
}

$notification_error = $_SESSION['manager_notification_error'] ?? '';
unset($_SESSION['manager_notification_error']);

/* Dashboard values */
$student_count = 0;
$avg_rating = 0.0;
$meal_rating_count = 0;
$total_requests = 0;
$breakfast_count = 0;
$lunch_count = 0;
$dinner_count = 0;
$menu_rows = [];

$unread_notification_count = 0;
$notifications = [];

/* Total students */
$student_query = $conn->query(
    "SELECT COUNT(*) AS total_students
     FROM users
     WHERE LOWER(role) = 'student'"
);

if ($student_query) {
    $row = $student_query->fetch_assoc();
    $student_count = (int)($row['total_students'] ?? 0);
}

/* Individual student food-item ratings */
$rating_query = $conn->query(
    "SELECT AVG(rating) AS avg_rating,
            COUNT(*) AS rating_count
     FROM food_item_ratings"
);

if ($rating_query) {
    $row = $rating_query->fetch_assoc();
    $avg_rating = (float)($row['avg_rating'] ?? 0);
    $meal_rating_count = (int)($row['rating_count'] ?? 0);
}

/* Active meal skips */
$skip_query = $conn->query(
    "SELECT COUNT(*) AS total_requests,
            COALESCE(SUM(breakfast), 0) AS breakfast_count,
            COALESCE(SUM(lunch), 0) AS lunch_count,
            COALESCE(SUM(dinner), 0) AS dinner_count
     FROM meal_skips
     WHERE LOWER(status) = 'active'"
);

if ($skip_query) {
    $row = $skip_query->fetch_assoc();

    $total_requests = (int)($row['total_requests'] ?? 0);
    $breakfast_count = (int)($row['breakfast_count'] ?? 0);
    $lunch_count = (int)($row['lunch_count'] ?? 0);
    $dinner_count = (int)($row['dinner_count'] ?? 0);
}

/* Today's meal schedule */
$schedule_query = $conn->prepare(
    "SELECT meal_type, meal_time, menu_items
     FROM meal_schedule
     WHERE day_of_week = ?
     ORDER BY FIELD(
         meal_type,
         'Breakfast',
         'Evening Snack',
         'Lunch',
         'Dinner'
     )"
);

if ($schedule_query) {
    $schedule_query->bind_param('s', $today);
    $schedule_query->execute();

    $result = $schedule_query->get_result();

    while ($row = $result->fetch_assoc()) {
        $menu_rows[] = $row;
    }

    $schedule_query->close();
}

/* Unread notification count */
$unread_query = $conn->prepare(
    "SELECT COUNT(*) AS unread_count
     FROM notifications
     WHERE recipient_role = 'manager'
       AND (recipient_id IS NULL OR recipient_id = ?)
       AND is_read = 0"
);

if ($unread_query) {
    $unread_query->bind_param('i', $manager_id);
    $unread_query->execute();

    $unread_result = $unread_query->get_result();
    $unread_row = $unread_result->fetch_assoc();

    $unread_notification_count =
        (int)($unread_row['unread_count'] ?? 0);

    $unread_query->close();
}

/* Latest 15 notifications */
$notification_query = $conn->prepare(
    "SELECT id, title, message, notification_type,
            related_id, is_read, created_at
     FROM notifications
     WHERE recipient_role = 'manager'
       AND (recipient_id IS NULL OR recipient_id = ?)
     ORDER BY created_at DESC, id DESC
     LIMIT 15"
);

if ($notification_query) {
    $notification_query->bind_param('i', $manager_id);
    $notification_query->execute();

    $notification_result = $notification_query->get_result();

    while ($row = $notification_result->fetch_assoc()) {
        $notifications[] = $row;
    }

    $notification_query->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | Manager Portal</title>

<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', Arial, sans-serif;
}

body {
    background: #2c3e50;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    color: #2d3748;
}

.app-window {
    background: #f0f2f8;
    width: 100%;
    max-width: 1250px;
    min-height: 750px;
    border-radius: 28px;
    display: flex;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0,0,0,.25);
}

.sidebar {
    width: 230px;
    min-width: 230px;
    background: #1e2b37;
    padding: 30px 0 25px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    color: white;
}

.brand-logo {
    padding: 0 25px 35px;
    font-size: 22px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
    color: white;
}

.nav-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 25px;
    color: #b8c7ce;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}

.nav-link i {
    width: 18px;
    text-align: center;
}

.nav-link:hover { color: white; }

.nav-link.active {
    background: #f0f2f8;
    color: #1e2b37;
    font-weight: 700;
    border-radius: 20px 0 0 20px;
    margin-left: 15px;
    padding-left: 20px;
}

.logout-link {
    padding: 0 25px;
    color: #e74c3c;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.logout-link:hover { color: white; }

.main-workspace {
    flex: 1;
    min-width: 0;
    padding: 30px;
    background: #f0f2f8;
    overflow-y: auto;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

.page-header h2 {
    font-size: 24px;
    color: #1a202c;
}

.page-header p {
    font-size: 13px;
    color: #718096;
    margin-top: 4px;
    line-height: 1.5;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.shift-badge, .user-profile {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 13px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.shift-badge {
    background: #e5edf7;
    color: #2c3e50;
}

.user-profile {
    background: white;
    color: #344054;
}

.notification-wrap { position: relative; }

.notification-bell {
    width: 42px;
    height: 42px;
    border: 1px solid #e1e8f0;
    border-radius: 12px;
    background: white;
    color: #344054;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    cursor: pointer;
    position: relative;
}

.notification-bell:hover,
.notification-bell[aria-expanded="true"] {
    background: #e8f2ff;
}

.notification-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    min-width: 19px;
    height: 19px;
    padding: 0 5px;
    border-radius: 20px;
    background: #e74c3c;
    color: white;
    border: 2px solid #f0f2f8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 800;
}

.notification-badge[hidden],
.notification-dropdown[hidden] {
    display: none;
}

.notification-dropdown {
    position: absolute;
    top: 52px;
    right: 0;
    width: 350px;
    max-width: calc(100vw - 35px);
    background: white;
    border: 1px solid #e4eaf2;
    border-radius: 16px;
    box-shadow: 0 18px 45px rgba(25,42,66,.18);
    z-index: 1000;
    overflow: hidden;
}

.notification-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 16px;
    border-bottom: 1px solid #edf1f6;
}

.notification-heading h3 {
    font-size: 14px;
    color: #24364d;
}

.notification-heading span {
    font-size: 10px;
    color: #6b7d93;
    background: #edf3fa;
    border-radius: 20px;
    padding: 5px 8px;
    font-weight: 800;
}

.notification-list {
    max-height: 360px;
    overflow-y: auto;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    padding: 14px;
    border-bottom: 1px solid #edf1f6;
}

.notification-item.unread { background: #f0f7ff; }

.notification-icon {
    width: 36px;
    height: 36px;
    min-width: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e5efff;
    color: #4678bb;
    border-radius: 11px;
}

.notification-item-content { flex: 1; min-width: 0; }

.notification-item-content h4 {
    font-size: 12px;
    color: #263b56;
    margin-bottom: 5px;
    overflow-wrap: anywhere;
}

.notification-item-content p {
    font-size: 11px;
    line-height: 1.6;
    color: #667991;
    overflow-wrap: anywhere;
}

.notification-time {
    display: block;
    font-size: 10px;
    color: #91a0b2;
    margin-top: 7px;
}

.notification-open {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 9px;
    padding: 7px 10px;
    border: 0;
    border-radius: 8px;
    background: #2c3e50;
    color: white;
    font-size: 10px;
    font-weight: 750;
    cursor: pointer;
}

.notification-open:hover { background: #42668c; }

.notification-empty {
    text-align: center;
    padding: 28px 16px;
    color: #7b8ba0;
    font-size: 12px;
    line-height: 1.8;
}

.notification-empty i {
    display: block;
    font-size: 25px;
    margin-bottom: 10px;
    color: #a7b8cc;
}

.notification-error {
    background: #fff0f0;
    color: #a12626;
    padding: 11px 14px;
    border-radius: 10px;
    font-size: 12px;
    margin-bottom: 16px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    background: white;
    border-radius: 20px;
    padding: 20px 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,.02);
}

.stat-icon {
    width: 42px;
    height: 42px;
    min-width: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 17px;
}

.blue { background: #e8f2ff; color: #4384d4; }
.green { background: #e4f8f1; color: #299b7c; }
.orange { background: #fff0db; color: #dc862b; }
.purple { background: #f2eaff; color: #8960d7; }

.stat-info { min-width: 0; }

.stat-info h3 {
    font-size: 21px;
    color: #202b3c;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.stat-info p {
    color: #8390a3;
    font-size: 10px;
    font-weight: 600;
    margin-top: 4px;
    line-height: 1.4;
}

.content-grid {
    display: grid;
    grid-template-columns: minmax(0,1.25fr) minmax(260px,.85fr);
    gap: 25px;
    align-items: start;
}

.card-panel {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 22px;
    box-shadow: 0 4px 12px rgba(0,0,0,.02);
    min-width: 0;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 18px;
}

.card-title {
    font-size: 16px;
    font-weight: 700;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 9px;
}

.card-title i { color: #2c3e50; }

.view-link {
    color: #5576a7;
    font-size: 11px;
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;
}

.view-link:hover {
    color: #1e2b37;
    text-decoration: underline;
}

.meal-list {
    display: flex;
    flex-direction: column;
    gap: 11px;
}

.meal-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 15px;
    background: #f8fafc;
    border-left: 4px solid #6b8cc3;
    border-radius: 12px;
}

.meal-item.breakfast { border-left-color: #e3a04b; }
.meal-item.lunch { border-left-color: #43a88a; }
.meal-item.dinner { border-left-color: #9270d8; }
.meal-item.snack { border-left-color: #e3a04b; }

.meal-info { min-width: 0; }

.meal-info h4 {
    font-size: 13px;
    color: #344054;
    margin-bottom: 5px;
}

.meal-info p {
    font-size: 12px;
    color: #718096;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.meal-time {
    flex-shrink: 0;
    padding: 6px 9px;
    border: 1px solid #e9edf4;
    border-radius: 8px;
    background: white;
    color: #77859a;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.empty-state {
    background: #f8fafc;
    border: 1px dashed #d8e1ed;
    border-radius: 12px;
    padding: 18px;
    color: #718096;
    font-size: 12px;
    line-height: 1.7;
}

.summary-list {
    display: flex;
    flex-direction: column;
    gap: 11px;
}

.summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 15px;
    background: #f8fafc;
    border-radius: 12px;
}

.summary-label {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.summary-label i {
    width: 18px;
    text-align: center;
    color: #6688bd;
}

.summary-label span {
    font-size: 12px;
    font-weight: 700;
    color: #475569;
}

.summary-count {
    background: #e5edf7;
    color: #2c3e50;
    padding: 6px 11px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 800;
}

.feedback-summary {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 16px;
    padding: 16px;
    border-radius: 14px;
    background: #f8fafc;
}

.feedback-icon {
    width: 43px;
    height: 43px;
    min-width: 43px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e8f2ff;
    color: #5689d0;
    border-radius: 12px;
    font-size: 18px;
}

.feedback-summary h4 {
    font-size: 14px;
    color: #344054;
    margin-bottom: 4px;
}

.feedback-summary p {
    font-size: 11px;
    color: #8490a2;
    line-height: 1.5;
}

.rating-display {
    text-align: center;
    padding: 22px 15px;
    background: #f8fafc;
    border-radius: 14px;
    margin-bottom: 16px;
}

.rating-display h3 {
    font-size: 32px;
    color: #2c3e50;
    margin-bottom: 8px;
}

.rating-display .stars {
    color: #e3a04b;
    font-size: 16px;
    margin-bottom: 7px;
}

.rating-display p {
    color: #8490a2;
    font-size: 11px;
}

.action-buttons {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 11px;
}

.btn-action {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 13px 10px;
    border: 1px solid #e4eaf2;
    border-radius: 12px;
    background: #f8fafc;
    color: #43536b;
    font-size: 11px;
    font-weight: 800;
    text-align: center;
    text-decoration: none;
    transition: all .2s ease;
}

.btn-action i { color: #6688bd; }

.btn-action:hover {
    background: #2c3e50;
    border-color: #2c3e50;
    color: white;
}

.btn-action:hover i { color: white; }

@media (max-width:1100px) {
    .main-workspace { padding: 25px; }
    .stats-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
    .content-grid { grid-template-columns: minmax(0,1fr); }
}

@media (max-width:720px) {
    body { padding: 8px; align-items: flex-start; }

    .app-window {
        flex-direction: column;
        border-radius: 20px;
        min-height: calc(100vh - 16px);
    }

    .sidebar {
        width: 100%;
        min-width: 0;
        padding: 20px 0;
    }

    .brand-logo { padding: 0 20px 20px; }

    .nav-list {
        display: grid;
        grid-template-columns: repeat(2,minmax(0,1fr));
        gap: 5px;
        padding: 0 10px;
    }

    .nav-link { padding: 11px; font-size: 12px; }

    .nav-link.active {
        margin-left: 0;
        padding-left: 11px;
        border-radius: 9px;
    }

    .logout-link { margin-top: 20px; }
    .main-workspace { padding: 20px; }

    .page-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .header-actions { flex-wrap: wrap; }
    .stats-grid { gap: 10px; }

    .stat-card {
        padding: 14px 10px;
        gap: 8px;
    }

    .stat-icon {
        width: 35px;
        height: 35px;
        min-width: 35px;
        font-size: 14px;
    }

    .stat-info h3 { font-size: 17px; }
    .card-panel { padding: 18px; }
    .meal-item { align-items: flex-start; }

    .meal-time {
        white-space: normal;
        text-align: center;
    }

    .notification-dropdown {
        right: auto;
        left: 0;
    }
}

@media (max-width:390px) {
    .stats-grid, .action-buttons {
        grid-template-columns: 1fr;
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

<header class="page-header">
    <div>
        <h2>Warden Dashboard</h2>
        <p>Overview of today's mess menu, meal skips, and student feedback.</p>
    </div>

    <div class="header-actions">

        <div class="shift-badge">
            <i class="fa-solid fa-calendar-day"></i>
            <?php echo e($today); ?>
        </div>

        <!-- Notification Bell -->
        <div class="notification-wrap">
            <button
                type="button"
                class="notification-bell"
                id="notificationBell"
                aria-label="Notifications"
                aria-expanded="false"
                aria-controls="notificationDropdown"
            >
                <i class="fa-solid fa-bell"></i>

                <span
                    class="notification-badge"
                    id="notificationBadge"
                    <?php echo $unread_notification_count === 0 ? 'hidden' : ''; ?>
                ><?php
                    echo $unread_notification_count > 99
                        ? '99+'
                        : $unread_notification_count;
                ?></span>
            </button>

            <div class="notification-dropdown" id="notificationDropdown" hidden>
                <div class="notification-heading">
                    <h3>Notifications</h3>
                    <span><?php echo $unread_notification_count; ?> unread</span>
                </div>

                <div class="notification-list">
                <?php if (empty($notifications)): ?>

                    <div class="notification-empty">
                        <i class="fa-regular fa-bell-slash"></i>
                        <strong>No notifications yet</strong>
                        <p>New announcements and meal-skip requests will appear here.</p>
                    </div>

                <?php else: ?>

                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        $type = (string)$notification['notification_type'];

                        if ($type === 'meal_skip') {
                            $icon = 'fa-calendar-minus';
                        } elseif ($type === 'announcement') {
                            $icon = 'fa-bullhorn';
                        } else {
                            $icon = 'fa-bell';
                        }

                        $is_unread = (int)$notification['is_read'] === 0;
                        $created = strtotime((string)$notification['created_at']);
                        ?>

                        <article class="notification-item <?php echo $is_unread ? 'unread' : ''; ?>">
                            <div class="notification-icon">
                                <i class="fa-solid <?php echo e($icon); ?>"></i>
                            </div>

                            <div class="notification-item-content">
                                <h4>
                                    <?php echo e($notification['title']); ?>

                                    <?php if ($is_unread): ?>
                                        <span style="color:#3478c9;font-size:10px;">• New</span>
                                    <?php endif; ?>
                                </h4>

                                <p><?php echo e($notification['message']); ?></p>

                                <span class="notification-time">
                                    <i class="fa-regular fa-clock"></i>
                                    <?php
                                    echo $created
                                        ? e(date('d M Y, h:i A', $created))
                                        : e($notification['created_at']);
                                    ?>
                                </span>

                                <form method="POST" action="/smartmess/manager/dashboard.php">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo e($_SESSION['manager_notification_token']); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="notification_id"
                                        value="<?php echo (int)$notification['id']; ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="mark_notification_read"
                                        value="1"
                                        class="notification-open"
                                    >
                                        <?php
                                        if ($type === 'meal_skip') {
                                            echo 'View Meal Skip';
                                        } elseif ($type === 'announcement') {
                                            echo 'View Announcement';
                                        } else {
                                            echo 'Open Notification';
                                        }
                                        ?>
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>

                <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="user-profile">
            <i class="fa-solid fa-user-gear"></i>
            Mess Manager
        </div>
    </div>
</header>

<?php if ($notification_error !== ''): ?>
    <div class="notification-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?php echo e($notification_error); ?>
    </div>
<?php endif; ?>

<!-- Statistics -->
<section class="stats-grid">

    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
        <div class="stat-info">
            <h3><?php echo $student_count; ?></h3>
            <p>Mess Students</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-star"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($avg_rating, 1); ?> / 5</h3>
            <p>Average Rating</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange"><i class="fa-solid fa-clock-rotate-left"></i></div>
        <div class="stat-info">
            <h3><?php echo $total_requests; ?></h3>
            <p>Active Meal Skips</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple"><i class="fa-solid fa-comment-dots"></i></div>
        <div class="stat-info">
            <h3><?php echo $meal_rating_count; ?></h3>
            <p>Ratings Submitted</p>
        </div>
    </div>

</section>

<section class="content-grid">
<div>

    <!-- Today's Schedule -->
    <div class="card-panel">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-utensils"></i>
                Today's Schedule
            </div>
            <a href="/smartmess/manager/menu.php" class="view-link">Edit Menu →</a>
        </div>

        <?php if (!empty($menu_rows)): ?>
            <div class="meal-list">
            <?php foreach ($menu_rows as $menu_item): ?>
                <?php
                $meal_type = strtolower($menu_item['meal_type'] ?? '');
                $class_name = 'breakfast';

                if ($meal_type === 'lunch') {
                    $class_name = 'lunch';
                } elseif ($meal_type === 'dinner') {
                    $class_name = 'dinner';
                } elseif ($meal_type === 'evening snack') {
                    $class_name = 'snack';
                }
                ?>

                <div class="meal-item <?php echo e($class_name); ?>">
                    <div class="meal-info">
                        <h4><?php echo e($menu_item['meal_type'] ?? 'Meal'); ?></h4>
                        <p><?php echo nl2br(e($menu_item['menu_items'] ?? 'No menu items set.')); ?></p>
                    </div>
                    <div class="meal-time"><?php echo e($menu_item['meal_time'] ?? ''); ?></div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                No menu items are currently stored for
                <strong><?php echo e($today); ?></strong>.
                Please update the meal schedule from the menu management page.
            </div>
        <?php endif; ?>
    </div>

    <!-- Meal Skip Summary -->
    <div class="card-panel">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-person-walking-arrow-right"></i>
                Meal Skip Summary
            </div>
            <a href="/smartmess/manager/mealskip.php" class="view-link">View All →</a>
        </div>

        <div class="summary-list">
            <div class="summary-row">
                <div class="summary-label">
                    <i class="fa-solid fa-users"></i>
                    <span>Active Requests</span>
                </div>
                <span class="summary-count"><?php echo $total_requests; ?></span>
            </div>

            <div class="summary-row">
                <div class="summary-label">
                    <i class="fa-solid fa-mug-hot"></i>
                    <span>Breakfast</span>
                </div>
                <span class="summary-count"><?php echo $breakfast_count; ?></span>
            </div>

            <div class="summary-row">
                <div class="summary-label">
                    <i class="fa-solid fa-bowl-food"></i>
                    <span>Lunch</span>
                </div>
                <span class="summary-count"><?php echo $lunch_count; ?></span>
            </div>

            <div class="summary-row">
                <div class="summary-label">
                    <i class="fa-solid fa-utensils"></i>
                    <span>Dinner</span>
                </div>
                <span class="summary-count"><?php echo $dinner_count; ?></span>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card-panel">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-bolt"></i>
                Quick Actions
            </div>
        </div>

        <div class="action-buttons">
            <a href="/smartmess/manager/menu.php" class="btn-action">
                <i class="fa-solid fa-pen-to-square"></i> Update Menu
            </a>

            <a href="/smartmess/manager/announcements.php" class="btn-action">
                <i class="fa-solid fa-bullhorn"></i> Post Notice
            </a>

            <a href="/smartmess/manager/mealskip.php" class="btn-action">
                <i class="fa-solid fa-person-walking-arrow-right"></i> Review Meal Skip
            </a>

            <a href="/smartmess/manager/reports.php" class="btn-action">
                <i class="fa-solid fa-chart-pie"></i> Generate Report
            </a>
        </div>
    </div>
</div>

<div>

    <!-- Rating Overview -->
    <div class="card-panel">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-star"></i>
                Student Feedback
            </div>
        </div>

        <div class="rating-display">
            <h3>
                <?php echo number_format($avg_rating, 1); ?>
                <span style="font-size:16px;">/ 5</span>
            </h3>

            <div class="stars">
                <?php
                $full_stars = (int)round($avg_rating);

                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $full_stars) {
                        echo '<i class="fa-solid fa-star"></i>';
                    } else {
                        echo '<i class="fa-regular fa-star"></i>';
                    }
                }
                ?>
            </div>

            <p>Average student meal rating</p>
        </div>

        <div class="feedback-summary">
            <div class="feedback-icon"><i class="fa-solid fa-comment-dots"></i></div>
            <div>
                <h4><?php echo $meal_rating_count; ?> Ratings Submitted</h4>
                <p>Student meal ratings recorded in the system.</p>
            </div>
        </div>

        <?php if ($meal_rating_count > 0): ?>
            <div class="empty-state">
                The system has recorded
                <strong><?php echo $meal_rating_count; ?></strong>
                meal ratings. Review the ratings in your available rating or report module.
            </div>
        <?php else: ?>
            <div class="empty-state">
                No meal ratings have been submitted yet.
                Students can rate their meals through the student module.
            </div>
        <?php endif; ?>
    </div>

    <!-- Manage Mess -->
    <div class="card-panel">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-list-check"></i>
                Manage Mess
            </div>
        </div>

        <div class="meal-list">
            <a href="/smartmess/manager/menu.php"
               class="btn-action"
               style="justify-content:flex-start;padding:15px;">
                <i class="fa-solid fa-calendar-days"></i>
                Manage Meal Schedule
                <span style="margin-left:auto;">→</span>
            </a>

            <a href="/smartmess/manager/complaints.php"
               class="btn-action"
               style="justify-content:flex-start;padding:15px;">
                <i class="fa-solid fa-comment-dots"></i>
                Review Complaints
                <span style="margin-left:auto;">→</span>
            </a>

            <a href="/smartmess/manager/announcements.php"
               class="btn-action"
               style="justify-content:flex-start;padding:15px;">
                <i class="fa-solid fa-bullhorn"></i>
                Manage Announcements
                <span style="margin-left:auto;">→</span>
            </a>

            <a href="/smartmess/manager/reports.php"
               class="btn-action"
               style="justify-content:flex-start;padding:15px;">
                <i class="fa-solid fa-chart-pie"></i>
                View Reports
                <span style="margin-left:auto;">→</span>
            </a>
        </div>
    </div>

</div>
</section>
</main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const bell = document.getElementById('notificationBell');
    const dropdown = document.getElementById('notificationDropdown');

    if (!bell || !dropdown) {
        return;
    }

    bell.addEventListener('click', function (event) {
        event.stopPropagation();

        const willOpen = dropdown.hidden;
        dropdown.hidden = !willOpen;
        bell.setAttribute('aria-expanded', String(willOpen));
    });

    document.addEventListener('click', function (event) {
        if (
            !dropdown.contains(event.target) &&
            !bell.contains(event.target)
        ) {
            dropdown.hidden = true;
            bell.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            dropdown.hidden = true;
            bell.setAttribute('aria-expanded', 'false');
            bell.focus();
        }
    });
});
</script>

</body>
</html>
