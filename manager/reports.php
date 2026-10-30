<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

/* Manager login protection */
if (
    empty($_SESSION['logged_in']) ||
    strtolower($_SESSION['role'] ?? '') !== 'manager'
) {
    header("Location: /smartmess/login.php");
    exit;
}

/* Database connection */
require_once __DIR__ . '/../db_config.php';

$db = null;

foreach (['conn', 'mysqli', 'con', 'connection', 'db'] as $varName) {
    if (isset($$varName) && $$varName instanceof mysqli) {
        $db = $$varName;
        break;
    }
}

if (!$db) {
    die(
        "Database connection not found. Please check the connection variable " .
        "in db_config.php."
    );
}

$db->set_charset('utf8mb4');

/* Selected month */
$selectedMonth = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$monthStart = $selectedMonth . '-01';
$nextMonth = date('Y-m-d', strtotime($monthStart . ' +1 month'));

/* Selected meal */
$allowedMeals = [
    'Breakfast',
    'Lunch',
    'Evening Snack',
    'Dinner'
];

$selectedMeal = trim($_GET['meal'] ?? 'All');

if (
    $selectedMeal !== 'All' &&
    !in_array($selectedMeal, $allowedMeals, true)
) {
    $selectedMeal = 'All';
}

/* Escape output safely */
function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/* Format average rating */
function formatRating($value)
{
    if ($value === null || $value === '') {
        return '—';
    }

    return number_format((float)$value, 1) . ' / 5';
}

/* Execute a prepared SELECT query */
function reportQuery($db, $sql, $types = '', $params = [])
{
    $stmt = $db->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Unable to prepare report query: ' . $db->error
        );
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();

        throw new RuntimeException(
            'Unable to execute report query: ' . $error
        );
    }

    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    if ($result) {
        $result->free();
    }

    $stmt->close();

    return $rows;
}

/* Reusable rating filter */
$ratingWhere = "
    WHERE serving_date >= ?
      AND serving_date < ?
";

$ratingTypes = 'ss';
$ratingParams = [$monthStart, $nextMonth];

if ($selectedMeal !== 'All') {
    $ratingWhere .= " AND meal_type = ?";
    $ratingTypes .= 's';
    $ratingParams[] = $selectedMeal;
}

/* Initialise report values */
$errorMessage = '';

$totalRatings = 0;
$averageRating = null;
$highestRatedFood = null;
$lowestRatedFood = null;
$uniqueStudents = 0;
$resolutionPercent = 0;
$totalFeedback = 0;
$resolvedFeedback = 0;

$mealSummary = [];
$foodSummary = [];
$comments = [];
$feedbackSummary = [];
$recentFeedback = [];

try {
    /* 1. Overall rating statistics */
    $rows = reportQuery(
        $db,
        "
        SELECT
            COUNT(*) AS total_ratings,
            AVG(rating) AS average_rating,
            COUNT(DISTINCT user_id) AS unique_students
        FROM food_item_ratings
        $ratingWhere
        ",
        $ratingTypes,
        $ratingParams
    );

    if (!empty($rows)) {
        $totalRatings = (int)$rows[0]['total_ratings'];
        $averageRating = $rows[0]['average_rating'];
        $uniqueStudents = (int)$rows[0]['unique_students'];
    }

    /* 2. Average rating by meal type */
    $mealSummary = reportQuery(
        $db,
        "
        SELECT
            meal_type,
            COUNT(*) AS total_ratings,
            AVG(rating) AS average_rating
        FROM food_item_ratings
        $ratingWhere
        GROUP BY meal_type
        ORDER BY
            CASE meal_type
                WHEN 'Breakfast' THEN 1
                WHEN 'Lunch' THEN 2
                WHEN 'Evening Snack' THEN 3
                WHEN 'Dinner' THEN 4
                ELSE 5
            END
        ",
        $ratingTypes,
        $ratingParams
    );

    /* 3. Food-wise rating report */
    $foodSummary = reportQuery(
        $db,
        "
        SELECT
            food_item,
            meal_type,
            COUNT(*) AS total_ratings,
            AVG(rating) AS average_rating,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS one_star_count,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS two_star_count,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS three_star_count,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS four_star_count,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS five_star_count
        FROM food_item_ratings
        $ratingWhere
        GROUP BY food_item, meal_type
        ORDER BY average_rating ASC, total_ratings DESC
        ",
        $ratingTypes,
        $ratingParams
    );

    /* 4. Student comments */
    $comments = reportQuery(
        $db,
        "
        SELECT
            serving_date,
            meal_type,
            food_item,
            rating,
            comment
        FROM food_item_ratings
        $ratingWhere
          AND comment IS NOT NULL
          AND TRIM(comment) <> ''
        ORDER BY serving_date DESC, created_at DESC
        LIMIT 100
        ",
        $ratingTypes,
        $ratingParams
    );

    /* 5. Monthly complaint / feedback summary */
    $feedbackSummary = reportQuery(
        $db,
        "
        SELECT
            COALESCE(NULLIF(TRIM(category), ''), 'General') AS category,
            COUNT(*) AS total_count,
            SUM(
                CASE
                    WHEN LOWER(TRIM(status)) IN
                        ('resolved', 'closed', 'completed')
                    THEN 1
                    ELSE 0
                END
            ) AS resolved_count
        FROM feedback
        WHERE created_at >= ?
          AND created_at < ?
        GROUP BY COALESCE(NULLIF(TRIM(category), ''), 'General')
        ORDER BY total_count DESC
        ",
        'ss',
        [$monthStart, $nextMonth]
    );

    /* 6. Feedback totals */
    $rows = reportQuery(
        $db,
        "
        SELECT
            COUNT(*) AS total_feedback,
            SUM(
                CASE
                    WHEN LOWER(TRIM(status)) IN
                        ('resolved', 'closed', 'completed')
                    THEN 1
                    ELSE 0
                END
            ) AS resolved_feedback
        FROM feedback
        WHERE created_at >= ?
          AND created_at < ?
        ",
        'ss',
        [$monthStart, $nextMonth]
    );

    if (!empty($rows)) {
        $totalFeedback = (int)$rows[0]['total_feedback'];
        $resolvedFeedback = (int)($rows[0]['resolved_feedback'] ?? 0);
    }

    $resolutionPercent = $totalFeedback > 0
        ? ($resolvedFeedback / $totalFeedback) * 100
        : 0;

    /* 7. Latest feedback records */
    $recentFeedback = reportQuery(
        $db,
        "
        SELECT
            category,
            message,
            status,
            manager_response,
            created_at
        FROM feedback
        WHERE created_at >= ?
          AND created_at < ?
        ORDER BY created_at DESC
        LIMIT 10
        ",
        'ss',
        [$monthStart, $nextMonth]
    );

    /* Highest-rated food */
    $rankedFoods = reportQuery(
        $db,
        "
        SELECT
            food_item,
            meal_type,
            COUNT(*) AS total_ratings,
            AVG(rating) AS average_rating
        FROM food_item_ratings
        $ratingWhere
        GROUP BY food_item, meal_type
        HAVING COUNT(*) >= 3
        ORDER BY average_rating DESC, total_ratings DESC
        ",
        $ratingTypes,
        $ratingParams
    );

    if (!empty($rankedFoods)) {
        $highestRatedFood = $rankedFoods[0];

        /* Lowest-rated food */
        $lowestRows = reportQuery(
            $db,
            "
            SELECT
                food_item,
                meal_type,
                COUNT(*) AS total_ratings,
                AVG(rating) AS average_rating
            FROM food_item_ratings
            $ratingWhere
            GROUP BY food_item, meal_type
            HAVING COUNT(*) >= 3
            ORDER BY average_rating ASC, total_ratings DESC
            LIMIT 1
            ",
            $ratingTypes,
            $ratingParams
        );

        if (!empty($lowestRows)) {
            $lowestRatedFood = $lowestRows[0];
        }
    }

} catch (Throwable $e) {
    error_log('SmartMess report error: ' . $e->getMessage());

    $errorMessage =
        'Some report data could not be loaded. Please check the database ' .
        'tables and columns used by this report.';
}

/* Month label */
$monthLabel = date('F Y', strtotime($monthStart));

/* Rating bar percentage */
function ratingWidth($rating)
{
    return max(0, min(100, ((float)$rating / 5) * 100));
}

/* Status label */
function statusClass($status)
{
    $status = strtolower(trim((string)$status));

    if (in_array($status, ['resolved', 'closed', 'completed'], true)) {
        return 'status-resolved';
    }

    if (in_array($status, ['pending', 'open', 'new'], true)) {
        return 'status-pending';
    }

    return 'status-other';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reports &amp; Analytics | Manager Portal</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

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

        .main-workspace {
            flex: 1;
            min-width: 0;
            padding: 30px;
            overflow-y: auto;
            background: #f0f2f8;
        }

        .page-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
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
            line-height: 1.6;
        }

        .btn-print {
            background-color: #2c3e50;
            color: white;
            border: none;
            padding: 11px 17px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
            transition: background 0.2s, transform 0.2s;
        }

        .btn-print:hover {
            background-color: #1e2b37;
            transform: translateY(-1px);
        }

        .btn-print:disabled {
            opacity: 0.7;
            cursor: wait;
            transform: none;
        }

        .filter-panel {
            background: #fff;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            display: flex;
            align-items: end;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }

        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 170px;
        }

        .filter-field label {
            font-size: 12px;
            font-weight: 700;
            color: #5a6e85;
        }

        .filter-field input,
        .filter-field select {
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid #d8e1ed;
            background: #f8fafc;
            color: #2d3748;
            border-radius: 10px;
            outline: none;
        }

        .filter-field input:focus,
        .filter-field select:focus {
            border-color: #3a5ba0;
        }

        .btn-filter {
            background: #3a5ba0;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            min-height: 42px;
            transition: background 0.2s;
        }

        .btn-filter:hover {
            background: #2c477e;
        }

        .btn-reset {
            text-decoration: none;
            color: #52647a;
            font-size: 13px;
            font-weight: 600;
            padding: 12px 5px;
        }

        .btn-reset:hover {
            color: #2c3e50;
            text-decoration: underline;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #fff;
            border-radius: 20px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }

        .stat-icon {
            flex-shrink: 0;
            width: 47px;
            height: 47px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .stat-info {
            min-width: 0;
        }

        .stat-info h3 {
            font-size: 22px;
            color: #1a202c;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .stat-info p {
            font-size: 11px;
            color: #718096;
            font-weight: 600;
            margin-top: 4px;
            line-height: 1.5;
        }

        .content-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .card-panel {
            background: #fff;
            border-radius: 20px;
            padding: 25px;
            min-width: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }

        .full-width {
            margin-bottom: 25px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 9px;
            line-height: 1.5;
        }

        .card-subtitle {
            font-size: 12px;
            color: #718096;
            margin-top: -10px;
            margin-bottom: 18px;
            line-height: 1.6;
        }

        .table-scroll {
            width: 100%;
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 460px;
        }

        .data-table th,
        .data-table td {
            text-align: left;
            padding: 14px 11px;
            font-size: 12px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: middle;
            line-height: 1.6;
        }

        .data-table th {
            font-weight: 700;
            color: #8290a3;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.45px;
        }

        .data-table td {
            color: #42536a;
        }

        .data-table td strong {
            color: #2d3748;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .rating-wrapper {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 90px;
        }

        .rating-number {
            font-weight: 700;
            color: #2d3748;
        }

        .rating-bar-container {
            width: 100%;
            background: #edf2f7;
            height: 6px;
            border-radius: 10px;
            overflow: hidden;
        }

        .rating-bar-fill {
            height: 100%;
            background: #3a5ba0;
            border-radius: 10px;
        }

        .rating-low {
            color: #c05621;
            font-weight: 700;
        }

        .rating-good {
            color: #2f855a;
            font-weight: 700;
        }

        .status {
            font-size: 10px;
            font-weight: 700;
            padding: 5px 9px;
            border-radius: 8px;
            display: inline-block;
            white-space: nowrap;
        }

        .status-resolved {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-pending {
            background: #feebc8;
            color: #9c4221;
        }

        .status-other {
            background: #e2e8f0;
            color: #4a5568;
        }

        .empty-state {
            text-align: center !important;
            color: #8290a3 !important;
            padding: 28px 12px !important;
        }

        .empty-state i {
            display: block;
            font-size: 23px;
            margin-bottom: 8px;
            color: #a0aec0;
        }

        .food-name {
            font-weight: 700;
            color: #2d3748;
        }

        .meal-tag {
            display: inline-block;
            background: #edf2f7;
            color: #52647a;
            border-radius: 7px;
            padding: 4px 7px;
            font-size: 10px;
            font-weight: 700;
        }

        .insight-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .insight {
            background: #eef5fb;
            border: 1px solid #dbe7f3;
            border-radius: 20px;
            padding: 25px;
            min-width: 0;
        }

        .insight-label {
            color: #718096;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .insight h4 {
            font-size: 16px;
            color: #1e2b37;
            margin-bottom: 5px;
            overflow-wrap: anywhere;
        }

        .insight p {
            font-size: 12px;
            color: #52647a;
            line-height: 1.6;
        }

        .alert-error {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            color: #9b2c2c;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.7;
        }

        .report-note {
            color: #718096;
            font-size: 11px;
            line-height: 1.7;
            margin-top: 15px;
            margin-bottom: 10px;
        }

        .comment-cell {
            min-width: 180px;
            max-width: 300px;
            overflow-wrap: anywhere;
        }

        @media (max-width: 1150px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 850px) {
            body {
                padding: 0;
                align-items: stretch;
            }

            .app-window {
                border-radius: 0;
                min-height: 100vh;
            }

            .main-workspace {
                padding: 25px;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .insight-grid {
                gap: 20px;
            }
        }

        @media (max-width: 650px) {
            .main-workspace {
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stat-card {
                padding: 20px;
            }

            .filter-panel {
                align-items: stretch;
                flex-direction: column;
                padding: 20px;
            }

            .filter-field {
                width: 100%;
                min-width: 0;
            }

            .filter-field input,
            .filter-field select {
                width: 100%;
            }

            .btn-filter {
                width: 100%;
            }

            .btn-reset {
                text-align: center;
            }

            .insight-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .card-panel {
                padding: 25px;
            }

            .insight {
                padding: 20px;
            }

            .page-header h2 {
                font-size: 22px;
            }

            .btn-print {
                width: 100%;
            }
        }

        @media (max-width: 400px) {
            .main-workspace {
                padding: 15px;
            }

            .card-panel {
                padding: 18px;
            }

            .filter-panel {
                padding: 18px;
            }

            .stat-card {
                padding: 16px;
            }
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
                display: block;
            }

            .sidebar,
            .btn-print,
            .filter-panel,
            .no-print {
                display: none !important;
            }

            .app-window {
                box-shadow: none;
                width: 100%;
                max-width: 100%;
                border-radius: 0;
                min-height: auto;
            }

            .main-workspace {
                padding: 0;
                overflow: visible;
            }

            .content-grid,
            .stats-grid,
            .insight-grid {
                break-inside: avoid;
            }

            .card-panel,
            .stat-card,
            .insight {
                box-shadow: none;
                border: 1px solid #e2e8f0;
            }

            .data-table th,
            .data-table td {
                font-size: 10px;
                padding: 8px 6px;
            }

            .table-scroll {
                overflow: visible;
            }

            .data-table {
                min-width: 0;
            }

            .page-header {
                margin-bottom: 18px;
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

    <main class="main-workspace" id="reportContent">

        <div class="page-header">
            <div>
                <h2>Mess Analytics &amp; Reports</h2>
                <p>
                    Real student ratings, food performance and feedback
                    for <?= h($monthLabel) ?>.
                </p>
            </div>

            <button
                class="btn-print"
                id="downloadPdfButton"
                type="button"
                onclick="downloadReportPDF()"
            >
                <i class="fa-solid fa-file-pdf"></i>
                Download Report PDF
            </button>
        </div>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= h($errorMessage) ?>
                <br>
                Check the PHP error log for the specific database error.
            </div>
        <?php endif; ?>

        <form class="filter-panel" method="GET">

            <div class="filter-field">
                <label for="month">Report Month</label>
                <input
                    type="month"
                    id="month"
                    name="month"
                    value="<?= h($selectedMonth) ?>"
                    required
                >
            </div>

            <div class="filter-field">
                <label for="meal">Meal Type</label>
                <select id="meal" name="meal">
                    <option
                        value="All"
                        <?= $selectedMeal === 'All' ? 'selected' : '' ?>
                    >
                        All Meals
                    </option>

                    <?php foreach ($allowedMeals as $meal): ?>
                        <option
                            value="<?= h($meal) ?>"
                            <?= $selectedMeal === $meal ? 'selected' : '' ?>
                        >
                            <?= h($meal) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="btn-filter" type="submit">
                <i class="fa-solid fa-filter"></i>
                Apply Filters
            </button>

            <a
                class="btn-reset"
                href="<?= h(basename($_SERVER['PHP_SELF'])) ?>"
            >
                Reset
            </a>

        </form>

        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon" style="background:#ebf8ff;color:#3182ce;">
                    <i class="fa-solid fa-star"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($totalRatings) ?></h3>
                    <p>Total Food Ratings</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:#faf5ff;color:#805ad5;">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?= h(formatRating($averageRating)) ?></h3>
                    <p>Average Student Rating</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:#e6fffa;color:#319795;">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($uniqueStudents) ?></h3>
                    <p>Students Who Rated Food</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:#feebc8;color:#dd6b20;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="stat-info">
                    <h3>
                        <?= $totalFeedback > 0
                            ? number_format($resolutionPercent, 1) . '%'
                            : '—' ?>
                    </h3>
                    <p>Feedback Resolution Rate</p>
                </div>
            </div>

        </div>

        <div class="insight-grid">

            <div class="insight">
                <div class="insight-label">
                    <i class="fa-solid fa-thumbs-up"></i>
                    HIGHEST-RATED FOOD
                </div>

                <?php if ($highestRatedFood): ?>
                    <h4><?= h($highestRatedFood['food_item']) ?></h4>
                    <p>
                        <?= h($highestRatedFood['meal_type']) ?>
                        &middot;
                        <strong>
                            <?= h(formatRating($highestRatedFood['average_rating'])) ?>
                        </strong>
                        &middot;
                        <?= (int)$highestRatedFood['total_ratings'] ?> ratings
                    </p>
                <?php else: ?>
                    <h4>Not enough ratings yet</h4>
                    <p>A food item needs at least 3 ratings to appear in this highlight.</p>
                <?php endif; ?>
            </div>

            <div class="insight">
                <div class="insight-label">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                    FOOD NEEDING REVIEW
                </div>

                <?php if ($lowestRatedFood): ?>
                    <h4><?= h($lowestRatedFood['food_item']) ?></h4>
                    <p>
                        <?= h($lowestRatedFood['meal_type']) ?>
                        &middot;
                        <strong>
                            <?= h(formatRating($lowestRatedFood['average_rating'])) ?>
                        </strong>
                        &middot;
                        <?= (int)$lowestRatedFood['total_ratings'] ?> ratings
                    </p>
                <?php else: ?>
                    <h4>Not enough ratings yet</h4>
                    <p>More student ratings are needed before food performance can be compared reliably.</p>
                <?php endif; ?>
            </div>

        </div>

        <div class="content-grid">

            <section class="card-panel">
                <div class="card-title">
                    <i class="fa-solid fa-chart-column" style="color:#3a5ba0;"></i>
                    Meal Satisfaction Breakdown
                </div>

                <p class="card-subtitle">
                    Average ratings and number of food-item reviews for <?= h($monthLabel) ?>.
                </p>

                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Meal Type</th>
                                <th>Average Rating</th>
                                <th>Reviews</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mealSummary)): ?>
                                <tr>
                                    <td colspan="3" class="empty-state">
                                        <i class="fa-regular fa-star"></i>
                                        No ratings found for this selection.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($mealSummary as $row): ?>
                                    <tr>
                                        <td><strong><?= h($row['meal_type']) ?></strong></td>
                                        <td>
                                            <div class="rating-wrapper">
                                                <span class="rating-number">
                                                    <?= h(formatRating($row['average_rating'])) ?>
                                                </span>
                                                <div class="rating-bar-container">
                                                    <div class="rating-bar-fill"
                                                        style="width:<?= ratingWidth($row['average_rating']) ?>%;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= number_format((int)$row['total_ratings']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card-panel">
                <div class="card-title">
                    <i class="fa-solid fa-list-check" style="color:#3a5ba0;"></i>
                    Feedback Resolution Summary
                </div>

                <p class="card-subtitle">
                    Actual feedback records grouped by category.
                </p>

                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Total</th>
                                <th>Resolved</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($feedbackSummary)): ?>
                                <tr>
                                    <td colspan="3" class="empty-state">
                                        <i class="fa-regular fa-folder-open"></i>
                                        No feedback found for this month.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($feedbackSummary as $row): ?>
                                    <tr>
                                        <td><strong><?= h($row['category']) ?></strong></td>
                                        <td><?= (int)$row['total_count'] ?></td>
                                        <td>
                                            <?= (int)$row['resolved_count'] ?>
                                            / <?= (int)$row['total_count'] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <p class="report-note">
                    Resolution is calculated from statuses named Resolved, Closed or Completed.
                </p>
            </section>

        </div>

        <section class="card-panel full-width">
            <div class="card-title">
                <i class="fa-solid fa-utensils" style="color:#3a5ba0;"></i>
                Food-Wise Rating Performance
            </div>

            <p class="card-subtitle">
                Items are ordered from lower to higher average rating. Review consistently low-rated items before planning the next semester's menu.
            </p>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Food Item</th>
                            <th>Meal</th>
                            <th>Average Rating</th>
                            <th>Number of Ratings</th>
                            <th>Rating Distribution (1-5 stars)</th>
                            <th>Manager Insight</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($foodSummary)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i class="fa-solid fa-bowl-food"></i>
                                    No food ratings available for this month.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($foodSummary as $row): ?>
                                <?php
                                $avg = (float)$row['average_rating'];

                                if ($avg < 3) {
                                    $insight = 'Review needed';
                                    $insightClass = 'rating-low';
                                } elseif ($avg < 4) {
                                    $insight = 'Monitor feedback';
                                    $insightClass = '';
                                } else {
                                    $insight = 'Good performance';
                                    $insightClass = 'rating-good';
                                }
                                ?>
                                <tr>
                                    <td><span class="food-name"><?= h($row['food_item']) ?></span></td>
                                    <td><span class="meal-tag"><?= h($row['meal_type']) ?></span></td>
                                    <td>
                                        <div class="rating-wrapper">
                                            <span class="rating-number"><?= h(formatRating($avg)) ?></span>
                                            <div class="rating-bar-container">
                                                <div class="rating-bar-fill" style="width:<?= ratingWidth($avg) ?>%;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= number_format((int)$row['total_ratings']) ?></td>
                                    <td>
                                        1 star: <?= (int)$row['one_star_count'] ?><br>
                                        2 stars: <?= (int)$row['two_star_count'] ?><br>
                                        3 stars: <?= (int)$row['three_star_count'] ?><br>
                                        4 stars: <?= (int)$row['four_star_count'] ?><br>
                                        5 stars: <?= (int)$row['five_star_count'] ?>
                                    </td>
                                    <td><span class="<?= h($insightClass) ?>"><?= h($insight) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card-panel full-width">
            <div class="card-title">
                <i class="fa-solid fa-comments" style="color:#3a5ba0;"></i>
                Student Comments on Food
            </div>

            <p class="card-subtitle">Optional comments submitted with food ratings.</p>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Serving Date</th>
                            <th>Meal</th>
                            <th>Food Item</th>
                            <th>Rating</th>
                            <th>Student Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($comments)): ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="fa-regular fa-comment-dots"></i>
                                    No student comments found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($comments as $row): ?>
                                <tr>
                                    <td><?= h(date('d M Y', strtotime($row['serving_date']))) ?></td>
                                    <td><?= h($row['meal_type']) ?></td>
                                    <td><strong><?= h($row['food_item']) ?></strong></td>
                                    <td><?= (int)$row['rating'] ?> / 5</td>
                                    <td class="comment-cell"><?= h($row['comment']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (count($comments) === 100): ?>
                <p class="report-note">Showing the latest 100 comments for the selected month.</p>
            <?php endif; ?>
        </section>

        <section class="card-panel full-width">
            <div class="card-title">
                <i class="fa-solid fa-clipboard-list" style="color:#3a5ba0;"></i>
                Latest Service Feedback
            </div>

            <p class="card-subtitle">
                Recent service and hygiene feedback for <?= h($monthLabel) ?>.
            </p>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Manager Response</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentFeedback)): ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="fa-regular fa-circle-check"></i>
                                    No service feedback found for this month.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentFeedback as $row): ?>
                                <tr>
                                    <td><?= h(date('d M Y', strtotime($row['created_at']))) ?></td>
                                    <td><strong><?= h($row['category']) ?></strong></td>
                                    <td class="comment-cell"><?= h($row['message']) ?></td>
                                    <td>
                                        <span class="status <?= h(statusClass($row['status'])) ?>">
                                            <?= h(ucfirst((string)$row['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="comment-cell">
                                        <?= trim((string)$row['manager_response']) !== ''
                                            ? h($row['manager_response'])
                                            : 'No response yet' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <p class="report-note">
            Report period: <?= h($monthLabel) ?>.
            Food performance is based on submitted student ratings.
            Items with average ratings below 3 are flagged for review;
            managers should consider the number of ratings and student comments before changing the menu.
        </p>

    </main>
</div>

<script>
async function downloadReportPDF() {
    const button = document.getElementById('downloadPdfButton');
    const report = document.getElementById('reportContent');

    if (!button || !report) {
        alert('The report could not be found.');
        return;
    }

    if (typeof html2pdf === 'undefined') {
        alert(
            'The PDF library could not be loaded. ' +
            'Check your internet connection and refresh the page.'
        );
        return;
    }

    const originalButtonHTML = button.innerHTML;
    const originalButtonDisabled = button.disabled;

    button.disabled = true;
    button.innerHTML =
        '<i class="fa-solid fa-spinner fa-spin"></i> Preparing PDF...';

    try {
        if (report.scrollHeight <= 0 || !report.querySelector('.stats-grid')) {
            throw new Error('The report content is empty or not ready.');
        }

        if (document.fonts && document.fonts.ready) {
            await document.fonts.ready;
        }

        /*
         * Prepare a meaningful filename.
         */
        const monthForFile = <?= json_encode($selectedMonth) ?>;
        const mealForFile = <?= json_encode($selectedMeal) ?>;

        const safeMeal = mealForFile === 'All'
            ? 'All_Meals'
            : mealForFile.replace(/[^a-zA-Z0-9_-]/g, '_');

        const fileName =
            'SmartMess_Report_' +
            monthForFile +
            '_' +
            safeMeal +
            '.pdf';

        /*
         * html2canvas applies PDF-only changes to its rendering clone,
         * leaving the visible report layout untouched.
         */
        const options = {
            margin: [8, 8, 8, 8],
            filename: fileName,

            image: {
                type: 'jpeg',
                quality: 0.98
            },

            html2canvas: {
                scale: 1.5,
                useCORS: true,
                logging: false,
                backgroundColor: '#f0f2f8',
                scrollX: 0,
                scrollY: 0,
                windowWidth: 1300,
                onclone: clonedDocument => {
                    const pdfReport =
                        clonedDocument.getElementById('reportContent');

                    if (!pdfReport) {
                        throw new Error(
                            'The report could not be prepared for PDF rendering.'
                        );
                    }

                    pdfReport.querySelectorAll(
                        '.filter-panel, .btn-print, .no-print'
                    ).forEach(element => element.remove());

                    const pdfHeading =
                        clonedDocument.createElement('div');
                    pdfHeading.style.cssText =
                        'margin-bottom:20px;padding-bottom:14px;' +
                        'border-bottom:2px solid #3a5ba0;';

                    const headingTitle =
                        clonedDocument.createElement('h1');
                    headingTitle.textContent =
                        'SmartMess - Analytics & Reports';
                    headingTitle.style.cssText =
                        'font-size:23px;color:#243b64;margin:0 0 8px;';

                    const headingMonth =
                        clonedDocument.createElement('p');
                    headingMonth.textContent =
                        'Report period: ' + <?= json_encode($monthLabel) ?>;
                    headingMonth.style.cssText =
                        'font-size:12px;margin:0 0 5px;';

                    const headingMeal =
                        clonedDocument.createElement('p');
                    headingMeal.textContent =
                        'Meal filter: ' +
                        <?= json_encode(
                            $selectedMeal === 'All'
                                ? 'All Meals'
                                : $selectedMeal
                        ) ?>;
                    headingMeal.style.cssText =
                        'font-size:12px;margin:0;';

                    pdfHeading.appendChild(headingTitle);
                    pdfHeading.appendChild(headingMonth);
                    pdfHeading.appendChild(headingMeal);
                    pdfReport.insertBefore(
                        pdfHeading,
                        pdfReport.firstChild
                    );

                    const generatedAt =
                        clonedDocument.createElement('p');
                    generatedAt.textContent =
                        'Generated on: ' + new Date().toLocaleString();
                    generatedAt.style.cssText =
                        'font-size:10px;color:#555;margin-top:18px;';
                    pdfReport.appendChild(generatedAt);

                    Object.assign(pdfReport.style, {
                        display: 'block',
                        width: '1200px',
                        maxWidth: 'none',
                        minWidth: '0',
                        height: 'auto',
                        minHeight: '0',
                        margin: '0',
                        padding: '28px',
                        overflow: 'visible',
                        background: '#f0f2f8',
                        color: '#2d3748',
                        boxSizing: 'border-box'
                    });

                    pdfReport.querySelectorAll('.table-scroll')
                        .forEach(element => {
                            element.style.overflow = 'visible';
                            element.style.width = '100%';
                            element.style.maxWidth = 'none';
                        });

                    pdfReport.querySelectorAll('table')
                        .forEach(table => {
                            table.style.width = '100%';
                            table.style.minWidth = '0';
                            table.style.tableLayout = 'auto';
                            table.style.borderCollapse = 'collapse';
                        });

                    pdfReport.querySelectorAll('th, td')
                        .forEach(cell => {
                            cell.style.whiteSpace = 'normal';
                            cell.style.overflowWrap = 'anywhere';
                        });

                    pdfReport.querySelectorAll(
                        '.stat-card, .insight, tr'
                    ).forEach(element => {
                        element.style.breakInside = 'avoid';
                        element.style.pageBreakInside = 'avoid';
                    });
                }
            },

            jsPDF: {
                unit: 'mm',
                format: 'a4',
                orientation: 'landscape'
            },

            pagebreak: {
                mode: ['css', 'legacy'],
                avoid: ['.stat-card', '.insight', 'tr']
            }
        };

        await html2pdf()
            .set(options)
            .from(report)
            .save();

    } catch (error) {
        console.error('SmartMess PDF download error:', error);

        alert(
            'Unable to generate the PDF. Refresh the page and try again. ' +
            'If the problem continues, press F12 and check the Console.'
        );

    } finally {
        button.disabled = originalButtonDisabled;
        button.innerHTML = originalButtonHTML;
    }
}
</script>

</body>
</html>