
<?php
session_start();

// Check manager login
if (
    !isset($_SESSION['logged_in']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'manager'
) {
    header("Location: ../login.php");
    exit;
}

require_once "../db_config.php";

$message = "";
$message_type = "";

// Available days
$days = [
    "Monday", "Tuesday", "Wednesday", "Thursday",
    "Friday", "Saturday", "Sunday"
];

// Meal types and timings
$meal_times = [
    "Breakfast" => "08:00 AM - 09:30 AM",
    "Lunch" => "01:00 PM - 02:30 PM",
    "Evening Snack" => "04:30 PM - 05:30 PM",
    "Dinner" => "08:00 PM - 09:30 PM"
];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_menu"])) {

    $day = trim($_POST["day"] ?? "");
    $meal = trim($_POST["meal"] ?? "");
    $items = trim($_POST["items"] ?? "");

    if (
        !in_array($day, $days, true) ||
        !array_key_exists($meal, $meal_times) ||
        $items === ""
    ) {
        $message = "Please select a valid day and meal and enter menu items.";
        $message_type = "error";

    } else {

        // Find the existing meal record
        $stmt = $conn->prepare(
            "SELECT id FROM meal_schedule
             WHERE day_of_week = ? AND meal_type = ?
             LIMIT 1"
        );

        if (!$stmt) {
            $message = "Database error: " . $conn->error;
            $message_type = "error";

        } else {
            $stmt->bind_param("ss", $day, $meal);

            if (!$stmt->execute()) {
                $message = "Unable to check the existing menu: " . $stmt->error;
                $message_type = "error";
                $stmt->close();

            } else {
                $stmt->store_result();
                $exists = $stmt->num_rows > 0;
                $stmt->close();

                if ($exists) {

                    // Update the existing record
                    $stmt = $conn->prepare(
                        "UPDATE meal_schedule
                         SET menu_items = ?
                         WHERE day_of_week = ? AND meal_type = ?"
                    );

                    if (!$stmt) {
                        $message = "Database error: " . $conn->error;
                        $message_type = "error";

                    } else {
                        $stmt->bind_param("sss", $items, $day, $meal);

                        if ($stmt->execute()) {
                            if ($stmt->affected_rows > 0) {
                                $message = "Menu updated successfully for "
                                    . $day . " (" . $meal . ").";
                            } else {
                                $message = "No change was needed. The menu may already contain these items.";
                            }

                            $message_type = "success";

                        } else {
                            $message = "Failed to update menu: " . $stmt->error;
                            $message_type = "error";
                        }

                        $stmt->close();
                    }

                } else {

                    // Insert a record if the meal does not exist
                    $time = $meal_times[$meal];

                    $stmt = $conn->prepare(
                        "INSERT INTO meal_schedule
                         (day_of_week, meal_type, meal_time, menu_items)
                         VALUES (?, ?, ?, ?)"
                    );

                    if (!$stmt) {
                        $message = "Database error: " . $conn->error;
                        $message_type = "error";

                    } else {
                        $stmt->bind_param(
                            "ssss",
                            $day,
                            $meal,
                            $time,
                            $items
                        );

                        if ($stmt->execute()) {
                            $message = "Menu added successfully for "
                                . $day . " (" . $meal . ").";
                            $message_type = "success";

                        } else {
                            $message = "Failed to add menu: " . $stmt->error;
                            $message_type = "error";
                        }

                        $stmt->close();
                    }
                }
            }
        }
    }
}

// Fetch actual menu records
$schedule = [];

$sql = "SELECT id, day_of_week, meal_type, meal_time, menu_items
        FROM meal_schedule
        ORDER BY
        FIELD(day_of_week,
            'Monday', 'Tuesday', 'Wednesday', 'Thursday',
            'Friday', 'Saturday', 'Sunday'),
        FIELD(meal_type,
            'Breakfast', 'Lunch', 'Evening Snack', 'Dinner')";

$result = $conn->query($sql);

if (!$result) {
    $message = "Unable to load the weekly menu: " . $conn->error;
    $message_type = "error";
} else {
    while ($row = $result->fetch_assoc()) {
        $schedule[] = $row;
    }
}

// Safely display database values
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meal Schedule | Manager Portal</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', -apple-system,
                         BlinkMacSystemFont, Roboto, sans-serif;
        }

        body {
            background-color: #2c3e50;
            min-height: 100vh;
            padding: 20px;
        }

        /* Main application window */
        .app-window {
            background-color: #f0f2f8;
            width: 100%;
            max-width: 1250px;
            min-height: 750px;
            margin: 0 auto;
            border-radius: 28px;
            display: flex;
            align-items: stretch;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
        }

        /* Preserve the existing manager sidebar */
        .app-window > .sidebar {
            flex: 0 0 230px;
            width: 230px;
            min-width: 230px;
            min-height: 750px;
            align-self: stretch;
        }

        /* Main content */
        .main-workspace {
            flex: 1 1 0;
            width: 0;
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
            margin-bottom: 5px;
        }

        .page-header p {
            font-size: 13px;
            color: #718096;
        }

        /* Form and table layout */
        .content-grid {
            display: grid;
            grid-template-columns: minmax(280px, 360px) minmax(0, 1fr);
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
            display: block;
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
            height: 90px;
            resize: vertical;
        }

        .btn-submit {
            width: 100%;
            background-color: #2c3e50;
            color: #ffffff;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn-submit:hover {
            background-color: #1e2b37;
        }

        /* Success and error messages */
        .alert-box {
            padding: 10px 14px;
            background: #e6fffa;
            border-left: 4px solid #319795;
            color: #234e52;
            font-size: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow-wrap: anywhere;
        }

        .alert-box.error {
            background: #fff5f5;
            border-left-color: #e53e3e;
            color: #9b2c2c;
        }

        /* Weekly menu table */
        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .data-table th,
        .data-table td {
            text-align: left;
            padding: 12px;
            font-size: 13px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: middle;
        }

        .data-table th {
            font-weight: 700;
            color: #718096;
            text-transform: uppercase;
            font-size: 11px;
        }

        .badge-meal {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .badge-breakfast {
            background: #feebc8;
            color: #c05621;
        }

        .badge-lunch {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-snack {
            background: #bee3f8;
            color: #2a4365;
        }

        .badge-dinner {
            background: #e9d8fd;
            color: #553c9a;
        }

        /* Responsive layout */
        @media (max-width: 1050px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
            body {
                padding: 0;
            }

            .app-window {
                min-height: 100vh;
                border-radius: 0;
            }

            .app-window > .sidebar {
                flex-basis: 200px;
                width: 200px;
                min-width: 200px;
            }

            .main-workspace {
                padding: 18px;
            }

            .card-panel {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<div class="app-window">

    <?php
    $manager_sidebar_theme = 'legacy';
    include '../includes/manager_sidebar.php';
    ?>

    <main class="main-workspace">

        <div class="page-header">
            <h2>Mess Meal Schedule</h2>
            <p>Manage and update daily meal plans for mess students.</p>
        </div>

        <div class="content-grid">

            <!-- Update form -->
            <div class="card-panel">

                <div class="card-title">
                    <i class="fa-solid fa-utensils" style="color:#2c3e50;"></i>
                    Update Meal Plan
                </div>

                <?php if ($message !== ''): ?>
                    <div class="alert-box <?= $message_type === 'error' ? 'error' : '' ?>">
                        <?= e($message) ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST">

                    <div class="form-group">
                        <label for="day">Day</label>

                        <select name="day" id="day" class="form-control" required>
                            <?php foreach ($days as $day_option): ?>
                                <option value="<?= e($day_option) ?>"
                                    <?= (($_POST['day'] ?? 'Monday') === $day_option) ? 'selected' : '' ?>>
                                    <?= e($day_option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="meal">Meal Type</label>

                        <select name="meal" id="meal" class="form-control" required>
                            <?php foreach ($meal_times as $meal_option => $time_option): ?>
                                <option value="<?= e($meal_option) ?>"
                                    <?= (($_POST['meal'] ?? 'Breakfast') === $meal_option) ? 'selected' : '' ?>>
                                    <?= e($meal_option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="items">Items List</label>

                        <textarea
                            name="items"
                            id="items"
                            class="form-control"
                            placeholder="Enter dishes separated by commas..."
                            required><?= e($_POST['items'] ?? '') ?></textarea>
                    </div>

                    <button type="submit"
                            name="update_menu"
                            value="1"
                            class="btn-submit">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save Changes
                    </button>

                </form>
            </div>

            <!-- Database-backed weekly menu -->
            <div class="card-panel">

                <div class="card-title">
                    <i class="fa-solid fa-list" style="color:#2c3e50;"></i>
                    Current Weekly Menu
                </div>

                <div class="table-wrap">
                    <table class="data-table">

                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Meal</th>
                                <th>Items</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (!empty($schedule)): ?>

                            <?php foreach ($schedule as $row): ?>

                                <?php
                                $badge_class = 'badge-breakfast';

                                if ($row['meal_type'] === 'Lunch') {
                                    $badge_class = 'badge-lunch';
                                } elseif ($row['meal_type'] === 'Evening Snack') {
                                    $badge_class = 'badge-snack';
                                } elseif ($row['meal_type'] === 'Dinner') {
                                    $badge_class = 'badge-dinner';
                                }
                                ?>

                                <tr>
                                    <td>
                                        <strong><?= e($row['day_of_week']) ?></strong>
                                    </td>

                                    <td>
                                        <span class="badge-meal <?= e($badge_class) ?>">
                                            <?= e($row['meal_type']) ?>
                                        </span>
                                    </td>

                                    <td><?= e($row['menu_items']) ?></td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="3">No menu records found.</td>
                            </tr>

                        <?php endif; ?>
                        </tbody>

                    </table>
                </div>
            </div>

        </div>
    </main>

</div>

</body>
</html>
```