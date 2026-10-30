
<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

if (
    empty($_SESSION['logged_in']) ||
    strtolower($_SESSION['role'] ?? '') !== 'admin'
) {
    header("Location: /smartmess/login.php");
    exit;
}

require_once __DIR__ . '/../db_config.php';

/* Find the active database connection */
$db = null;

foreach (['conn', 'mysqli', 'con', 'connection', 'db'] as $connectionName) {
    if (isset($$connectionName) && $$connectionName instanceof mysqli) {
        $db = $$connectionName;
        break;
    }
}

if (!$db) {
    die("Database connection not found. Please check db_config.php.");
}

$db->set_charset("utf8mb4");

/* Escape output safely */
function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* Selected month: YYYY-MM */
$selectedMonth = is_string($_GET['month'] ?? null) ? $_GET['month'] : date('Y-m');

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$monthStart = $selectedMonth . '-01';

function adminReportQuery($db, $sql, $types, $params)
{
    $stmt = $db->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare monthly overview query: ' . $db->error);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Unable to execute monthly overview query: ' . $error);
    }

    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    if ($result) {
        $result->free();
    }

    $stmt->close();
    return $rows;
}

$nextMonth = date('Y-m-d', strtotime($monthStart . ' +1 month'));
$analyticsError = '';
$foodItems = [];
$highestFood = null;
$lowestFood = null;
$feedbackCategories = [];
$feedbackResponses = [];
$ratingStats = ['rating_count' => 0, 'average_rating' => null];
$mealSkipStats = [
    'total_requests' => 0,
    'breakfast_count' => 0,
    'lunch_count' => 0,
    'dinner_count' => 0,
    'full_day_count' => 0,
];
$feedbackStats = [
    'total_feedback' => 0,
    'pending_feedback' => 0,
    'in_progress_feedback' => 0,
    'resolved_feedback' => 0,
];

try {
    $rows = adminReportQuery(
        $db,
        "SELECT COUNT(*) AS rating_count, AVG(rating) AS average_rating
         FROM food_item_ratings
         WHERE serving_date >= ? AND serving_date < ?",
        'ss',
        [$monthStart, $nextMonth]
    );
    if ($rows) {
        $ratingStats = $rows[0];
    }

    $foodItems = adminReportQuery(
        $db,
        "SELECT
             food_item,
             meal_type,
             COUNT(*) AS rating_count,
             AVG(rating) AS average_rating,
             SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS one_star_count,
             SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS two_star_count,
             SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS three_star_count,
             SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS four_star_count,
             SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS five_star_count
         FROM food_item_ratings
         WHERE serving_date >= ? AND serving_date < ?
         GROUP BY food_item, meal_type
         ORDER BY average_rating DESC, rating_count DESC, food_item ASC",
        'ss',
        [$monthStart, $nextMonth]
    );
    if ($foodItems) {
        $highestFood = $foodItems[0];
        $lowestFood = $foodItems[count($foodItems) - 1];
    }

    $rows = adminReportQuery(
        $db,
        "SELECT
             COUNT(*) AS total_requests,
             COALESCE(SUM(breakfast = 1), 0) AS breakfast_count,
             COALESCE(SUM(lunch = 1), 0) AS lunch_count,
             COALESCE(SUM(dinner = 1), 0) AS dinner_count,
             COALESCE(SUM(breakfast = 1 AND lunch = 1 AND dinner = 1), 0) AS full_day_count
         FROM meal_skips
         WHERE LOWER(TRIM(status)) = 'active'
           AND start_date < ?
           AND end_date >= ?",
        'ss',
        [$nextMonth, $monthStart]
    );
    if ($rows) {
        $mealSkipStats = $rows[0];
    }

    $rows = adminReportQuery(
        $db,
        "SELECT
             COUNT(*) AS total_feedback,
             COALESCE(SUM(LOWER(TRIM(status)) = 'pending'), 0) AS pending_feedback,
             COALESCE(SUM(LOWER(TRIM(status)) = 'in progress'), 0) AS in_progress_feedback,
             COALESCE(SUM(LOWER(TRIM(status)) IN ('resolved', 'closed', 'completed')), 0) AS resolved_feedback
         FROM feedback
         WHERE created_at >= ? AND created_at < ?",
        'ss',
        [$monthStart, $nextMonth]
    );
    if ($rows) {
        $feedbackStats = $rows[0];
    }

    $feedbackCategories = adminReportQuery(
        $db,
        "SELECT COALESCE(NULLIF(TRIM(category), ''), 'General') AS category,
                COUNT(*) AS total_count
         FROM feedback
         WHERE created_at >= ? AND created_at < ?
         GROUP BY COALESCE(NULLIF(TRIM(category), ''), 'General')
         ORDER BY total_count DESC, category ASC",
        'ss',
        [$monthStart, $nextMonth]
    );

    $feedbackResponses = adminReportQuery(
        $db,
        "SELECT category, status, manager_response, created_at
         FROM feedback
         WHERE created_at >= ? AND created_at < ?
           AND manager_response IS NOT NULL
           AND TRIM(manager_response) <> ''
         ORDER BY created_at DESC
         LIMIT 5",
        'ss',
        [$monthStart, $nextMonth]
    );
} catch (RuntimeException $exception) {
    error_log($exception->getMessage());
    $analyticsError = 'Unable to load all monthly overview data. Please check the related database tables.';
} catch (mysqli_sql_exception $exception) {
    error_log('Monthly overview database query failed: ' . $exception->getMessage());
    $analyticsError = 'Unable to load all monthly overview data. Please check the related database tables.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Overview | SmartMess Admin</title>
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
            min-height: 100vh;
            background: var(--bg);
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
            display: flex;
            flex: 0 0 240px;
            flex-direction: column;
            justify-content: space-between;
            width: 240px;
            min-width: 240px;
            padding: 28px 0 22px;
            background: linear-gradient(180deg, var(--navy), var(--deep-navy));
            color: #fff;
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 12px;
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
            background: rgba(255,255,255,0.04);
            color: #fff;
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

        .logout-link:hover { color: #ffd9d9; }

        .main-workspace {
            display: flex;
            flex: 1;
            flex-direction: column;
            gap: 22px;
            min-width: 0;
            padding: 28px 30px 34px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .page-header h1 {
            margin-bottom: 6px;
            font-size: 26px;
            letter-spacing: -0.04em;
        }

        .page-header p,
        .muted {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .user-pill {
            display: flex;
            flex-shrink: 0;
            align-items: center;
            gap: 10px;
            padding: 6px 12px 6px 7px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: var(--panel);
            box-shadow: var(--shadow);
        }

        .avatar-img {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--navy);
            color: #fff;
            font-size: 12px;
        }

        .user-pill span {
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
        }

        .card-panel {
            min-width: 0;
            padding: 22px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--panel);
            box-shadow: var(--shadow);
        }

        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
        }

        .panel-head h2 {
            margin-bottom: 5px;
            font-size: 17px;
            color: var(--text);
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .overview-card {
            min-width: 0;
            padding: 18px 20px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--panel);
            box-shadow: var(--shadow);
        }

        .overview-card span {
            display: block;
            margin-bottom: 8px;
            color: var(--muted);
            font-size: 12px;
        }

        .overview-card strong {
            color: var(--text);
            font-size: 26px;
            letter-spacing: -0.04em;
        }

        .overview-card .muted { margin-top: 5px; }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .analytics-section { min-width: 0; }

        .analytics-section h3 {
            margin-bottom: 14px;
            color: var(--text);
            font-size: 16px;
        }

        .analytics-section p {
            margin: 8px 0;
            color: var(--text);
            font-size: 13px;
            line-height: 1.6;
        }

        .analytics-section p.muted { color: var(--muted); }

        .analytics-error {
            margin-bottom: 16px;
            padding: 12px 14px;
            border: 1px solid #f1c4c4;
            border-radius: 12px;
            background: #fff0f0;
            color: #a52b2b;
            font-size: 13px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .analytics-table {
            width: 100%;
            min-width: 360px;
            border-collapse: collapse;
        }

        .analytics-table th,
        .analytics-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #edf2f7;
            text-align: left;
            vertical-align: top;
            font-size: 13px;
        }

        .analytics-table th {
            color: #8a9bb5;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .analytics-table tbody tr:hover { background: #fafcff; }

        .rating-warning {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            background: #fff0d8;
            color: #a85b00;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .filter-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-form input[type="month"] {
            min-width: 170px;
            padding: 11px 12px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fafcff;
            color: var(--text);
            font: inherit;
            font-size: 13px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 14px;
            border: 0;
            border-radius: 12px;
            background: var(--navy);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .button:hover { background: var(--deep-navy); }

        .button-secondary {
            background: #eef3ff;
            color: #3b52a0;
        }

        .button-secondary:hover {
            background: #dfe8ff;
            color: #2c3e50;
        }

        .empty-state {
            padding: 22px;
            border: 1px dashed var(--line);
            border-radius: 12px;
            background: #f8fafc;
            color: var(--muted);
            font-size: 13px;
            text-align: center;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        @media (max-width: 1100px) {
            .overview-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 820px) {
            .app-window { flex-direction: column; }

            .sidebar {
                width: 100%;
                min-width: 100%;
                padding: 20px 0;
            }

            .nav-list {
                flex-direction: row;
                flex-wrap: wrap;
                padding: 0 14px 10px;
            }

            .nav-link { padding: 11px 12px; }

            .logout-link { padding: 10px 24px 0; }

            .main-workspace { padding: 20px 16px 28px; }
        }

        @media (max-width: 560px) {
            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .overview-grid,
            .analytics-grid { grid-template-columns: 1fr; }

            .card-panel { padding: 16px; }

            .panel-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .filter-form { width: 100%; }

            .filter-form input[type="month"] { flex: 1; }
        }

        @media print {
            body,
            .app-window { background: #fff; }

            .sidebar,
            .filter-form,
            .user-pill { display: none !important; }

            .main-workspace {
                display: block;
                padding: 0;
            }

            .page-header { margin-bottom: 18px; }

            .card-panel,
            .overview-card {
                margin-bottom: 14px;
                box-shadow: none;
                break-inside: avoid;
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
                <a href="/smartmess/admin/user-management.php" class="nav-link"><i class="fa-solid fa-users"></i> User Management</a>
                <a href="/smartmess/admin/manage-menu.php" class="nav-link"><i class="fa-solid fa-calendar-days"></i> Menu Management</a>
                <a href="/smartmess/admin/complaints.php" class="nav-link"><i class="fa-solid fa-comment-dots"></i> Feedback</a>
                <a href="/smartmess/admin/announcements.php" class="nav-link"><i class="fa-solid fa-bullhorn"></i> Announcements</a>
                <a href="/smartmess/admin/reports.php" class="nav-link active" aria-current="page"><i class="fa-solid fa-chart-pie"></i> Reports</a>
            </nav>
        </div>
        <a href="/smartmess/logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
    </aside>

    <main class="main-workspace">
        <header class="page-header">
            <div>
                <h1>Reports Overview</h1>
                <p>Overall monthly mess report for <?= h(date('F Y', strtotime($monthStart))) ?>. Review food ratings, meal skips, and feedback.</p>
            </div>
            <div class="user-pill">
                <span class="avatar-img"><i class="fa-solid fa-user-shield"></i></span>
                <span>Administrator</span>
            </div>
        </header>

        <section class="card-panel">
            <div class="panel-head">
                <div>
                    <h2>Monthly Mess Overview</h2>
                    <p class="muted">Actual database records for <?= h(date('F Y', strtotime($monthStart))) ?>.</p>
                </div>
                <form method="GET" class="filter-form">
                    <label class="sr-only" for="report-month">Report month</label>
                    <input
                        type="month"
                        id="report-month"
                        name="month"
                        value="<?= h($selectedMonth) ?>"
                        required
                    >
                    <button class="button" type="submit"><i class="fa-solid fa-calendar-check"></i> View Month</button>
                    <button class="button button-secondary" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
                </form>
            </div>
        </section>

        <?php if ($analyticsError !== ''): ?>
            <div class="analytics-error"><?= h($analyticsError) ?></div>
        <?php endif; ?>

        <div class="overview-grid">
            <div class="overview-card">
                <span>Food Ratings</span>
                <strong><?= $analyticsError !== '' ? '—' : (int)$ratingStats['rating_count'] ?></strong>
                <div class="muted">
                    Average:
                    <?= $analyticsError !== '' || $ratingStats['average_rating'] === null
                        ? '—'
                        : h(number_format((float)$ratingStats['average_rating'], 1) . ' / 5') ?>
                </div>
            </div>
            <div class="overview-card">
                <span>Active Meal-Skip Requests</span>
                <strong><?= $analyticsError !== '' ? '—' : (int)$mealSkipStats['total_requests'] ?></strong>
            </div>
            <div class="overview-card">
                <span>Feedback Submissions</span>
                <strong><?= $analyticsError !== '' ? '—' : (int)$feedbackStats['total_feedback'] ?></strong>
            </div>
        </div>

        <div class="analytics-grid">
            <section class="card-panel analytics-section">
                <h3>Food Item Ratings</h3>
                <?php if ($analyticsError !== ''): ?>
                    <p class="muted">Rating details are unavailable.</p>
                <?php elseif (!$foodItems): ?>
                    <p class="muted">No food ratings were submitted this month.</p>
                <?php else: ?>
                    <p class="muted">
                        Highest rated:
                        <strong><?= h($highestFood['food_item']) ?></strong>
                        (<?= h(number_format((float)$highestFood['average_rating'], 1)) ?> / 5,
                        <?= (int)$highestFood['rating_count'] ?> ratings)
                    </p>
                    <p class="muted">
                        Lowest rated:
                        <strong><?= h($lowestFood['food_item']) ?></strong>
                        (<?= h(number_format((float)$lowestFood['average_rating'], 1)) ?> / 5,
                        <?= (int)$lowestFood['rating_count'] ?> ratings)
                    </p>
                    <div class="table-wrap">
                        <table class="analytics-table">
                            <thead>
                                <tr>
                                    <th>Food item</th>
                                    <th>Meal</th>
                                    <th>Average</th>
                                    <th>Ratings</th>
                                    <th>1 star</th>
                                    <th>2 stars</th>
                                    <th>3 stars</th>
                                    <th>4 stars</th>
                                    <th>5 stars</th>
                                    <th>Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($foodItems as $food): ?>
                                    <tr>
                                        <td><?= h($food['food_item']) ?></td>
                                        <td><?= h($food['meal_type']) ?></td>
                                        <td><?= h(number_format((float)$food['average_rating'], 1)) ?> / 5</td>
                                        <td><?= (int)$food['rating_count'] ?></td>
                                        <td><?= (int)$food['one_star_count'] ?></td>
                                        <td><?= (int)$food['two_star_count'] ?></td>
                                        <td><?= (int)$food['three_star_count'] ?></td>
                                        <td><?= (int)$food['four_star_count'] ?></td>
                                        <td><?= (int)$food['five_star_count'] ?></td>
                                        <td>
                                            <?php if ((float)$food['average_rating'] < 3): ?>
                                                <span class="rating-warning">Needs attention</span>
                                            <?php else: ?>
                                                <span class="muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="muted">Items averaging below 3 / 5 are marked for attention. The table is ordered from highest to lowest average.</p>
                <?php endif; ?>
            </section>

            <section class="card-panel analytics-section">
                <h3>Meal-Skip Requests</h3>
                <?php if ($analyticsError !== ''): ?>
                    <p class="muted">Meal-skip details are unavailable.</p>
                <?php elseif ((int)$mealSkipStats['total_requests'] === 0): ?>
                    <p class="muted">No active meal-skip requests overlapped this month.</p>
                <?php else: ?>
                    <p><strong>Total active requests:</strong> <?= (int)$mealSkipStats['total_requests'] ?></p>
                    <p><strong>Breakfast:</strong> <?= (int)$mealSkipStats['breakfast_count'] ?> request(s)</p>
                    <p><strong>Lunch:</strong> <?= (int)$mealSkipStats['lunch_count'] ?> request(s)</p>
                    <p><strong>Dinner:</strong> <?= (int)$mealSkipStats['dinner_count'] ?> request(s)</p>
                    <p><strong>Full-day:</strong> <?= (int)$mealSkipStats['full_day_count'] ?> request(s)</p>
                    <p class="muted">
                        Active request rows whose date range overlaps this month; each request is counted once.
                        Full-day requests are included in each selected meal's count.
                    </p>
                <?php endif; ?>
            </section>

            <section class="card-panel analytics-section">
                <h3>Feedback &amp; Complaints</h3>
                <?php if ($analyticsError !== ''): ?>
                    <p class="muted">Feedback details are unavailable.</p>
                <?php else: ?>
                    <p><strong>Pending:</strong> <?= (int)$feedbackStats['pending_feedback'] ?></p>
                    <p><strong>In progress:</strong> <?= (int)$feedbackStats['in_progress_feedback'] ?></p>
                    <p><strong>Resolved:</strong> <?= (int)$feedbackStats['resolved_feedback'] ?></p>
                    <?php if (!$feedbackCategories): ?>
                        <p class="muted">No feedback was submitted this month.</p>
                    <?php else: ?>
                        <p><strong>By category:</strong></p>
                        <?php foreach ($feedbackCategories as $category): ?>
                            <p class="muted">
                                <?= h($category['category']) ?>:
                                <?= (int)$category['total_count'] ?>
                            </p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($feedbackResponses): ?>
                        <p><strong>Recent manager responses:</strong></p>
                        <?php foreach ($feedbackResponses as $response): ?>
                            <p class="muted">
                                <?= h($response['category']) ?>
                                (<?= h($response['status']) ?>):
                                <?= h($response['manager_response']) ?>
                            </p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

</body>
</html>
