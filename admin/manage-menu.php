<?php
session_start();

if (!isset($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /smartmess/login.php');
    exit;
}

require_once __DIR__ . '/../db_config.php';

$message = '';
$message_type = 'success';

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$meal_times = [
    'Breakfast' => '08:00 AM - 09:30 AM',
    'Lunch' => '01:00 PM - 02:30 PM',
    'Evening Snack' => '04:30 PM - 05:30 PM',
    'Dinner' => '08:00 PM - 09:30 PM'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_menu'])) {
    $day = trim((string)($_POST['day_of_week'] ?? ''));
    $meal_type = trim((string)($_POST['meal_type'] ?? ''));
    $menu_items = trim((string)($_POST['menu_items'] ?? ''));

    if ($day === '' || $meal_type === '' || $menu_items === '') {
        $message = 'Please fill in all required menu fields.';
        $message_type = 'error';
    } elseif (!in_array($day, $days, true) || !array_key_exists($meal_type, $meal_times)) {
        $message = 'Please select a valid day and meal type.';
        $message_type = 'error';
    } else {
        $time = $meal_times[$meal_type];
        $check_stmt = $conn->prepare('SELECT id FROM meal_schedule WHERE day_of_week = ? AND meal_type = ? LIMIT 1');

        if (!$check_stmt) {
            $message = 'Database error while checking the schedule.';
            $message_type = 'error';
        } else {
            $check_stmt->bind_param('ss', $day, $meal_type);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $exists = $check_result->num_rows > 0;
            $check_stmt->close();

            if ($exists) {
                $stmt = $conn->prepare('UPDATE meal_schedule SET meal_time = ?, menu_items = ? WHERE day_of_week = ? AND meal_type = ?');
                if (!$stmt) {
                    $message = 'Database error while updating the menu.';
                    $message_type = 'error';
                } else {
                    $stmt->bind_param('ssss', $time, $menu_items, $day, $meal_type);
                    if ($stmt->execute()) {
                        $message = 'Menu updated successfully for ' . $day . ' (' . $meal_type . ').';
                        $message_type = 'success';
                    } else {
                        $message = 'Unable to update the menu.';
                        $message_type = 'error';
                    }
                    $stmt->close();
                }
            } else {
                $stmt = $conn->prepare('INSERT INTO meal_schedule (day_of_week, meal_type, meal_time, menu_items) VALUES (?, ?, ?, ?)');
                if (!$stmt) {
                    $message = 'Database error while creating the menu.';
                    $message_type = 'error';
                } else {
                    $stmt->bind_param('ssss', $day, $meal_type, $time, $menu_items);
                    if ($stmt->execute()) {
                        $message = 'Menu saved successfully for ' . $day . ' (' . $meal_type . ').';
                        $message_type = 'success';
                    } else {
                        $message = 'Unable to save the menu.';
                        $message_type = 'error';
                    }
                    $stmt->close();
                }
            }
        }
    }
}

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function meal_badge($meal_type)
{
    $meal = strtolower(trim((string)$meal_type));
    if ($meal === 'lunch') {
        return 'badge-lunch';
    }
    if ($meal === 'dinner' || $meal === 'evening snack') {
        return 'badge-dinner';
    }
    return 'badge-breakfast';
}

$schedule = [];
$schedule_sql = "SELECT id, day_of_week, meal_type, meal_time, menu_items
                FROM meal_schedule
                ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                FIELD(meal_type, 'Breakfast', 'Lunch', 'Evening Snack', 'Dinner')";

$result = $conn->query($schedule_sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $schedule[] = $row;
    }
} else {
    $message = 'Unable to load the meal schedule.';
    $message_type = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management | SmartMess Admin</title>
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
            grid-template-columns: minmax(320px, 420px) minmax(0, 1fr);
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
            min-height: 110px;
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

        .badge-meal {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge-breakfast {
            background: #fff0d8;
            color: #a85b00;
        }

        .badge-lunch {
            background: #dcfce7;
            color: #166534;
        }

        .badge-dinner {
            background: #f3e8ff;
            color: #6b21a8;
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
                    <a href="/smartmess/admin/manage-menu.php" class="nav-link active"><i class="fa-solid fa-calendar-days"></i>Menu Management</a>
                    <a href="/smartmess/admin/complaints.php" class="nav-link"><i class="fa-solid fa-comment-dots"></i>Feedback</a>
                    <a href="/smartmess/admin/announcements.php" class="nav-link"><i class="fa-solid fa-bullhorn"></i>Announcements</a>
                    <a href="/smartmess/admin/reports.php" class="nav-link"><i class="fa-solid fa-chart-pie"></i>Reports</a>
                </nav>
            </div>

            <a href="/smartmess/logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i>Log Out</a>
        </aside>

        <main class="main-workspace">
            <div class="page-header">
                <h2>Menu Management</h2>
                <p>Set and update daily meal schedules for students.</p>
            </div>

            <div class="content-grid">
                <div class="card-panel">
                    <div class="card-title">
                        <i class="fa-solid fa-pen-to-square" style="color: #3b52a0;"></i>
                        Add / Update Menu
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert-box <?php echo ($message_type === 'error') ? 'error' : ''; ?>">
                            <?php echo e($message); ?>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="form-group">
                            <label>Day of Week</label>
                            <select name="day_of_week" class="form-control" required>
                                <option value="">Select Day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Meal Type</label>
                            <select name="meal_type" class="form-control" required>
                                <option value="Breakfast">Breakfast (08:00 AM)</option>
                                <option value="Lunch">Lunch (01:00 PM)</option>
                                <option value="Evening Snack">Evening Snack (04:30 PM)</option>
                                <option value="Dinner">Dinner (08:00 PM)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Menu Items</label>
                            <textarea name="menu_items" class="form-control" placeholder="e.g. Idli, Sambar, Fresh Chutney, Tea/Coffee" required></textarea>
                        </div>

                        <button type="submit" name="save_menu" class="btn-submit">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Save Schedule
                        </button>
                    </form>
                </div>

                <div class="card-panel">
                    <div class="card-title">
                        <i class="fa-solid fa-calendar-week" style="color: #3b52a0;"></i>
                        Active Meal Timetable
                    </div>

                    <?php if (empty($schedule)): ?>
                        <div class="empty-state">No meal schedule has been added yet.</div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Meal</th>
                                    <th>Menu Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedule as $meal): ?>
                                    <tr>
                                        <td><strong><?php echo e($meal['day_of_week'] ?? ''); ?></strong></td>
                                        <td>
                                            <span class="badge-meal <?php echo meal_badge($meal['meal_type'] ?? 'Breakfast'); ?>">
                                                <?php echo e($meal['meal_type'] ?? 'Meal'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo nl2br(e($meal['menu_items'] ?? 'No menu items set.')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
