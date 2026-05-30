<?php

$menu_items = [
    'new_order' => ['label' => 'Новый', 'icon' => 'fa-plus-circle', 'url' => 'index.php?section=new_order'],
    'in_progress' => ['label' => 'В работе', 'icon' => 'fa-tasks', 'url' => 'index.php?section=in_progress'],
    'completed' => ['label' => 'Готово', 'icon' => 'fa-check-circle', 'url' => 'index.php?section=completed'],
    'history' => ['label' => 'История', 'icon' => 'fa-history', 'url' => 'order_history.php'],
    'archive' => ['label' => 'Архив', 'icon' => 'fa-archive', 'url' => 'index.php?section=archive'],
    'calendar' => ['label' => 'Календарь', 'icon' => 'fa-calendar-alt', 'url' => 'calendar.php', 'class' => 'd-none d-lg-flex'],
    'zakaz' => ['label' => 'Шиномонтаж', 'short_label' => 'Шины', 'icon' => 'fa-file-invoice', 'url' => 'shinomontazh'],
    'warehouse' => ['label' => 'Склад', 'icon' => 'fa-warehouse', 'url' => 'index.php?section=warehouse'],
    'storage' => ['label' => 'Хранение', 'icon' => 'fa-box', 'url' => 'storage.php'],
    'rashody' => ['label' => 'Расходы', 'icon' => 'fa-coins', 'url' => 'Расходы.html?v=2026050102'],
    'settings' => ['label' => 'Настройки', 'icon' => 'fa-sliders-h', 'url' => 'index.php?section=settings', 'admin_only' => true],
];

function getVisibleMenuItems(): array
{
    global $menu_items;
    $isAdmin = isAdminUser();
    $visible = [];
    foreach ($menu_items as $key => $item) {
        if (!empty($item['admin_only']) && !$isAdmin) {
            continue;
        }
        $visible[$key] = $item;
    }
    return $visible;
}

function renderUnifiedNavigation(string $activeKey = '', array $options = []): void
{
    $showTop = false;
    $showBottom = false;
    $showSpacers = $options['show_spacers'] ?? false;
    $showUserRole = $options['show_user_role'] ?? true;
    $toggleLabel = $options['toggle_label'] ?? '☰';
    $toggleTitle = $options['toggle_title'] ?? 'Меню';
    $toggleClass = $options['toggle_class'] ?? 'btn nav-toggle d-none d-lg-flex';

    $items = getVisibleMenuItems();
    $topOrder = ['new_order', 'in_progress', 'completed', 'history', 'archive', 'calendar', 'zakaz', 'warehouse', 'storage', 'rashody', 'settings'];
    $sideOrder = ['zakaz', 'warehouse', 'new_order', 'in_progress', 'completed', 'archive', 'storage', 'rashody', 'history', 'settings'];
    $bottomOrder = ['new_order', 'in_progress', 'completed', 'history', 'archive', 'zakaz', 'warehouse', 'storage', 'rashody', 'settings'];

    $renderLink = static function (string $key, bool $isTop = false, bool $isBottom = false) use ($items, $activeKey): void {
        if (!isset($items[$key])) {
            return;
        }
        $item = $items[$key];
        $isActive = $key === $activeKey;
        $class = $isTop ? ($isActive ? 'active' : '') : ('nav-item' . ($isActive ? ' active' : ''));
        $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class) . '"' : '';
        $label = (string)$item['label'];
        $bottomLabel = (string)($item['short_label'] ?? $label);
        echo '<a href="' . htmlspecialchars((string)$item['url']) . '"' . $classAttr . ' title="' . htmlspecialchars($label) . '">';
        if ($isBottom && !empty($item['icon'])) {
            echo '<i class="fas ' . htmlspecialchars((string)$item['icon']) . ' nav-icon"></i>';
            echo '<span>' . htmlspecialchars($bottomLabel) . '</span>';
        } else {
            echo htmlspecialchars($label);
        }
        echo '</a>';
    };

    if ($showTop) {
        echo '<div class="top-nav d-lg-none">';
        foreach ($topOrder as $key) {
            $renderLink($key, true, false);
        }
        echo '<a href="?logout">Выйти</a>';
        echo '</div>';
    }

    if ($showSpacers) {
        echo '<div id="topNavSpacer" class="top-nav-spacer d-lg-none"></div>';
        echo '<div id="desktopNavSpacer" class="desktop-nav-spacer d-none d-lg-block"></div>';
    }

    echo '<button class="' . htmlspecialchars($toggleClass) . '" onclick="toggleNav()" aria-label="Открыть меню" title="' . htmlspecialchars($toggleTitle) . '">' . htmlspecialchars($toggleLabel) . '</button>';

    echo '<div class="nav-menu" id="navMenu">';
    echo '<div class="d-flex flex-column mb-3">';
    echo '<a href="?logout" class="btn btn-danger btn-sm">Выйти</a>';
    $username = isset($_SESSION['username']) ? (string)$_SESSION['username'] : 'Гость';
    $roleText = isAdminUser() ? 'Админ' : 'Пользователь';
    if ($showUserRole) {
        echo '<span class="text-dark mt-2">Пользователь: ' . htmlspecialchars($username) . ' (' . htmlspecialchars($roleText) . ')</span>';
    } else {
        echo '<span class="text-dark mt-2">Пользователь: ' . htmlspecialchars($username) . '</span>';
    }
    echo '</div>';
    foreach ($sideOrder as $key) {
        $renderLink($key, false, false);
    }
    echo '</div>';

    if ($showBottom) {
        echo '<div class="bottom-nav d-lg-none">';
        foreach ($bottomOrder as $key) {
            $renderLink($key, false, true);
        }
        echo '<a href="?logout" class="nav-item"><i class="fas fa-sign-out-alt nav-icon"></i><span>Выйти</span></a>';
        echo '</div>';
    }
}
