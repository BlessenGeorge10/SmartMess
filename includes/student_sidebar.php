<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    /* ==============================
       SMARTMESS STUDENT SIDEBAR
    ============================== */

    .student-sidebar {
        width: 250px;
        height: 100vh;
        height: 100dvh;
        background: #2c3e50;
        padding: 28px 0 24px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        color: #ffffff;
        flex-shrink: 0;
        position: fixed;
        left: 0;
        top: 0;
        overflow-x: hidden;
        overflow-y: auto;
        z-index: 1000;
        box-sizing: border-box;
        border-right: 1px solid rgba(255, 255, 255, 0.06);
        font-family: 'Segoe UI', Arial, sans-serif;
    }

    .student-sidebar *,
    .student-sidebar *::before,
    .student-sidebar *::after {
        box-sizing: border-box;
    }

    /* Sidebar top section */
    .student-sidebar-top {
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    /* Brand */
    .student-brand-logo {
        padding: 4px 24px 34px;
        font-size: 23px;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 12px;
        color: #ffffff;
        letter-spacing: -0.6px;
        white-space: nowrap;
    }

    .student-brand-logo i {
        width: 40px;
        height: 40px;
        background: #38bdf8;
        color: #1e2b37;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        box-shadow: 0 5px 14px rgba(56, 189, 248, 0.18);
        flex-shrink: 0;
    }

    /* Navigation */
    .student-nav-list {
        display: flex;
        flex-direction: column;
        gap: 7px;
        padding: 0 13px;
    }

    .student-nav-link {
        display: flex;
        align-items: center;
        gap: 14px;
        min-height: 48px;
        padding: 13px 15px;
        color: #cbd5e1;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        border-radius: 10px;
        border: 1px solid transparent;
        transition:
            background 0.2s ease,
            color 0.2s ease,
            border-color 0.2s ease,
            transform 0.2s ease;
    }

    .student-nav-link i {
        width: 20px;
        min-width: 20px;
        text-align: center;
        font-size: 17px;
        color: #94a3b8;
        transition: color 0.2s ease;
    }

    /* Hover */
    .student-nav-link:hover {
        background: rgba(255, 255, 255, 0.07);
        color: #ffffff;
        border-color: rgba(255, 255, 255, 0.05);
    }

    .student-nav-link:hover i {
        color: #38bdf8;
    }

    /* Active page */
    .student-nav-link.active {
        background: #f0f2f8;
        color: #1e2b37;
        font-weight: 750;
        border-color: #f0f2f8;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .student-nav-link.active i {
        color: #0284c7;
    }

    /* Logout */
    .student-logout-link {
        margin: 18px 13px 0;
        padding: 13px 15px;
        min-height: 48px;
        color: #cbd5e1;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 14px;
        border-radius: 10px;
        border: 1px solid transparent;
        transition: all 0.2s ease;
    }

    .student-logout-link i {
        width: 20px;
        min-width: 20px;
        text-align: center;
        font-size: 17px;
    }

    .student-logout-link:hover {
        background: rgba(239, 68, 68, 0.12);
        color: #fca5a5;
        border-color: rgba(239, 68, 68, 0.12);
    }

    /* Keyboard accessibility */
    .student-nav-link:focus-visible,
    .student-logout-link:focus-visible {
        outline: 2px solid #38bdf8;
        outline-offset: 3px;
    }

    /* Smaller screens */
    @media (max-width: 768px) {
        .student-sidebar {
            width: 220px;
            padding-top: 22px;
        }

        .student-brand-logo {
            padding: 4px 18px 28px;
            font-size: 21px;
        }

        .student-nav-list {
            padding: 0 10px;
            gap: 6px;
        }

        .student-nav-link {
            padding: 12px;
            font-size: 13px;
        }

        .student-logout-link {
            margin-left: 10px;
            margin-right: 10px;
            padding: 12px;
        }
    }

    @media (max-height: 520px) {
        .student-sidebar {
            padding-top: 14px;
            padding-bottom: 14px;
        }

        .student-brand-logo {
            padding-bottom: 18px;
        }

        .student-nav-list {
            gap: 3px;
        }

        .student-nav-link {
            min-height: 40px;
            padding-top: 9px;
            padding-bottom: 9px;
        }

        .student-logout-link {
            min-height: 40px;
            margin-top: 10px;
            padding-top: 9px;
            padding-bottom: 9px;
        }
    }
</style>

<aside class="student-sidebar">

    <div class="student-sidebar-top">

        <!-- SmartMess Logo -->
        <div class="student-brand-logo">
            <i class="fa-solid fa-utensils"></i>
            <span>SmartMess</span>
        </div>

        <!-- Navigation -->
        <nav class="student-nav-list">

            <!-- Dashboard -->
            <a href="/smartmess/student/dashboard.php"
               class="student-nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-border-all"></i>
                <span>Dashboard</span>
            </a>

            <!-- Schedule -->
            <a href="/smartmess/student/meal-schedule.php"
               class="student-nav-link <?php echo ($current_page === 'meal-schedule.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Schedule</span>
            </a>

            <!-- Meal Skip -->
            <a href="/smartmess/student/mealskip.php"
               class="student-nav-link <?php echo ($current_page === 'mealskip.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-person-walking-arrow-right"></i>
                <span>Meal Skip</span>
            </a>

            <!-- Announcements -->
            <a href="/smartmess/student/announcements.php"
               class="student-nav-link <?php echo ($current_page === 'announcements.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-bullhorn"></i>
                <span>Announcements</span>
            </a>

            <!-- Meal Rating -->
            <a href="/smartmess/student/rating.php"
               class="student-nav-link <?php echo ($current_page === 'rating.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-star"></i>
                <span>Meal Rating</span>
            </a>

            <!-- Feedback -->
            <a href="/smartmess/student/complaints.php"
               class="student-nav-link <?php echo ($current_page === 'complaints.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-comment-dots"></i>
                <span>Feedback</span>
            </a>

        </nav>

    </div>

    <!-- Logout -->
    <a href="/smartmess/logout.php" class="student-logout-link">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Log Out</span>
    </a>

</aside>