<?php

$title = 'Records';
include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/scroll-top.php';

$user            = $user            ?? $_SESSION['user'] ?? [];
$role            = $user['role']    ?? '';
$csrf            = $csrf            ?? '';
$records         = $records         ?? [];
$total           = $total           ?? 0;
$page            = $page            ?? 1;
$totalPages      = $totalPages      ?? 1;
$perPage         = $perPage         ?? 25;
$search          = $search          ?? '';
$school          = $school          ?? '';
$animalType      = $animalType      ?? '';
$sex          = $sex          ?? '';
$researcherType  = $researcherType  ?? '';
$status          = $status          ?? '';
$sort            = $sort            ?? 'newest';
$schools         = $schools         ?? [];
$animalTypes     = $animalTypes     ?? [];
$sexes         = $sexes         ?? [];
$researcherTypes = $researcherTypes ?? [];
$stats           = $stats           ?? [
    'reviewed' => 0,
    'revised' => 0,
    'revision_submissions' => 0,
    'avg_revisions' => 0,
    'revisions_by_protocol' => [],
    'revisions_by_pi' => [],
    'endorsed' => 0,
    'signed' => 0,
    'clearances_returned' => 0,
    'total_records' => 0,
    'total_records_all' => 0,
    'total_animals' => 0,
    'avg_animals_per_record' => 0,
    'species_breakdown' => [],
    'school_breakdown' => [],
    'sex_breakdown' => [],
    'researcher_type_breakdown' => [],
    'incomplete_count' => 0,
    'excluded_by_period' => 0,
];
$period          = $period          ?? null;
$periodPreset    = $periodPreset    ?? RecordModel::DEFAULT_PERIOD;
$periodFrom      = $periodFrom      ?? '';
$periodTo        = $periodTo        ?? '';
$listPeriod      = $listPeriod      ?? null;
$comparison      = $comparison      ?? null;
$flash_success   = $flash_success   ?? '';
$flash_error     = $flash_error     ?? '';

$offset      = ($page - 1) * $perPage;
$incompleteOnly = $incompleteOnly ?? false;
$hasFilters  = $incompleteOnly || $search !== '' || $school !== '' || $animalType !== '' || $sex !== '' || $researcherType !== '' || $status !== '' || $listPeriod !== null;
$isStaff = $role === 'staff';
$colCount = 18;

// ===== Chart helpers =====
function pluralize(int $count, string $singular, ?string $plural = null): string
{
    return $count === 1 ? $singular : ($plural ?? $singular . 's');
}

function statBarRows(array $breakdown, int $topN = 6, bool $groupOther = true): array
{
    if (! $breakdown) return [];
    $top = array_slice($breakdown, 0, $topN);
    if ($groupOther) {
        $rest       = array_slice($breakdown, $topN);
        $otherTotal = array_sum(array_column($rest, 'total'));
        if ($otherTotal > 0) {
            $top[] = ['label' => 'Other', 'total' => $otherTotal, 'protocols' => array_sum(array_column($rest, 'protocols'))];
        }
    }
    $grandTotal = array_sum(array_column($top, 'total'));
    $max        = max(array_column($top, 'total'));
    $rows = [];
    foreach ($top as $row) {
        $rows[] = [
            'label'     => $row['label'],
            'total'     => (int) $row['total'],
            'share'     => $grandTotal > 0 ? round(($row['total'] / $grandTotal) * 100) : 0,
            'bar'       => $max > 0 ? round(($row['total'] / $max) * 100) : 0,
            'protocols' => isset($row['protocols']) ? (int) $row['protocols'] : null,
            'tooltip'   => $row['tooltip'] ?? null,
            'modifier'  => $row['modifier'] ?? '',
        ];
    }
    return $rows;
}

function renderBarList(array $rows, string $emptyText, bool $showShare = true, bool $stacked = false): void
{
    if (! $rows) {
        echo '<div class="records-card-empty">' . htmlspecialchars($emptyText) . '</div>';
        return;
    }
    echo '<ul class="records-bar-chart' . ($stacked ? ' records-bar-chart--stacked' : '') . '">';
    foreach ($rows as $r) {
        $hover = $r['tooltip'] ?? ($r['protocols'] !== null ? (int) $r['protocols'] . ' ' . pluralize((int) $r['protocols'], 'protocol') : null);
        $title = $hover !== null ? ' title="' . htmlspecialchars((string) $hover, ENT_QUOTES) . '"' : '';
        $fill  = 'records-bar-fill' . ($r['modifier'] !== '' ? ' records-bar-fill--' . $r['modifier'] : '');
        echo '<li class="records-bar-row"' . $title . '>';
        echo '<span class="records-bar-label">' . htmlspecialchars($r['label']) . '</span>';
        echo '<span class="records-bar-track"><span class="' . $fill . '" style="--bar-pct: ' . $r['bar'] . '%"></span></span>';
        echo '<span class="records-bar-count">' . number_format($r['total']);
        if ($showShare) echo '<small>' . $r['share'] . '%</small>';
        echo '</span></li>';
    }
    echo '</ul>';
}

function statDelta(int|float $value, ?int $previous): string
{
    if ($previous === null) return '';
    $diff = $value - $previous;
    if ($diff === 0) {
        return '<span class="stat-delta stat-delta--flat" title="Same as the previous period (' . number_format($previous) . ')">No change</span>';
    }
    $dir   = $diff > 0 ? 'up' : 'down';
    $arrow = $diff > 0 ? '↑' : '↓';
    $label = $previous > 0
        ? number_format(round(abs($diff) / $previous * 100)) . '%'
        : number_format(abs($diff)) . ' new';
    $title = number_format($previous) . ' in the previous period, ' . number_format(abs($diff)) . ($diff > 0 ? ' more' : ' fewer') . ' now';
    return '<span class="stat-delta stat-delta--' . $dir . '" title="' . htmlspecialchars($title, ENT_QUOTES) . '">'
        . '<span aria-hidden="true">' . $arrow . '</span> ' . $label
        . '<span class="sr-only"> ' . ($diff > 0 ? 'up' : 'down') . ' from ' . number_format($previous) . ' in the previous period</span></span>';
}

function statCompare(int|float $value, ?int $previous): string
{
    if ($previous === null) return '';
    $max  = max($value, $previous, 1);
    $row  = function (string $name, int $n, string $mod) use ($max): string {
        return '<div class="stat-compare-row"><span class="stat-compare-name">' . $name . '</span>'
            . '<span class="stat-compare-track"><span class="stat-compare-fill stat-compare-fill--' . $mod . '" style="--w: ' . round($n / $max * 100) . '%"></span></span>'
            . '<span class="stat-compare-num">' . number_format($n) . '</span></div>';
    };
    return '<div class="stat-compare" aria-hidden="true">' . $row('Before', $previous, 'before') . $row('Now', $value, 'now') . '</div>';
}

function renderStatCard(string $label, int|float $value, ?int $previous, string $hint, string $href = '', string $tone = 'neutral', string $icon = '', string $title = ''): void
{
    $tag     = $href !== '' ? 'a' : 'div';
    $classes = 'metric-card' . ($href !== '' ? ' records-stat-link' : '') . ($value == 0 ? ' is-zero' : '');
    $attrs   = ' class="' . $classes . '" data-tone="' . htmlspecialchars($tone, ENT_QUOTES) . '"' . ($href !== '' ? ' href="' . htmlspecialchars($href, ENT_QUOTES) . '"' : '') . ($title !== '' ? ' title="' . htmlspecialchars($title, ENT_QUOTES) . '"' : '');
    echo '<' . $tag . $attrs . '>';
    echo '<div class="records-stat-head">';
    if ($icon !== '') {
        echo '<span class="records-stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#' . htmlspecialchars($icon, ENT_QUOTES) . '"></use></svg></span>';
    }
    echo '<div class="metric-card-label">' . htmlspecialchars($label) . '</div>';
    echo '</div>';
    echo '<div class="records-stat-main">';
    echo '<div class="metric-card-value">' . number_format($value, is_float($value) ? 1 : 0) . '</div>';
    echo statDelta($value, $previous);
    echo '</div>';
    echo statCompare($value, $previous);
    echo '<div class="records-stat-hint">' . htmlspecialchars($hint) . '</div>';
    echo '</' . $tag . '>';
}

function renderDataNote(string $text, string $href, string $linkLabel): void
{
    echo '<div class="records-data-note">'
        . '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#info-icon"></use></svg>'
        . '<p>' . $text . '</p>'
        . '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '">' . htmlspecialchars($linkLabel) . '</a>'
        . '</div>';
}

function renderColumnChart(array $rows, string $emptyText, string $unit): void
{
    if (! $rows) {
        echo '<div class="records-card-empty">' . htmlspecialchars($emptyText) . '</div>';
        return;
    }
    echo '<div class="records-column-chart">';
    foreach ($rows as $r) {
        $hover = htmlspecialchars($r['label'] . ': ' . number_format($r['total']) . ' ' . pluralize($r['total'], $unit), ENT_QUOTES);
        echo '<div class="records-column-col" title="' . $hover . '">';
        echo '<div class="records-column-count">' . number_format($r['total']) . '<small>' . $r['share'] . '%</small></div>';
        echo '<div class="records-column-track"><div class="records-column-fill" style="--column-pct: ' . $r['bar'] . '%"></div></div>';
        echo '<div class="records-column-label">' . htmlspecialchars($r['label']) . '</div>';
        echo '</div>';
    }
    echo '</div>';
}

function renderDonut(array $rows, string $centerLabel, string $emptyText): void
{
    if (! $rows) {
        echo '<div class="records-card-empty">' . htmlspecialchars($emptyText) . '</div>';
        return;
    }
    $sum    = array_sum(array_column($rows, 'total'));
    $radius = 15.91549431;
    $cursor = 0.0;
    $segs   = '';
    $legend = '';
    foreach (array_values($rows) as $i => $row) {
        $pct   = $sum > 0 ? ($row['total'] / $sum) * 100 : 0;
        $tone  = $row['modifier'] !== '' ? $row['modifier'] : (string) (($i % 8) + 1);
        $segs .= sprintf(
            '<circle class="records-donut-seg records-donut-seg--%s" cx="21" cy="21" r="%F" stroke-dasharray="%F %F" stroke-dashoffset="%F"></circle>',
            $tone,
            $radius,
            $pct,
            100 - $pct,
            25 - $cursor
        );
        $legend .= '<li><span class="records-legend-dot records-donut-seg--' . $tone . '"></span>'
            . '<span class="records-legend-label">' . htmlspecialchars($row['label']) . '</span>'
            . '<span class="records-legend-value">' . number_format($row['total']) . '<small>' . $row['share'] . '%</small></span></li>';
        $cursor += $pct;
    }
    echo '<div class="records-donut-body">';
    echo '<div class="records-donut">';
    echo '<svg viewBox="0 0 42 42" role="img" aria-label="' . htmlspecialchars($centerLabel) . ' breakdown" focusable="false">';
    echo '<circle class="records-donut-ring" cx="21" cy="21" r="' . sprintf('%F', $radius) . '"></circle>' . $segs . '</svg>';
    echo '<div class="records-donut-center"><strong>' . number_format($sum) . '</strong><span>' . htmlspecialchars($centerLabel) . '</span></div>';
    echo '</div>';
    echo '<ul class="records-legend">' . $legend . '</ul>';
    echo '</div>';
}

$speciesRows        = statBarRows($stats['species_breakdown'], count($stats['species_breakdown']), false);
$schoolRows         = statBarRows($stats['school_breakdown']);
$researcherRows     = statBarRows($stats['researcher_type_breakdown']);
$sexRows            = statBarRows($stats['sex_breakdown']);
$revisionProtoRows  = statBarRows($stats['revisions_by_protocol'], count($stats['revisions_by_protocol']), false);
$revisionPiRows     = statBarRows($stats['revisions_by_pi'], 8, false);

$periodLabel  = $period['label'] ?? 'All time';
$periodParams = array_filter([
    'period' => $periodPreset,
    'from'   => $periodPreset === 'custom' ? $periodFrom : '',
    'to'     => $periodPreset === 'custom' ? $periodTo : '',
], fn($v) => $v !== '');

$activeTab = ($_GET['tab'] ?? '') === 'statistics' ? 'statistics' : 'records';

// ===== Page URL helper =====
function pageUrl(int $p, string $search, string $school, string $animalType, string $sex, string $researcherType, string $sort, string $status = ''): string
{
    $periodFilter = ($_GET['in_period'] ?? '') === '1' ? array_intersect_key($_GET, array_flip(['in_period', 'period', 'from', 'to'])) : [];

    return '?' . http_build_query(array_filter([
        'page'   => $p,
        'search' => $search,
        'school' => $school,
        'animal' => $animalType,
        'sex' => $sex,
        'rtype'  => $researcherType,
        'status' => $status,
        'completion' => ($_GET['completion'] ?? '') === 'incomplete' ? 'incomplete' : '',
        'sort'   => $sort !== 'newest' ? $sort : '',
    ], fn($v) => $v !== '' && $v !== 1 || is_string($v)) + $periodFilter);
}

$renderOptions = fn(array $list): string => implode('', array_map(fn($item) => '<option>' . htmlspecialchars($item) . '</option>', $list));
?>

<link rel="stylesheet" href="<?= asset_css('pagination.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/personnel-home.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/records.css') ?>">
<link rel="stylesheet" href="<?= asset_css('tabs.css') ?>">
<link rel="stylesheet" href="<?= asset_css('researcher-modal.css') ?>">
<link rel="stylesheet" href="<?= asset_css('protocol-modals.css') ?>">

<div class="body">
    <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

    <!-- ===== Records page ===== -->
    <main class="main-content" id="main-content" tabindex="-1">

        <div class="page-banner">
            <div class="page-banner-text">
                <h1>Records and Statistics</h1>
                <p class="page-subtitle">Reviewed protocols on record and summary figures for a chosen period</p>
            </div>
        </div>

        <?php
        $toastSuccess = $flash_success;
        $toastError   = $flash_error;
        include dirname(__DIR__) . '/includes/flash.php';
        ?>

        <!-- ===== Tabs: Records / Statistics ===== -->
        <div class="tab-strip" role="tablist" aria-label="Records sections" data-tab-panels="recordsTabPanels" data-tab-param="tab">
            <button type="button" role="tab" class="has-guide" id="tab-records" data-tab="records"
                aria-selected="<?= $activeTab === 'records' ? 'true' : 'false' ?>"
                aria-controls="panel-records">Records</button>
            <?php $tabGuideId = 'records';
            $tabGuideLabel = 'Records';
            include dirname(__DIR__) . '/includes/tab-guide.php'; ?>
            <button type="button" role="tab" class="has-guide" id="tab-statistics" data-tab="statistics"
                aria-selected="<?= $activeTab === 'statistics' ? 'true' : 'false' ?>"
                aria-controls="panel-statistics">Statistics</button>
            <?php $tabGuideId = 'statistics';
            $tabGuideLabel = 'Statistics';
            include dirname(__DIR__) . '/includes/tab-guide.php'; ?>
        </div>

        <div id="recordsTabPanels">
            <div class="tab-panel" id="panel-records" role="tabpanel" aria-labelledby="tab-records"
                data-tab-panel="records" <?= $activeTab === 'records' ? '' : 'hidden' ?>>
                <!-- ===== Page header ===== -->
                <div class="dashboard-page-header records-page-header">
                    <div class="inbox-search-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#search-icon">
                        </svg>
                        <input type="text"
                            name="search"
                            id="recordSearch"
                            class="inbox-search-input"
                            placeholder="Search records…"
                            value="<?= htmlspecialchars($search, ENT_QUOTES) ?>"
                            autocomplete="off"
                            form="recordsFilterForm">
                        <button type="button" class="inbox-search-clear <?= $search ? 'visible' : '' ?>" id="clearSearch" aria-label="Clear search"><svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#close-icon" />
                            </svg></button>
                    </div>

                    <?php if ($role === 'staff'): ?>
                        <button class="row-btn row-btn-primary" id="addRecordBtn" type="button">
                            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#add-icon">
                            </svg>
                            Add Record
                        </button>
                    <?php endif; ?>
                </div>

                <!-- ===== Filters ===== -->
                <form method="GET" action="" id="recordsFilterForm">

                    <button type="button" class="records-toggle records-filters-toggle" aria-expanded="<?= $hasFilters ? 'true' : 'false' ?>" aria-controls="recordsFilters">
                        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#filter-icon" />
                        </svg>
                        Filters
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#chev-down-icon" />
                        </svg>
                    </button>

                    <div class="records-filters-row<?= $hasFilters ? ' open' : '' ?>" id="recordsFilters">
                        <p class="sort-filter-label">
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#filter-icon" />
                            </svg>
                            Filter by:
                        </p>
                        <select name="school" class="records-filter-select" aria-label="Filter by school" onchange="this.form.submit()">
                            <option value="">All Schools</option>
                            <?php foreach ($schools as $s): ?>
                                <option value="<?= htmlspecialchars($s, ENT_QUOTES) ?>" <?= $school === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="animal" class="records-filter-select" aria-label="Filter by animal type" onchange="this.form.submit()">
                            <option value="">All Animal Types</option>
                            <?php foreach ($animalTypes as $a): ?>
                                <option value="<?= htmlspecialchars($a, ENT_QUOTES) ?>" <?= $animalType === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="sex" class="records-filter-select" aria-label="Filter by sex" onchange="this.form.submit()">
                            <option value="">All Sexes</option>
                            <?php foreach ($sexes as $g): ?>
                                <option value="<?= htmlspecialchars($g, ENT_QUOTES) ?>" <?= $sex === $g ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="rtype" class="records-filter-select" aria-label="Filter by researcher type" onchange="this.form.submit()">
                            <option value="">All Researcher Types</option>
                            <?php foreach ($researcherTypes as $r): ?>
                                <option value="<?= htmlspecialchars($r, ENT_QUOTES) ?>" <?= $researcherType === $r ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="status" class="records-filter-select" aria-label="Filter by status" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <?php foreach (RecordModel::STATUSES as $st): ?>
                                <option value="<?= htmlspecialchars($st, ENT_QUOTES) ?>" <?= $status === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="completion" class="records-filter-select" aria-label="Filter by completion" onchange="this.form.submit()">
                            <option value="">All Records</option>
                            <option value="incomplete" <?= $incompleteOnly ? 'selected' : '' ?>>Needs Completion</option>
                        </select>

                        <?php if ($hasFilters): ?>
                            <a href="<?= ROOT ?>/personnel/records" class="row-btn records-clear-btn">
                                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#close-icon" />
                                </svg>
                                Clear all
                            </a>
                        <?php endif; ?>

                        <div class="records-sort-group">
                            <p class="sort-filter-label">
                                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#sort-icon" />
                                </svg>
                                Sort by:
                            </p>
                            <select name="sort" class="records-filter-select" aria-label="Sort records" onchange="this.form.submit()">
                                <?php foreach (RecordModel::sortOptions() as $key => $opt): ?>
                                    <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= htmlspecialchars($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <input type="hidden" name="page" value="1">
                    <?php if ($listPeriod): ?>
                        <input type="hidden" name="in_period" value="1">
                        <?php foreach ($periodParams as $periodKey => $periodValue): ?>
                            <input type="hidden" name="<?= $periodKey ?>" value="<?= htmlspecialchars($periodValue, ENT_QUOTES) ?>">
                        <?php endforeach; ?>
                    <?php endif; ?>
                </form>

                <?php if ($listPeriod): ?>
                    <div class="records-filter-note">
                        <span>Showing records for <?= htmlspecialchars($listPeriod['label']) ?></span>
                        <a href="<?= ROOT ?>/personnel/records">Show all records</a>
                    </div>
                <?php endif; ?>

                <?php
                // ===== Clickable column headers =====
                // Column key => header class. Clicking toggles asc/desc; labels come from RecordModel::SORT_COLUMNS.
                $sortColumns = [
                    'ipn' => 'col-ref',
                    'ar' => 'col-ar',
                    'title' => 'col-title',
                    'school' => 'col-school',
                    'animal' => 'col-animal',
                    'count' => 'col-count',
                    'researcher' => 'col-pi',
                    'sex' => 'col-sex',
                    'rtype' => 'col-rtype',
                    'adviser' => 'col-adviser',
                    'vet' => 'col-vet',
                    'duration' => 'col-duration',
                    'date_released' => 'col-date',
                    'date_added' => 'col-date',
                    'received' => 'col-recv',
                    'status' => 'col-status',
                ];
                $sortHeader = function (string $col, string $class) use ($sort, $search, $school, $animalType, $sex, $researcherType, $status): string {
                    $label     = RecordModel::SORT_COLUMNS[$col][1];
                    $active    = in_array($sort, [$col . '_asc', $col . '_desc'], true);
                    $nextSort  = match ($sort) {
                        $col . '_asc'  => $col . '_desc',
                        $col . '_desc' => 'newest',
                        default        => $col . '_asc',
                    };
                    $ariaSort  = $active ? ($sort === $col . '_asc' ? 'ascending' : 'descending') : 'none';
                    $icon      = $sort === $col . '_desc' ? 'chev-down-icon' : 'chev-up-icon';
                    $href      = htmlspecialchars(pageUrl(1, $search, $school, $animalType, $sex, $researcherType, $nextSort, $status), ENT_QUOTES);
                    $iconHtml  = $active ? '<svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#' . $icon . '" /></svg>' : '';
                    return '<th class="' . $class . '" aria-sort="' . $ariaSort . '"><a class="records-sort-link' . ($active ? ' is-active' : '') . '" href="' . $href . '">'
                        . htmlspecialchars($label) . $iconHtml . '</a></th>';
                };
                ?>

                <div class="records-table-toolbar">
                    <p class="pagination-info records-count"><?= number_format($total) ?> <?= pluralize($total, 'record') ?></p>
                    <div class="records-columns">
                        <button type="button" class="records-columns-toggle" id="recordsColumnsToggle" aria-expanded="false" aria-controls="recordsColumnsMenu">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <rect width="18" height="18" x="3" y="3" rx="2" />
                                <path d="M9 3v18" />
                                <path d="M15 3v18" />
                            </svg>
                            Columns
                        </button>
                        <div class="records-columns-menu" id="recordsColumnsMenu" hidden>
                            <?php foreach (array_keys($sortColumns) as $columnIndex => $col): ?>
                                <?php if ($col === 'title') continue; ?>
                                <label class="records-columns-option">
                                    <input type="checkbox" value="<?= $col ?>" data-column-index="<?= $columnIndex ?>" checked>
                                    <?= htmlspecialchars(RecordModel::SORT_COLUMNS[$col][1]) ?>
                                </label>
                            <?php endforeach; ?>
                            <label class="records-columns-option">
                                <input type="checkbox" value="file" data-column-index="<?= count($sortColumns) ?>" checked>
                                File
                            </label>
                            <button type="button" class="records-columns-reset" id="recordsColumnsReset">Show all columns</button>
                        </div>
                    </div>
                </div>

                <!-- ===== Table ===== -->
                <div class="protocol-table-wrap records-table-wrap">
                    <div class="protocol-table-scroll">
                        <table class="protocol-table records-table data-table">
                            <thead>
                                <tr>
                                    <?php foreach ($sortColumns as $col => $class): ?>
                                        <?= $sortHeader($col, $class) ?>
                                    <?php endforeach; ?>
                                    <th class="col-file">File</th>
                                    <th class="col-actions" aria-label="Actions"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($records) === 0): ?>
                                    <tr>
                                        <td colspan="<?= $colCount ?>">
                                            <div class="inbox-no-results">
                                                <?php if ($hasFilters): ?>
                                                    No records match your search or filters.
                                                <?php else: ?>
                                                    No records yet. Records are added automatically when a protocol is marked <strong>Reviewed</strong>. You may also add entries manually.
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($records as $i => $r): ?>
                                        <?php
                                        $rowStatus     = in_array($r['status'] ?? '', RecordModel::STATUSES, true) ? $r['status'] : RecordModel::DEFAULT_STATUS;
                                        $durationLabel = RecordModel::formatDuration($r['research_duration_start'] ?? null, $r['research_duration_end'] ?? null);
                                        $releasedLabel = $r['date_released'] ? date(DATE_FORMAT, strtotime($r['date_released'])) : '';
                                        $addedLabel    = !empty($r['created_at']) ? date(DATE_FORMAT, strtotime($r['created_at'])) : '';
                                        $viewDetails   = [
                                            ['IPN', $r['reference_no'] ?? ''],
                                            ['AR No.', $r['ar_number'] ?? ''],
                                            ['Title of Research', $r['title_of_research'] ?? ''],
                                            ['School', $r['school'] ?? ''],
                                            ['Animal Type', $r['animal_type'] ?? ''],
                                            ['Animal Count', $r['animal_count'] ?? ''],
                                            ['Researcher', $r['principal_investigator'] ?? ''],
                                            ['Researcher Sex', $r['sex'] ?? ''],
                                            ['Researcher Type', $r['researcher_type'] ?? ''],
                                            ['Research Adviser', $r['research_adviser'] ?? ''],
                                            ['Veterinarian', $r['veterinarian'] ?? ''],
                                            ['Research Duration', $durationLabel],
                                            ['Date Released', $releasedLabel],
                                            ['Date Added', $addedLabel],
                                            ['Received By', $r['received_by'] ?? ''],
                                            ['Status', $rowStatus],
                                            ['Protocol File', $r['file_original_name'] ?? ''],
                                        ];
                                        ?>
                                        <tr>
                                            <td class="records-detail date-cell records-ref"><?= htmlspecialchars($r['reference_no'] ?? '') ?></td>
                                            <td class="records-detail date-cell records-ar"><?= htmlspecialchars($r['ar_number'] ?? '') ?></td>
                                            <td class="records-title-cell">
                                                <div class="protocol-title-cell">
                                                    <?= htmlspecialchars($r['title_of_research']) ?>
                                                    <span class="pending-sync-badge" data-pending-sync="record:<?= (int)$r['id'] ?>" hidden></span>
                                                </div>
                                            </td>
                                            <td class="researcher-cell" data-label="School"><?= htmlspecialchars($r['school'] ?? '') ?></td>
                                            <td class="records-detail date-cell" data-label="Animal Type"><?= htmlspecialchars($r['animal_type'] ?? '') ?></td>
                                            <td class="records-detail date-cell" data-label="Count"><?= htmlspecialchars($r['animal_count'] ?? '') ?></td>
                                            <td class="researcher-cell" data-label="Researcher"><?= htmlspecialchars($r['principal_investigator'] ?? '') ?></td>
                                            <td class="records-detail date-cell" data-label="Researcher Sex"><?= htmlspecialchars($r['sex'] ?? '') ?></td>
                                            <td class="records-detail date-cell" data-label="Researcher Type"><?= htmlspecialchars($r['researcher_type'] ?? '') ?></td>
                                            <td class="records-detail researcher-cell" data-label="Research Adviser"><?= htmlspecialchars($r['research_adviser'] ?? '') ?></td>
                                            <td class="records-detail researcher-cell" data-label="Veterinarian"><?= htmlspecialchars($r['veterinarian'] ?? '') ?></td>
                                            <td class="records-detail date-cell" data-label="Duration"><?= htmlspecialchars($durationLabel) ?><?php if (!empty($r['user_id']) && !empty($r['research_duration_end']) && strtotime($r['research_duration_end']) < strtotime('today')): ?><span class="records-expired-tag">Expired</span><?php endif; ?></td>
                                            <td class="records-detail date-cell" data-label="Date Released"><?= $releasedLabel ?></td>
                                            <td class="records-detail date-cell" data-label="Date Added"><?= $addedLabel ?></td>
                                            <td class="records-detail researcher-cell" data-label="Received By"><?= htmlspecialchars($r['received_by'] ?? '') ?></td>
                                            <td class="date-cell" data-label="Status"><span class="records-status records-status-<?= strtolower($rowStatus) ?>"><?= htmlspecialchars($rowStatus) ?></span></td>
                                            <td class="records-detail date-cell" data-label="File"><?php if (!empty($r['file_path'])): ?><a class="researcher-name-link" href="<?= ROOT ?>/personnel/records_file/<?= (int)$r['id'] ?>/<?= rawurlencode($r['file_original_name'] ?? 'record.pdf') ?>" target="_blank" rel="noopener noreferrer">View file</a><?php endif; ?></td>
                                            <td class="records-details-cell">
                                                <button type="button" class="records-toggle records-details-toggle" aria-expanded="false">
                                                    Details
                                                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                        <use href="#chev-down-icon" />
                                                    </svg>
                                                </button>
                                            </td>
                                            <td class="actions-cell">
                                                <div class="row-actions">
                                                    <?php if ($isStaff): ?>
                                                        <button type="button" class="row-btn edit-record-btn"
                                                            data-id="<?= (int)$r['id'] ?>"
                                                            data-record="<?= htmlspecialchars(json_encode($r, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES, 'UTF-8') ?>"
                                                            aria-label="Edit record">
                                                            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                                <use href="#edit-icon">
                                                            </svg>
                                                        </button>

                                                        <button type="button" class="row-btn delete-record-btn"
                                                            data-id="<?= (int)$r['id'] ?>"
                                                            data-title="<?= htmlspecialchars(mb_substr($r['title_of_research'], 0, 60), ENT_QUOTES) ?>"
                                                            aria-label="Delete record">
                                                            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                                <use href="#trash-icon">
                                                            </svg>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="row-btn view-record-btn"
                                                        data-details="<?= htmlspecialchars(json_encode($viewDetails, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-file-url="<?= !empty($r['file_path']) ? ROOT . '/personnel/records_file/' . (int)$r['id'] . '/' . rawurlencode($r['file_original_name'] ?? 'record.pdf') : '' ?>"
                                                        aria-label="View record details">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                            <use href="#eye-icon">
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ===== Pagination ===== -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-bar pagination-bar--pinned">
                        <div class="pagination-info">
                            Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?> records
                        </div>
                        <div class="pagination-buttons">
                            <?php if ($page > 1): ?>
                                <a href="<?= pageUrl(1, $search, $school, $animalType, $sex, $researcherType, $sort, $status) ?>" class="pagination-btn" title="First">«</a>
                                <a href="<?= pageUrl($page - 1, $search, $school, $animalType, $sex, $researcherType, $sort, $status) ?>" class="pagination-btn" title="Previous">‹</a>
                            <?php else: ?>
                                <span class="pagination-btn" style="opacity:.35;cursor:default">«</span>
                                <span class="pagination-btn" style="opacity:.35;cursor:default">‹</span>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 2);
                            $end   = min($totalPages, $page + 2);
                            if ($start > 1) echo '<span class="pagination-ellipsis">…</span>';
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <a href="<?= pageUrl($i, $search, $school, $animalType, $sex, $researcherType, $sort, $status) ?>"
                                    class="pagination-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor;
                            if ($end < $totalPages) echo '<span class="pagination-ellipsis">…</span>';
                            ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= pageUrl($page + 1, $search, $school, $animalType, $sex, $researcherType, $sort, $status) ?>" class="pagination-btn" title="Next">›</a>
                                <a href="<?= pageUrl($totalPages, $search, $school, $animalType, $sex, $researcherType, $sort, $status) ?>" class="pagination-btn" title="Last">»</a>
                            <?php else: ?>
                                <span class="pagination-btn" style="opacity:.35;cursor:default">›</span>
                                <span class="pagination-btn" style="opacity:.35;cursor:default">»</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <div class="tab-panel" id="panel-statistics" role="tabpanel" aria-labelledby="tab-statistics"
                data-tab-panel="statistics" <?= $activeTab === 'statistics' ? '' : 'hidden' ?>>
                <?php
                $previousTotals  = $comparison['totals'] ?? null;
                $inboxUrl        = ROOT . '/personnel/home?status=';
                $recordsUrl      = ROOT . '/personnel/records?' . http_build_query($period ? array_merge(['tab' => 'records', 'in_period' => 1], $periodParams) : ['tab' => 'records']);
                $excludedCount   = $period ? (int) $stats['excluded_by_period'] : 0;
                $incompleteCount = (int) $stats['incomplete_count'];
                $periodRecords   = (int) $stats['total_records'];
                ?>

                <!-- ===== Period filter ===== -->
                <form method="GET" action="" class="records-period-bar" id="statsPeriodForm">
                    <input type="hidden" name="tab" value="statistics">

                    <fieldset class="records-period-presets">
                        <legend class="sr-only">Statistics period</legend>
                        <?php foreach (RecordModel::PERIOD_PRESETS as $key => $label): ?>
                            <label class="records-period-chip">
                                <input type="radio" name="period" value="<?= $key ?>" <?= $periodPreset === $key ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <span class="records-period-custom" id="statsPeriodCustom" <?= $periodPreset === 'custom' ? '' : 'hidden' ?>>
                        <input type="date" name="from" class="records-filter-select" aria-label="From date" value="<?= htmlspecialchars($periodFrom, ENT_QUOTES) ?>">
                        <span class="records-date-range-sep">to</span>
                        <input type="date" name="to" class="records-filter-select" aria-label="To date" value="<?= htmlspecialchars($periodTo, ENT_QUOTES) ?>">
                        <button type="submit" class="row-btn records-period-apply">Apply</button>
                    </span>

                    <?php if (($period && $periodPreset !== 'custom') || $comparison): ?>
                        <div class="records-period-summary">
                            <?php if ($period && $periodPreset !== 'custom'): ?>
                                <span class="records-period-range records-period-range--current">
                                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <use href="#calendar-icon" />
                                    </svg>
                                    <?= htmlspecialchars($periodLabel) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($comparison): ?>
                                <span class="records-period-range">Compared with <?= htmlspecialchars($comparison['period']['label']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <a class="row-btn records-export-btn"
                        href="<?= ROOT ?>/personnel/records_export?<?= http_build_query($periodParams) ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#download-icon">
                        </svg>
                        Download as Excel
                    </a>
                </form>

                <?php if ($excludedCount > 0) {
                    renderDataNote(
                        number_format($excludedCount) . ' ' . pluralize($excludedCount, 'record has', 'records have') . ' no release or review date, so ' . ($excludedCount === 1 ? 'it is' : 'they are') . ' not counted in this period.',
                        ROOT . '/personnel/records?tab=records',
                        'View all records'
                    );
                } ?>

                <!-- ===== Review activity ===== -->
                <div class="records-section-head">
                    <h2 class="records-section-title">Review activity</h2>
                    <p class="records-section-sub">What the committee handled in this period</p>
                </div>
                <div class="metrics-row records-metrics">
                    <?php
                    renderStatCard('Protocols Reviewed', $stats['reviewed'], $previousTotals['reviewed'] ?? null, 'Finished review', $inboxUrl . 'reviewed', 'reviewed', 'review-icon');
                    renderStatCard(
                        'Protocols Revised',
                        $stats['revised'],
                        $previousTotals['revised'] ?? null,
                        $stats['revised'] > 0
                            ? number_format($stats['revision_submissions']) . ' ' . pluralize($stats['revision_submissions'], 'resubmission') . ' in total'
                            : 'No resubmissions',
                        $inboxUrl . 'returned-for-revision',
                        'revised',
                        'undo-icon',
                        'One protocol can be resubmitted several times, so resubmissions can be higher than protocols revised.'
                    );
                    renderStatCard('Protocols Endorsed', $stats['endorsed'], $previousTotals['endorsed'] ?? null, 'Endorsed by administrative staff', $inboxUrl . 'endorsed', 'endorsed', 'timeline-endorse-icon');
                    renderStatCard('Signed by IACUC Chairperson', $stats['signed'], $previousTotals['signed'] ?? null, 'Signed scans uploaded', $inboxUrl . 'endorsed', 'signed', 'shield-check-icon');
                    ?>
                </div>

                <!-- ===== Records and clearances ===== -->
                <div class="records-section-head">
                    <h2 class="records-section-title">Records and clearances</h2>
                    <p class="records-section-sub">Filed records, clearances, and animal use</p>
                </div>
                <div class="metrics-row records-metrics">
                    <?php
                    renderStatCard('Total Records', $stats['total_records'], $previousTotals['total_records'] ?? null, number_format($stats['total_records_all']) . ' all time', $recordsUrl, 'neutral', 'protocols-icon');
                    renderStatCard('Average Revisions per Protocol', $stats['avg_revisions'], null, 'Resubmissions per revised protocol', $inboxUrl . 'returned-for-revision', 'revised', 'undo-icon');
                    renderStatCard('Clearances Received', $stats['clearances_returned'], $previousTotals['clearances_returned'] ?? null, 'Clearance documents attached', $inboxUrl . 'approved', 'signed', 'clearance-page-icon');
                    renderStatCard('Total Animals Used', $stats['total_animals'], $previousTotals['total_animals'] ?? null, 'Avg ' . number_format($stats['avg_animals_per_record'], 1) . ' per record', $recordsUrl, 'animals', 'beaker-icon');
                    ?>
                </div>

                <!-- ===== Breakdowns ===== -->
                <div class="records-section-head">
                    <h2 class="records-section-title">Breakdowns</h2>
                    <p class="records-section-sub">Who submitted, and what they are working with</p>
                </div>
                <?php if ($incompleteCount > 0) {
                    renderDataNote(
                        number_format($incompleteCount) . ' of ' . number_format($periodRecords) . ' ' . pluralize($periodRecords, 'record') . ' in this period ' . pluralize($incompleteCount, 'is', 'are') . ' missing an animal count, species, or researcher type, so those charts only show what has been filled in.',
                        $recordsUrl . '&completion=incomplete',
                        'View records'
                    );
                } ?>
                <div class="records-charts-grid">
                    <div class="metric-card records-bar-card">
                        <div class="metric-card-label">Animals Used by Species</div>
                        <p class="records-card-hint">Total animals across records in this period</p>
                        <?php if (count($speciesRows) > 5): ?>
                            <?php renderBarList($speciesRows, 'No animal counts recorded in this period.'); ?>
                        <?php else: ?>
                            <?php renderColumnChart($speciesRows, 'No animal counts recorded in this period.', 'animal'); ?>
                        <?php endif; ?>
                    </div>

                    <div class="metric-card records-bar-card records-chart-wide">
                        <div class="metric-card-label">Protocols by School</div>
                        <p class="records-card-hint">Where researchers come from</p>
                        <?php renderDonut($schoolRows, 'protocols', 'No school data in this period.'); ?>
                    </div>

                    <div class="metric-card records-bar-card">
                        <div class="metric-card-label">Protocols by Researcher Sex</div>
                        <p class="records-card-hint">Recorded sex of the researcher</p>
                        <?php renderDonut($sexRows, 'protocols', 'No sex data in this period.'); ?>
                    </div>

                    <div class="metric-card records-bar-card">
                        <div class="metric-card-label">Protocols by Research Type</div>
                        <p class="records-card-hint">Student, faculty, staff, or researcher</p>
                        <?php renderDonut($researcherRows, 'protocols', 'No researcher type data in this period.'); ?>
                    </div>

                    <div class="metric-card records-bar-card records-chart-wide" data-tone="revised">
                        <div class="metric-card-label">Revisions per Protocol</div>
                        <p class="records-card-hint">Protocols reviewed or resubmitted in this period, most resubmitted first</p>
                        <?php renderBarList(array_slice($revisionProtoRows, 0, 8), 'No protocols in this period.', false, true); ?>
                        <?php if (count($revisionProtoRows) > 8): ?>
                            <details class="records-more">
                                <summary>Show <?= number_format(count($revisionProtoRows) - 8) ?> more</summary>
                                <?php renderBarList(array_slice($revisionProtoRows, 8), '', false, true); ?>
                            </details>
                        <?php endif; ?>
                    </div>

                    <div class="metric-card records-bar-card" data-tone="revised">
                        <div class="metric-card-label">Revisions per PI</div>
                        <p class="records-card-hint">Resubmissions by principal investigator</p>
                        <?php renderBarList($revisionPiRows, 'No revisions in this period.', false); ?>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<?php
$guideId    = 'records';
$guideSteps = [
    ['Records', 'This table holds every protocol record. Records are added automatically when a protocol is marked **Reviewed**. Let us walk through it.'],
    ['Search', 'Type here to search the records.', '#panel-records .inbox-search-wrap'],
];
if ($isStaff) {
    $guideSteps[] = ['Add Record', 'Add an entry to the table by hand.', '#addRecordBtn'];
}
$guideSteps[] = ['Filters and sort', 'Narrow the table by school, animal type, sex, researcher type, status, or **Needs Completion**, and change the order with **Sort** or by clicking a column heading. A third click on the same heading resets the order. **Clear all** appears when a filter is on.', '#recordsFilterForm .records-filters-row'];
$guideSteps[] = ['Columns', 'Hide the columns you do not use so the table fits your screen. Your choice is remembered on this computer. **Show all columns** brings them back.', '#recordsColumnsToggle'];
if ($records) {
    $guideSteps[] = ['Records table', 'Each row is one record: reference number, title, school, animal type, researcher, dates, status, and a link to the protocol file.', '.records-table-wrap'];
    $guideSteps[] = ['Row actions', $isStaff ? 'Edit or delete a record, or see all of its details with the eye icon.' : 'See all the details of a record with the eye icon.', '.records-table .row-actions:first-visible'];
}
if ($totalPages > 1) {
    $guideSteps[] = ['Pagination', 'Move between pages of records.', '.pagination-bar'];
}
$guideSteps[] = ['Statistics tab', 'Switch to **Statistics** for totals and charts over a period you choose.', '#tab-statistics'];
include dirname(__DIR__) . '/includes/guide.php';

$guideId    = 'statistics';
$guideSteps = [
    ['Statistics', 'See review activity and record totals for a period you choose. Let us walk through it.'],
    ['Period', 'Choose the period to report on, including a custom date range. When available, the figures are compared with the previous period.', '#statsPeriodForm'],
    ['Cards', 'The cards count protocols reviewed, revised, endorsed, and signed, plus records, average revisions, clearances received, and animals used. Most cards open the matching list when pressed.', '.records-metrics'],
    ['Breakdowns', 'Charts break the period down by animals used, school, researcher sex, researcher type, and resubmissions.', '.records-charts-grid'],
    ['Download as Excel', 'Download the statistics and records for the chosen period as an Excel file.', '.records-export-btn'],
];
include dirname(__DIR__) . '/includes/guide.php';
?>
<?php include dirname(__DIR__) . '/includes/person-options.php'; ?>

<script src="<?= asset_js('tabs.js') ?>" defer></script>

<!-- ===== ADD RECORD MODAL ===== -->
<div class="modal-backdrop" id="addModal" data-static data-confirm-discard role="dialog" aria-modal="true" aria-labelledby="addModalTitle">
    <div class="modal-card records-modal-card">
        <div class="modal-header">
            <span class="modal-header-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                    <use href="#add-icon" />
                </svg>
            </span>
            <p class="modal-title" id="addModalTitle">Add Record</p>
            <button type="button" class="modal-close" data-close="addModal" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
        </div>
        <div class="records-modal-body">
            <div class="alert error-messages" id="addError" hidden></div>
            <div class="records-form-grid">
                <div class="records-form-group records-form-full">
                    <label class="field-label" for="add_reference_no">IPN <span class="records-required">*</span></label>
                    <input type="text" id="add_reference_no" name="reference_no" placeholder="e.g. 000026" maxlength="6" inputmode="numeric" pattern="\d{6}">
                </div>
                <div class="records-form-group records-form-full">
                    <label class="field-label" for="add_ar_number">AR Number</label>
                    <input type="text" id="add_ar_number" name="ar_number" placeholder="From the BAI clearance">
                </div>
                <div class="records-form-group records-form-full">
                    <label class="field-label" for="add_title">Title of Research <span class="records-required">*</span></label>
                    <textarea id="add_title" name="title_of_research" rows="3" placeholder="Full research title"></textarea>
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="add_pi">Principal Investigator <span class="records-required">*</span></label>
                    <input type="text" id="add_pi" name="principal_investigator" placeholder="Full name">
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="add_school">School <span class="records-required">*</span></label>
                    <select id="add_school" name="school">
                        <option value="">Select</option>
                        <?= $renderOptions(UserModel::SCHOOLS) ?>
                    </select>
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="add_animal_type">Animal Type <span class="records-required">*</span></label>
                    <select id="add_animal_type" name="animal_type">
                        <option value="">Select</option>
                        <?= $renderOptions(RecordModel::ANIMAL_TYPES) ?>
                    </select>
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="add_animal_count">Animal Count <span class="records-required">*</span></label>
                    <input type="number" id="add_animal_count" name="animal_count" min="1" step="1" placeholder="1">
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="add_sex">Researcher Sex <span class="records-required">*</span></label>
                    <select id="add_sex" name="sex">
                        <option value="">Select</option>
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="add_researcher_type">Researcher Type <span class="records-required">*</span></label>
                    <select id="add_researcher_type" name="researcher_type">
                        <option value="">Select</option>
                        <?php include dirname(__DIR__) . '/includes/researcher-type-options.php'; ?>
                    </select>
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="add_research_adviser">Research Adviser</label>
                    <input type="text" id="add_research_adviser" name="research_adviser" list="adviser-options" placeholder="Full name">
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="add_veterinarian">Veterinarian</label>
                    <input type="text" id="add_veterinarian" name="veterinarian" list="veterinarian-options" placeholder="Full name">
                </div>
                <div class="records-form-group records-form-full">
                    <label class="field-label" for="add_research_duration_start">Research Duration</label>
                    <div class="records-date-range">
                        <input type="month" id="add_research_duration_start" name="research_duration_start" aria-label="Research duration start month">
                        <span class="records-date-range-sep">to</span>
                        <input type="month" id="add_research_duration_end" name="research_duration_end" aria-label="Research duration end month">
                    </div>
                    <span class="helper">The end month also serves as this clearance's expiry (valid until the last day of that month). Once it passes and the researcher has no other protocols being processed, their account is marked inactive automatically.</span>
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="add_date_released">Date Released</label>
                    <input type="date" id="add_date_released" name="date_released">
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="add_status">Status</label>
                    <select id="add_status" name="status">
                        <?php foreach (RecordModel::STATUSES as $st): ?>
                            <option value="<?= htmlspecialchars($st, ENT_QUOTES) ?>"><?= htmlspecialchars($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="records-form-group records-form-full">
                    <label class="field-label" for="add_received_by">Received By</label>
                    <input type="text" id="add_received_by" name="received_by" placeholder="Name of receiving officer">
                </div>
                <?php $filePrefix = 'add';
                include dirname(__DIR__) . '/includes/record-file-field.php'; ?>
            </div>
        </div>
        <div class="records-modal-footer">
            <button type="button" class="confirm-modal-btn confirm-modal-cancel" data-close="addModal">Cancel</button>
            <button type="button" class="confirm-modal-btn confirm-modal-ok" id="addRecordSave">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#check-icon" />
                </svg>
                Save Record
            </button>
        </div>
    </div>
</div>

<!-- ===== EDIT RECORD MODAL ===== -->
<div class="modal-backdrop" id="editModal" data-static data-confirm-discard role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
    <div class="modal-card records-modal-card">
        <div class="modal-header">
            <span class="modal-header-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                    <use href="#edit-icon" />
                </svg>
            </span>
            <p class="modal-title" id="editModalTitle">Edit Record</p>
            <button type="button" class="modal-close" data-close="editModal" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
        </div>
        <div class="records-modal-body">
            <div class="alert error-messages" id="editError" hidden></div>
            <div class="records-form-grid">
                <input type="hidden" id="edit_id">
                <div class="records-form-grid records-form-full records-form-staff">
                    <div class="records-form-group records-form-full">
                        <label class="field-label" for="edit_reference_no">IPN</label>
                        <input type="text" id="edit_reference_no" name="reference_no" placeholder="e.g. 000026" maxlength="6" inputmode="numeric" pattern="\d{6}">
                    </div>
                    <div class="records-form-group records-form-full">
                        <label class="field-label" for="edit_ar_number">AR Number</label>
                        <input type="text" id="edit_ar_number" name="ar_number" placeholder="From the BAI clearance">
                    </div>
                    <div class="records-form-group">
                        <label class="field-label" for="edit_date_released">Date Released</label>
                        <input type="date" id="edit_date_released" name="date_released">
                    </div>
                    <div class="records-form-group">
                        <label class="field-label" for="edit_status">Status</label>
                        <select id="edit_status" name="status">
                            <?php foreach (RecordModel::STATUSES as $st): ?>
                                <option value="<?= htmlspecialchars($st, ENT_QUOTES) ?>"><?= htmlspecialchars($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="records-form-group records-form-full">
                        <label class="field-label" for="edit_received_by">Received By</label>
                        <input type="text" id="edit_received_by" name="received_by">
                    </div>
                </div>
                <div class="records-form-group records-form-full">
                    <label class="field-label" for="edit_title">Title of Research <span class="records-required">*</span></label>
                    <textarea id="edit_title" name="title_of_research" rows="3"></textarea>
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="edit_pi">Principal Investigator <span class="records-required">*</span></label>
                    <input type="text" id="edit_pi" name="principal_investigator">
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="edit_school">School <span class="records-required">*</span></label>
                    <select id="edit_school" name="school">
                        <option value="">Select</option>
                        <?= $renderOptions(UserModel::SCHOOLS) ?>
                    </select>
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="edit_animal_type">Animal Type <span class="records-required">*</span></label>
                    <select id="edit_animal_type" name="animal_type">
                        <option value="">Select</option>
                        <?= $renderOptions(RecordModel::ANIMAL_TYPES) ?>
                    </select>
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="edit_animal_count">Animal Count <span class="records-required">*</span></label>
                    <input type="number" id="edit_animal_count" name="animal_count" min="1" step="1">
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="edit_sex">Researcher Sex <span class="records-required">*</span></label>
                    <select id="edit_sex" name="sex">
                        <option value="">Select</option>
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="edit_researcher_type">Researcher Type <span class="records-required">*</span></label>
                    <select id="edit_researcher_type" name="researcher_type">
                        <option value="">Select</option>
                        <?php include dirname(__DIR__) . '/includes/researcher-type-options.php'; ?>
                    </select>
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="edit_research_adviser">Research Adviser</label>
                    <input type="text" id="edit_research_adviser" name="research_adviser" list="adviser-options">
                </div>
                <div class="records-form-group">
                    <label class="field-label" for="edit_veterinarian">Veterinarian</label>
                    <input type="text" id="edit_veterinarian" name="veterinarian" list="veterinarian-options">
                </div>
                <div class="records-form-group records-form-full">
                    <label class="field-label" for="edit_research_duration_start">Research Duration</label>
                    <div class="records-date-range">
                        <input type="month" id="edit_research_duration_start" name="research_duration_start" aria-label="Research duration start month">
                        <span class="records-date-range-sep">to</span>
                        <input type="month" id="edit_research_duration_end" name="research_duration_end" aria-label="Research duration end month">
                    </div>
                    <span class="helper">The end month also serves as this clearance's expiry (valid until the last day of that month). Once it passes and the researcher has no other protocols being processed, their account is marked inactive automatically.</span>
                </div>
                <?php $filePrefix = 'edit';
                include dirname(__DIR__) . '/includes/record-file-field.php'; ?>
            </div>
        </div>
        <div class="records-modal-footer">
            <button type="button" class="confirm-modal-btn confirm-modal-cancel" data-close="editModal">Cancel</button>
            <button type="button" class="confirm-modal-btn confirm-modal-ok" id="editRecordSave">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#check-icon" />
                </svg>
                Save Changes
            </button>
        </div>
    </div>
</div>

<!-- ===== View record modal ===== -->
<div class="modal-backdrop" id="viewModal" data-static role="dialog" aria-modal="true" aria-labelledby="viewModalTitle">
    <div class="modal-card records-modal-card">
        <div class="modal-header">
            <span class="modal-header-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                    <use href="#eye-icon" />
                </svg>
            </span>
            <p class="modal-title" id="viewModalTitle">Record Details</p>
            <button type="button" class="modal-close" data-close="viewModal" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
        </div>
        <div class="records-modal-body">
            <div class="researcher-detail-rows records-view-rows" id="viewRows"></div>
        </div>
        <div class="records-modal-footer">
            <button type="button" class="confirm-modal-btn confirm-modal-cancel" data-close="viewModal">Close</button>
            <a class="confirm-modal-btn confirm-modal-ok" id="viewFileLink" href="#" target="_blank" rel="noopener noreferrer" hidden>
                Open protocol file
            </a>
        </div>
    </div>
</div>

<!-- ===== Modal scripts ===== -->
<script>
    (function() {
        const ROOT = '<?= ROOT ?>';
        const CSRF = '<?= htmlspecialchars($csrf, ENT_QUOTES) ?>';

        // ===== Modal helpers =====
        function openModal(id) {
            const modal = document.getElementById(id);
            modal.classList.add('open');
            const focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (focusable) focusable.focus();
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        document.querySelectorAll('[data-close]').forEach(btn => {
            btn.addEventListener('click', () => closeModal(btn.dataset.close));
        });
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
            backdrop.addEventListener('click', e => {
                if (e.target === backdrop) closeModal(backdrop.id);
            });
        });

        // ===== Search clear =====
        const searchInput = document.getElementById('recordSearch');
        const clearSearch = document.getElementById('clearSearch');
        if (searchInput && clearSearch) {
            clearSearch.addEventListener('click', () => {
                searchInput.value = '';
                document.getElementById('recordsFilterForm').submit();
            });
            searchInput.addEventListener('input', () => {
                clearSearch.classList.toggle('visible', searchInput.value.length > 0);
            });
        }

        // ===== Mobile toggles =====
        document.querySelectorAll('.records-filters-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const open = document.getElementById(btn.getAttribute('aria-controls')).classList.toggle('open');
                btn.setAttribute('aria-expanded', open);
            });
        });

        document.querySelectorAll('.records-details-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const open = btn.closest('tr').classList.toggle('details-open');
                btn.setAttribute('aria-expanded', open);
            });
        });

        // ===== AJAX helper =====
        function post(url, body) {
            body.csrf_token = CSRF;
            const fd = new FormData();
            Object.entries(body).forEach(([k, v]) => fd.append(k, v ?? ''));
            return fetch(ROOT + url, {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json());
        }

        function showErr(id, msg) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = msg;
            el.hidden = false;
        }

        function hideErr(id) {
            const el = document.getElementById(id);
            if (el) {
                el.hidden = true;
                el.textContent = '';
            }
        }

        let editOriginalIpn = '';
        let editHasSignedScan = false;
        let editSkipFields = [];

        // ===== REQUIRED STATISTICS FIELDS =====
        const STAT_FIELDS = [
            ['title', 'Title of research'],
            ['pi', 'Principal investigator'],
            ['school', 'School'],
            ['animal_type', 'Animal type'],
            ['animal_count', 'Animal count'],
            ['sex', 'Researcher sex'],
            ['researcher_type', 'Researcher type'],
        ];

        function missingStatField(prefix, skip = []) {
            const field = STAT_FIELDS.find(([key]) => !skip.includes(key) && !document.getElementById(prefix + '_' + key).value.trim());
            if (field) return field[1] + ' is required.';
            return Number(document.getElementById(prefix + '_animal_count').value) < 1 ? 'Animal count must be at least 1.' : '';
        }

        function setSelectValue(select, value) {
            select.querySelectorAll('[data-legacy]').forEach(option => option.remove());
            if (value && ![...select.options].some(option => option.value === value)) {
                const legacy = new Option(value, value);
                legacy.dataset.legacy = '';
                select.add(legacy);
            }
            select.value = value;
        }

        // ===== FILE FIELDS =====
        const MAX_FILE_BYTES = 10 * 1024 * 1024;
        const FILE_HINT = 'Optional. PDF or Word (Word is converted to PDF), max 10 MB.';

        function fileField(prefix) {
            const field = document.getElementById(prefix + '_file_field');
            const input = document.getElementById(prefix + '_protocol_file');
            const picker = field.querySelector('[data-file-picker]');
            const card = field.querySelector('[data-file-card]');
            const preview = field.querySelector('[data-file-preview]');
            const state = {
                savedName: '',
                savedUrl: '',
                removed: false,
                blobUrl: ''
            };

            function sync() {
                const chosen = input.files[0];
                const name = chosen ? chosen.name : state.removed ? '' : state.savedName;
                if (state.blobUrl) URL.revokeObjectURL(state.blobUrl);
                state.blobUrl = chosen?.type === 'application/pdf' ? URL.createObjectURL(chosen) : '';
                const previewUrl = chosen ? state.blobUrl : state.savedUrl;
                picker.classList.toggle('is-hidden', !!name);
                card.classList.toggle('is-hidden', !name);
                card.querySelector('[data-file-name]').textContent = name;
                card.querySelector('[data-file-sub]').textContent = chosen ? formatFileSize(chosen.size) + ' \u00b7 Not saved yet' : 'Current file';
                preview.classList.toggle('is-hidden', !previewUrl);
                preview.href = previewUrl || '#';
                field.querySelector('[data-file-note]').textContent = state.removed ? 'The current file will be removed when you save.' : FILE_HINT;
            }

            input.addEventListener('change', () => {
                state.removed = false;
                sync();
            });
            field.querySelector('[data-file-remove]').addEventListener('click', () => {
                if (input.files[0]) input.value = '';
                else state.removed = true;
                sync();
            });

            return {
                file: () => input.files[0],
                removed: () => state.removed,
                tooBig: () => input.files[0]?.size > MAX_FILE_BYTES,
                reset(savedName = '', savedUrl = '') {
                    input.value = '';
                    Object.assign(state, {
                        savedName,
                        savedUrl,
                        removed: false
                    });
                    sync();
                },
            };
        }
        const addFile = fileField('add');
        const editFile = fileField('edit');

        // ===== ADD =====
        const addRecordBtn = document.getElementById('addRecordBtn');
        if (addRecordBtn) {
            addRecordBtn.addEventListener('click', () => {
                hideErr('addError');
                document.getElementById('addModal').querySelectorAll('input,textarea,select').forEach(el => el.value = '');
                document.getElementById('add_status').value = 'Ongoing';
                document.getElementById('add_animal_count').value = 1;
                addFile.reset();
                openModal('addModal');
            });
        }

        const addRecordSave = document.getElementById('addRecordSave');
        if (addRecordSave) {
            addRecordSave.addEventListener('click', () => {
                hideErr('addError');
                const ref = document.getElementById('add_reference_no').value.trim();
                const title = document.getElementById('add_title').value.trim();
                if (!ref) {
                    showErr('addError', 'IPN is required.');
                    return;
                }
                if (!/^\d{6}$/.test(ref)) {
                    showErr('addError', 'IPN must be 6 digits, with the last 2 digits as the year (e.g. 000026).');
                    return;
                }
                const missing = missingStatField('add');
                if (missing) {
                    showErr('addError', missing);
                    return;
                }
                if (addFile.tooBig()) {
                    showErr('addError', 'The file is larger than the 10 MB limit.');
                    return;
                }

                post('/personnel/records_add', {
                    reference_no: ref,
                    ar_number: document.getElementById('add_ar_number').value,
                    title_of_research: title,
                    school: document.getElementById('add_school').value,
                    animal_type: document.getElementById('add_animal_type').value,
                    animal_count: document.getElementById('add_animal_count').value,
                    principal_investigator: document.getElementById('add_pi').value,
                    sex: document.getElementById('add_sex').value,
                    researcher_type: document.getElementById('add_researcher_type').value,
                    research_adviser: document.getElementById('add_research_adviser').value,
                    veterinarian: document.getElementById('add_veterinarian').value,
                    research_duration_start: document.getElementById('add_research_duration_start').value,
                    research_duration_end: document.getElementById('add_research_duration_end').value,
                    date_released: document.getElementById('add_date_released').value,
                    received_by: document.getElementById('add_received_by').value,
                    status: document.getElementById('add_status').value,
                    protocol_file: addFile.file(),
                }).then(data => {
                    if (data.queued) {
                        closeModal('addModal');
                        notifyQueued();
                    } else if (data.ok) {
                        closeModal('addModal');
                        sessionStorage.setItem('records_flash', 'Record added successfully.');
                        location.reload();
                    } else {
                        showErr('addError', data.message || 'Add failed.');
                    }
                }).catch(() => showErr('addError', 'Network error. Please try again.'));
            });
        }

        // ===== VIEW =====
        document.querySelectorAll('.view-record-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const rows = document.getElementById('viewRows');
                rows.replaceChildren();
                JSON.parse(btn.dataset.details).forEach(([label, value]) => {
                    const row = document.createElement('div');
                    row.className = 'researcher-detail-row';
                    const key = document.createElement('span');
                    key.className = 'researcher-detail-label';
                    key.textContent = label;
                    const val = document.createElement('span');
                    val.className = 'researcher-detail-value';
                    val.textContent = String(value ?? '').trim() || '-';
                    row.append(key, val);
                    rows.append(row);
                });
                const fileLink = document.getElementById('viewFileLink');
                fileLink.hidden = !btn.dataset.fileUrl;
                fileLink.href = btn.dataset.fileUrl || '#';
                openModal('viewModal');
            });
        });

        // ===== EDIT =====
        document.querySelectorAll('.edit-record-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                hideErr('editError');
                const id = btn.dataset.id;
                fetch(ROOT + '/personnel/records_get?id=' + encodeURIComponent(id))
                    .then(r => r.json())
                    .catch(() => ({
                        ok: true,
                        data: JSON.parse(btn.dataset.record)
                    }))
                    .then(data => {
                        if (!data.ok) {
                            showToast(data.message || 'Could not load record.', 'error');
                            return;
                        }
                        const d = data.data;
                        document.getElementById('edit_id').value = d.id;
                        document.getElementById('edit_reference_no').value = d.reference_no ?? '';
                        document.getElementById('edit_ar_number').value = d.ar_number ?? '';
                        editOriginalIpn = d.reference_no ?? '';
                        editHasSignedScan = !!d.has_signed_scan;
                        document.getElementById('edit_title').value = d.title_of_research ?? '';
                        document.getElementById('edit_pi').value = d.principal_investigator ?? '';
                        setSelectValue(document.getElementById('edit_school'), d.school ?? '');
                        setSelectValue(document.getElementById('edit_animal_type'), d.animal_type ?? '');
                        document.getElementById('edit_animal_count').value = d.animal_count ?? '';
                        ['sex', 'researcher_type'].forEach(key => {
                            const field = document.getElementById('edit_' + key);
                            field.value = d[key] ?? '';
                            field.disabled = !!d.user_id;
                            field.title = d.user_id ? 'Set by the researcher on their account' : '';
                        });
                        editSkipFields = d.user_id ? ['sex', 'researcher_type'] : [];
                        document.getElementById('edit_research_adviser').value = d.research_adviser ?? '';
                        document.getElementById('edit_veterinarian').value = d.veterinarian ?? '';
                        document.getElementById('edit_research_duration_start').value = (d.research_duration_start ?? '').slice(0, 7);
                        document.getElementById('edit_research_duration_end').value = (d.research_duration_end ?? '').slice(0, 7);
                        document.getElementById('edit_date_released').value = d.date_released ?? '';
                        document.getElementById('edit_received_by').value = d.received_by ?? '';
                        document.getElementById('edit_status').value = d.status || 'Ongoing';
                        editFile.reset(d.file_original_name ?? '', d.file_original_name ? ROOT + '/personnel/records_file/' + d.id + '/' + encodeURIComponent(d.file_original_name) : '');
                        openModal('editModal');
                    })
                    .catch(() => showToast('Network error. Please try again.', 'error'));
            });
        });

        document.getElementById('editRecordSave').addEventListener('click', async () => {
            hideErr('editError');

            const ipnChanged = document.getElementById('edit_reference_no').value.trim() !== editOriginalIpn;
            const newIpn = document.getElementById('edit_reference_no').value.trim();
            if (ipnChanged && newIpn !== '' && !/^\d{6}$/.test(newIpn)) {
                showErr('editError', 'IPN must be 6 digits, with the last 2 digits as the year (e.g. 000026).');
                return;
            }
            if (ipnChanged && editHasSignedScan) {
                const confirmed = await confirmAction(
                    'This record already has a signed scan. Changing the IPN will make the signed scan not match the record.', {
                        okText: 'Change IPN',
                        cancelText: 'Cancel',
                        danger: true
                    }
                );
                if (!confirmed) return;
            }

            const missing = missingStatField('edit', editSkipFields);
            if (missing) {
                showErr('editError', missing);
                return;
            }
            if (editFile.tooBig()) {
                showErr('editError', 'The file is larger than the 10 MB limit.');
                return;
            }

            post('/personnel/records_edit', {
                id: document.getElementById('edit_id').value,
                reference_no: document.getElementById('edit_reference_no').value,
                ar_number: document.getElementById('edit_ar_number').value,
                title_of_research: document.getElementById('edit_title').value,
                school: document.getElementById('edit_school').value,
                animal_type: document.getElementById('edit_animal_type').value,
                animal_count: document.getElementById('edit_animal_count').value,
                principal_investigator: document.getElementById('edit_pi').value,
                sex: document.getElementById('edit_sex').value,
                researcher_type: document.getElementById('edit_researcher_type').value,
                research_adviser: document.getElementById('edit_research_adviser').value,
                veterinarian: document.getElementById('edit_veterinarian').value,
                research_duration_start: document.getElementById('edit_research_duration_start').value,
                research_duration_end: document.getElementById('edit_research_duration_end').value,
                date_released: document.getElementById('edit_date_released').value,
                received_by: document.getElementById('edit_received_by').value,
                status: document.getElementById('edit_status').value,
                protocol_file: editFile.file(),
                remove_file: editFile.removed() ? '1' : '',
            }).then(data => {
                if (data.queued) {
                    closeModal('editModal');
                    notifyQueued();
                } else if (data.ok) {
                    closeModal('editModal');
                    sessionStorage.setItem('records_flash', 'Record updated successfully.');
                    location.reload();
                } else {
                    showErr('editError', data.message || 'Update failed.');
                }
            }).catch(() => showErr('editError', 'Network error. Please try again.'));
        });

        // ===== DELETE =====
        document.querySelectorAll('.delete-record-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const title = btn.dataset.title || '#' + btn.dataset.id;
                const confirmed = await confirmAction(
                    'Delete "' + title + '"? This cannot be undone.', {
                        okText: 'Delete',
                        cancelText: 'Cancel',
                        danger: true
                    }
                );
                if (!confirmed) return;

                setButtonBusy(btn, true, 'Deleting...');

                post('/personnel/records_delete', {
                        id: btn.dataset.id
                    })
                    .then(data => {
                        if (data.queued) {
                            setButtonBusy(btn, false);
                            notifyQueued();
                        } else if (data.ok) {
                            sessionStorage.setItem('records_flash', 'Record deleted.');
                            location.reload();
                        } else {
                            setButtonBusy(btn, false);
                            showToast(data.message || 'Delete failed.', 'error');
                        }
                    }).catch(() => {
                        setButtonBusy(btn, false);
                        showToast('Network error. Please try again.', 'error');
                    });
            });
        });

        // ===== Statistics period filter =====
        (function() {
            const form = document.getElementById('statsPeriodForm');
            if (!form) return;
            const custom = document.getElementById('statsPeriodCustom');

            form.addEventListener('change', e => {
                if (e.target.name !== 'period') return;
                const isCustom = e.target.value === 'custom';
                custom.hidden = !isCustom;
                if (isCustom) {
                    custom.querySelector('input').focus();
                    return;
                }
                form.submit();
            });
        })();

        // ===== Column visibility =====
        (function() {
            const table = document.querySelector('.records-table');
            const toggle = document.getElementById('recordsColumnsToggle');
            const menu = document.getElementById('recordsColumnsMenu');
            if (!table || !toggle || !menu || table.querySelector('.inbox-no-results')) return;

            const storageKey = 'recordsHiddenColumns';
            const boxes = [...menu.querySelectorAll('input[type="checkbox"]')];
            let hidden = [];

            try {
                hidden = JSON.parse(localStorage.getItem(storageKey)) || [];
            } catch (e) {}

            function applyColumns() {
                boxes.forEach(box => {
                    const hide = hidden.includes(box.value);
                    box.checked = !hide;
                    table.querySelectorAll('tr > :nth-child(' + (Number(box.dataset.columnIndex) + 1) + ')')
                        .forEach(cell => cell.classList.toggle('is-column-hidden', hide));
                });

                try {
                    localStorage.setItem(storageKey, JSON.stringify(hidden));
                } catch (e) {}
            }

            function setMenu(open) {
                menu.hidden = !open;
                toggle.setAttribute('aria-expanded', open);
            }

            toggle.addEventListener('click', () => setMenu(menu.hidden));

            menu.addEventListener('change', e => {
                hidden = boxes.filter(box => !box.checked).map(box => box.value);
                applyColumns();
            });

            document.getElementById('recordsColumnsReset').addEventListener('click', () => {
                hidden = [];
                applyColumns();
            });

            document.addEventListener('click', e => {
                if (!e.target.closest('.records-columns')) setMenu(false);
            });

            document.addEventListener('keydown', e => {
                if (e.key !== 'Escape' || menu.hidden) return;
                setMenu(false);
                toggle.focus();
            });

            applyColumns();
        })();

        // ===== Drag-to-scroll table =====
        (function() {
            const scrollEl = document.querySelector('.records-table-wrap .protocol-table-scroll');
            if (!scrollEl) return;

            let isDragging = false;
            let startX = 0;
            let startScrollLeft = 0;

            scrollEl.addEventListener('mousedown', e => {
                if (e.target.closest('button, a, input, select, textarea')) return;
                isDragging = true;
                scrollEl.classList.add('dragging');
                startX = e.pageX;
                startScrollLeft = scrollEl.scrollLeft;
            });

            window.addEventListener('mouseup', () => {
                isDragging = false;
                scrollEl.classList.remove('dragging');
            });

            scrollEl.addEventListener('mouseleave', () => {
                isDragging = false;
                scrollEl.classList.remove('dragging');
            });

            scrollEl.addEventListener('mousemove', e => {
                if (!isDragging) return;
                e.preventDefault();
                scrollEl.scrollLeft = startScrollLeft - (e.pageX - startX);
            });
        })();

        // ===== sessionStorage flash (after reload) =====
        const pendingFlash = sessionStorage.getItem('records_flash');
        if (pendingFlash) {
            sessionStorage.removeItem('records_flash');
            showToast(pendingFlash);
        }

    })();
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>