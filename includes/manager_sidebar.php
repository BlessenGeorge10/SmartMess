
<?php
$current_page = basename($_SERVER['PHP_SELF'] ?? '');

$manager_sidebar_sections = [
    [
        'title' => 'MAIN',
        'items' => [
            [
                'page' => 'dashboard.php',
                'url' => '/smartmess/manager/dashboard.php',
                'label' => 'Dashboard',
                'icon' => '🏠',
                'legacy_icon' => 'fa-border-all',
            ],
        ],
    ],
    [
        'title' => 'MANAGEMENT',
        'items' => [
            [
                'page' => 'menu.php',
                'url' => '/smartmess/manager/menu.php',
                'label' => 'Meal Schedule',
                'icon' => '📅',
                'legacy_icon' => 'fa-calendar-days',
            ],
            [
                'page' => 'mealskip.php',
                'url' => '/smartmess/manager/mealskip.php',
                'label' => 'Meal Skip',
                'icon' => '🍽️',
                'legacy_icon' => 'fa-person-walking-arrow-right',
            ],
            [
                'page' => 'announcements.php',
                'url' => '/smartmess/manager/announcements.php',
                'label' => 'Announcements',
                'icon' => '📢',
                'legacy_icon' => 'fa-bullhorn',
            ],
        ],
    ],
    [
        'title' => 'FEEDBACK',
        'items' => [
            [
                'page' => 'complaints.php',
                'url' => '/smartmess/manager/complaints.php',
                'label' => 'Complaints',
                'icon' => '⚠️',
                'legacy_icon' => 'fa-comment-dots',
            ],
        ],
    ],
    [
        'title' => 'INSIGHTS',
        'items' => [
            [
                'page' => 'reports.php',
                'url' => '/smartmess/manager/reports.php',
                'label' => 'Reports',
                'icon' => '📋',
                'legacy_icon' => 'fa-chart-pie',
            ],
        ],
    ],
];

$is_legacy_manager_sidebar =
    ($manager_sidebar_theme ?? 'modern') === 'legacy';
?>

<?php if ($is_legacy_manager_sidebar): ?>
    <aside class="sidebar manager-sidebar-legacy">
        <div class="sidebar-top">
            <div class="brand-logo">
                <i class="fa-solid fa-utensils"></i>
                <span>Manager Portal</span>
            </div>

            <nav class="nav-list">
                <?php foreach ($manager_sidebar_sections as $section): ?>
                    <?php foreach ($section['items'] as $item): ?>
                        <a
                            href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
                            class="nav-link <?= $current_page === $item['page'] ? 'active' : '' ?>">
                            <i class="fa-solid <?= htmlspecialchars($item['legacy_icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                            <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </nav>
        </div>

        <a href="/smartmess/logout.php" class="logout-link">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Log Out</span>
        </a>
    </aside>
<?php else: ?>
    <aside class="sidebar manager-sidebar-modern" id="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon">🍴</span>
            <span class="brand-name">Manager <span class="brand-dot">●</span></span>
        </div>

        <nav class="nav-menu">
            <?php foreach ($manager_sidebar_sections as $section): ?>
                <div class="nav-section">
                    <h3 class="nav-section-title">
                        <?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?>
                    </h3>

                    <?php foreach ($section['items'] as $item): ?>
                        <a
                            href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
                            class="nav-link <?= $current_page === $item['page'] ? 'active' : '' ?>">
                            <span class="nav-icon"><?= $item['icon'] ?></span>
                            <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="nav-section">
                <h3 class="nav-section-title">ACCOUNT</h3>
                <a href="/smartmess/logout.php" class="nav-link logout-link">
                    <span class="nav-icon">🚪</span>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </aside>
<?php endif; ?>

<style>
/* Legacy sidebar: used by manager/menu.php */
.manager-sidebar-legacy {
    flex: 0 0 230px;
    width: 230px;
    min-width: 230px;
    min-height: 750px;
    background: #1e2b37;
    padding: 30px 0 25px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-self: stretch;
    color: #fff;
}

.manager-sidebar-legacy .sidebar-top {
    width: 100%;
}

.manager-sidebar-legacy .brand-logo {
    padding: 0 20px 35px 25px;
    font-size: 20px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #fff;
    white-space: nowrap;
}

.manager-sidebar-legacy .brand-logo i {
    flex-shrink: 0;
}

.manager-sidebar-legacy .nav-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.manager-sidebar-legacy .nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 18px 12px 25px;
    color: #b8c7ce;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: background-color .2s, color .2s;
}

.manager-sidebar-legacy .nav-link i {
    width: 18px;
    text-align: center;
    flex-shrink: 0;
}

.manager-sidebar-legacy .nav-link:hover {
    color: #fff;
}

.manager-sidebar-legacy .nav-link.active {
    background: #f0f2f8;
    color: #1e2b37;
    font-weight: 700;
    border-radius: 20px 0 0 20px;
    margin-left: 15px;
    padding-left: 20px;
}

.manager-sidebar-legacy .logout-link {
    padding: 0 20px 0 25px;
    color: #e74c3c;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.manager-sidebar-legacy .logout-link:hover {
    color: #ff786b;
}

/* Modern sidebar: separate styles */
.manager-sidebar-modern {
    flex: 0 0 250px;
    width: 250px;
    min-width: 250px;
    background: #1e2b37;
    padding: 30px 0 25px;
    display: flex;
    flex-direction: column;
    color: #fff;
}

.manager-sidebar-modern .sidebar-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 20px 35px;
    font-size: 22px;
    font-weight: 800;
}

.manager-sidebar-modern .brand-icon {
    font-size: 28px;
}

.manager-sidebar-modern .brand-name {
    white-space: nowrap;
}

.manager-sidebar-modern .brand-dot {
    color: #35c6a0;
    font-size: 12px;
}

.manager-sidebar-modern .nav-menu {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.manager-sidebar-modern .nav-section-title {
    padding: 0 25px 8px;
    color: #91a3af;
    font-size: 10px;
    letter-spacing: 1.2px;
}

.manager-sidebar-modern .nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 25px;
    color: #b8c7ce;
    text-decoration: none;
    font-size: 14px;
}

.manager-sidebar-modern .nav-link:hover {
    color: #fff;
}

.manager-sidebar-modern .nav-link.active {
    color: #fff;
    background: #304354;
    border-left: 4px solid #35c6a0;
}

.manager-sidebar-modern .nav-icon {
    width: 22px;
    text-align: center;
}

.manager-sidebar-modern .logout-link {
    color: #e74c3c;
}

/* Mobile navigation */
@media (max-width: 700px) {
    .app-window {
        flex-direction: column;
        min-height: 100vh;
        border-radius: 18px;
    }

    .app-window > .manager-sidebar-legacy,
    .app-window > .manager-sidebar-modern {
        flex: 0 0 auto;
        width: 100%;
        min-width: 0;
        min-height: auto;
        gap: 18px;
        padding: 18px 0;
    }

    .manager-sidebar-legacy .brand-logo {
        padding: 0 20px 16px;
    }

    .manager-sidebar-legacy .nav-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        padding: 0 12px;
        gap: 4px;
    }

    .manager-sidebar-legacy .nav-link {
        padding: 10px;
        gap: 8px;
    }

    .manager-sidebar-legacy .nav-link.active {
        margin-left: 0;
        padding-left: 10px;
        border-radius: 10px;
    }

    .manager-sidebar-legacy .logout-link {
        padding: 8px 20px 0;
    }

    .manager-sidebar-modern .sidebar-brand {
        padding-bottom: 15px;
    }

    .manager-sidebar-modern .nav-menu {
        gap: 8px;
    }

    .manager-sidebar-modern .nav-section-title {
        padding-bottom: 5px;
    }

    .app-window .main-workspace {
        width: 100%;
        padding: 20px;
    }
}

@media (max-width: 480px) {
    .manager-sidebar-legacy .nav-list {
        grid-template-columns: 1fr;
    }

    .app-window .main-workspace {
        padding: 16px;
    }
}
</style>