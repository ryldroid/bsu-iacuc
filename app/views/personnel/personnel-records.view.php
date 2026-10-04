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
$sort            = $sort            ?? 'newest';
$schools         = $schools         ?? [];
$animalTypes     = $animalTypes     ?? [];
$sexes         = $sexes         ?? [];
$researcherTypes = $researcherTypes ?? [];
$stats           = $stats           ?? [
    'total' => 0,
    'processed_this_month' => 0,
    'processed_this_quarter' => 0,
    'total_animals' => 0,
    'animal_breakdown' => [],
    'school_breakdown' => [],
    'researcher_type_breakdown' => [],
    'sex_breakdown' => [],
    'incomplete_count' => 0,
    'distinct_schools' => 0,
    'distinct_researchers' => 0,
    'protocols_with_count' => 0,
    'avg_animals_per_protocol' => 0,
    'ongoing_studies' => 0,
    'completed_studies' => 0,
    'monthly_trend' => [],
    'active_clearances' => 0,
    'expiring_soon' => 0,
    'expired_clearances' => 0,
    'no_duration_count' => 0,
    'released_this_year' => 0,
    'species_breakdown' => [],
    'protocols_reviewed' => 0,
    'protocols_endorsed' => 0,
    'reviewed_this_month' => 0,
    'endorsed_this_month' => 0,
    'release_trend' => [],
    'excluded_by_period' => 0,
];
$period          = $period          ?? null;
$periodPreset    = $periodPreset    ?? 'all';
$periodBasis     = $periodBasis     ?? 'released';
$periodFrom      = $periodFrom      ?? '';
$periodTo        = $periodTo        ?? '';
$flash_success   = $flash_success   ?? '';
$flash_error     = $flash_error     ?? '';

$offset      = ($page - 1) * $perPage;
$hasFilters  = $search !== '' || $school !== '' || $animalType !== '' || $sex !== '' || $researcherType !== '';
$isStaff = $role === 'staff';
$colCount = 15;

// ===== Chart helpers =====
function statBarRows(array $breakdown, int $topN = 6): array
{
    if (! $breakdown) return [];
    $top  = array_slice($breakdown, 0, $topN);
    $rest = array_slice($breakdown, $topN);
    $otherTotal = array_sum(array_column($rest, 'total'));
    if ($otherTotal > 0) {
        $top[] = ['label' => 'Other', 'total' => $otherTotal, 'protocols' => array_sum(array_column($rest, 'protocols'))];
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
            'modifier'  => $row['modifier'] ?? '',
        ];
    }
    return $rows;
}

function renderBarList(array $rows, string $emptyText, bool $showShare = true): void
{
    if (! $rows) {
        echo '<div class="records-card-empty">' . htmlspecialchars($emptyText) . '</div>';
        return;
    }
    echo '<ul class="records-bar-chart">';
    foreach ($rows as $r) {
        $title = $r['protocols'] !== null ? ' title="' . (int) $r['protocols'] . ' record(s)"' : '';
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

$speciesRows    = statBarRows($stats['species_breakdown']);
$schoolRows     = statBarRows($stats['school_breakdown']);
$researcherRows = statBarRows($stats['researcher_type_breakdown']);
$sexRows        = statBarRows($stats['sex_breakdown']);

$activeOnly = max(0, $stats['active_clearances'] - $stats['expiring_soon']);
$statusRows = statBarRows(array_values(array_filter([
    ['label' => 'Active',                  'total' => $activeOnly,                   'modifier' => 'ok'],
    ['label' => 'Expiring within 30 days', 'total' => $stats['expiring_soon'],       'modifier' => 'warn'],
    ['label' => 'Expired',                 'total' => $stats['expired_clearances'],  'modifier' => 'danger'],
    ['label' => 'No end date set',         'total' => $stats['no_duration_count'],   'modifier' => 'muted'],
], fn($r) => $r['total'] > 0)));

$releaseTrend    = $stats['release_trend'] ?? [];
$releaseTrendMax = $releaseTrend ? max(array_column($releaseTrend, 'total')) : 0;

$activeFilterLabels = array_filter([
    'Search'          => $search,
    'School'          => $school,
    'Animal'          => $animalType,
    'Sex'             => $sex,
    'Researcher type' => $researcherType,
    'Period'          => $period['label'] ?? '',
], fn($v) => $v !== '');

$carriedFilters = array_filter([
    'search' => $search,
    'school' => $school,
    'animal' => $animalType,
    'sex'    => $sex,
    'rtype'  => $researcherType,
    'sort'   => $sort !== 'newest' ? $sort : '',
], fn($v) => $v !== '');
$periodParams = $period ? array_filter([
    'period' => $periodPreset,
    'basis'  => $periodBasis,
    'from'   => $periodPreset === 'custom' ? $periodFrom : '',
    'to'     => $periodPreset === 'custom' ? $periodTo : '',
], fn($v) => $v !== '') : [];

$activeTab = ($_GET['tab'] ?? '') === 'statistics' ? 'statistics' : 'records';

// ===== Page URL helper =====
function pageUrl(int $p, string $search, string $school, string $animalType, string $sex, string $researcherType, string $sort): string
{
    return '?' . http_build_query(array_filter([
        'page'   => $p,
        'search' => $search,
        'school' => $school,
        'animal' => $animalType,
        'sex' => $sex,
        'rtype'  => $researcherType,
        'sort'   => $sort !== 'newest' ? $sort : '',
    ], fn($v) => $v !== '' && $v !== 1 || is_string($v)));
}

// ===== Duration helper =====
function formatDurationRange(?string $start, ?string $end): string
{
    if ($start && $end) {
        return date(DATE_FORMAT, strtotime($start)) . ' – ' . date(DATE_FORMAT, strtotime($end));
    }
    if ($start) {
        return 'From ' . date(DATE_FORMAT, strtotime($start));
    }
    if ($end) {
        return 'Until ' . date(DATE_FORMAT, strtotime($end));
    }
    return '';
}
?>

<link rel="stylesheet" href="<?= asset_css('personnel/personnel-home.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/records.css') ?>">
<link rel="stylesheet" href="<?= asset_css('tabs.css') ?>">

<div class="body">
    <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

    <!-- ===== Records page ===== -->
    <main class="main-content" id="main-content" tabindex="-1">

        <!-- ===== Flash messages ===== -->
        <?php if ($flash_success): ?>
            <div class="alert success-message" id="flashSuccess">
                <?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div class="alert error-messages" id="flashError">
                <?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <!-- ===== Tabs: Records / Statistics ===== -->
        <div class="tab-strip" role="tablist" aria-label="Records sections" data-tab-panels="recordsTabPanels" data-tab-param="tab">
            <button type="button" role="tab" id="tab-records" data-tab="records"
                aria-selected="<?= $activeTab === 'records' ? 'true' : 'false' ?>"
                aria-controls="panel-records">Records</button>
            <button type="button" role="tab" id="tab-statistics" data-tab="statistics"
                aria-selected="<?= $activeTab === 'statistics' ? 'true' : 'false' ?>"
                aria-controls="panel-statistics">Statistics</button>
        </div>

        <div id="recordsTabPanels">
            <div class="tab-panel" id="panel-records" role="tabpanel" aria-labelledby="tab-records"
                data-tab-panel="records" <?= $activeTab === 'records' ? '' : 'hidden' ?>>
                <!-- ===== Page header ===== -->
                <div class="dashboard-page-header records-page-header">
                    <div>
                        <h1 class="dashboard-page-title">Records</h1>
                        <!-- <p>Protocol entries are automatically added to the records table once the reviewer finishes review.</p> -->
                    </div>

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

                    <!-- Filter selects -->
                    <div class="records-filters-row">
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
                                Sort:
                            </p>
                            <select name="sort" class="records-filter-select" aria-label="Sort records" onchange="this.form.submit()">
                                <?php foreach (RecordModel::SORT_OPTIONS as $key => $opt): ?>
                                    <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= htmlspecialchars($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <input type="hidden" name="page" value="1">
                    <?php foreach ($periodParams as $name => $value): ?>
                        <input type="hidden" name="<?= htmlspecialchars($name, ENT_QUOTES) ?>" value="<?= htmlspecialchars($value, ENT_QUOTES) ?>">
                    <?php endforeach; ?>
                </form>

                <!-- ===== Table ===== -->
                <div class="protocol-table-wrap records-table-wrap">
                    <div class="protocol-table-scroll">
                        <table class="protocol-table records-table data-table">
                            <thead>
                                <tr>
                                    <th class="col-ref">IPN</th>
                                    <th class="col-ar">AR No.</th>
                                    <th class="col-title">Title of Research</th>
                                    <th class="col-school">School</th>
                                    <th class="col-animal">Animal Type</th>
                                    <th class="col-count">Count</th>
                                    <th class="col-pi">Researcher</th>
                                    <th class="col-sex">Researcher Sex</th>
                                    <th class="col-rtype">Researcher Type</th>
                                    <th class="col-adviser">Research Adviser</th>
                                    <th class="col-vet">Veterinarian</th>
                                    <th class="col-duration">Duration</th>
                                    <th class="col-date">Date Released</th>
                                    <th class="col-recv">Received By</th>
                                    <!-- ACTION BUTTONS column -->
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
                                                    No records yet. Records are added automatically when a protocol is marked <strong>Reviewed</strong>, or you can add one manually.
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($records as $i => $r): ?>
                                        <tr>
                                            <td class="date-cell records-ref"><?= htmlspecialchars($r['reference_no'] ?? '') ?></td>
                                            <td class="date-cell records-ar"><?= htmlspecialchars($r['ar_number'] ?? '') ?></td>
                                            <td class="records-title-cell">
                                                <div class="protocol-title-cell">
                                                    <?= htmlspecialchars($r['title_of_research']) ?>
                                                </div>
                                            </td>
                                            <td class="researcher-cell" data-label="School"><?= htmlspecialchars($r['school'] ?? '') ?></td>
                                            <td class="date-cell" data-label="Animal Type"><?= htmlspecialchars($r['animal_type'] ?? '') ?></td>
                                            <td class="date-cell" data-label="Count"><?= htmlspecialchars($r['animal_count'] ?? '') ?></td>
                                            <td class="researcher-cell" data-label="Researcher"><?= htmlspecialchars($r['principal_investigator'] ?? '') ?></td>
                                            <td class="date-cell" data-label="Researcher Sex"><?= htmlspecialchars($r['sex'] ?? '') ?></td>
                                            <td class="date-cell" data-label="Researcher Type"><?= htmlspecialchars($r['researcher_type'] ?? '') ?></td>
                                            <td class="researcher-cell" data-label="Research Adviser"><?= htmlspecialchars($r['research_adviser'] ?? '') ?></td>
                                            <td class="researcher-cell" data-label="Veterinarian"><?= htmlspecialchars($r['veterinarian'] ?? '') ?></td>
                                            <td class="date-cell" data-label="Duration">
                                                <?= htmlspecialchars(formatDurationRange($r['research_duration_start'] ?? null, $r['research_duration_end'] ?? null)) ?>
                                                <?php if (!empty($r['user_id']) && !empty($r['research_duration_end']) && strtotime($r['research_duration_end']) < strtotime('today')): ?>
                                                    <span class="records-expired-tag">Expired</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="date-cell" data-label="Date Released"><?= $r['date_released'] ? date(DATE_FORMAT, strtotime($r['date_released'])) : '' ?></td>
                                            <td class="researcher-cell" data-label="Received By"><?= htmlspecialchars($r['received_by'] ?? '') ?></td>
                                            <!-- ACTION BUTTONS -->
                                            <td class="actions-cell">
                                                <div class="row-actions">
                                                    <?php if (!empty($r['file_path'])): ?>
                                                        <a class="row-btn view-record-btn"
                                                            href="<?= ROOT ?>/personnel/records_file/<?= (int)$r['id'] ?>"
                                                            target="_blank" rel="noopener noreferrer"
                                                            aria-label="View protocol file">
                                                            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                                <use href="#eye-icon">
                                                            </svg>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($isStaff): ?>
                                                        <button type="button" class="row-btn delete-record-btn"
                                                            data-id="<?= (int)$r['id'] ?>"
                                                            data-title="<?= htmlspecialchars(mb_substr($r['title_of_research'], 0, 60), ENT_QUOTES) ?>"
                                                            aria-label="Delete record">
                                                            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                                <use href="#trash-icon">
                                                            </svg>
                                                        </button>

                                                        <button type="button" class="row-btn edit-record-btn"
                                                            data-id="<?= (int)$r['id'] ?>"
                                                            aria-label="Edit record">
                                                            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                                <use href="#edit-icon">
                                                            </svg>
                                                        </button>
                                                    <?php endif; ?>
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
                    <div class="pagination-bar">
                        <div class="pagination-info">
                            Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?> records
                        </div>
                        <div class="pagination-buttons">
                            <?php if ($page > 1): ?>
                                <a href="<?= pageUrl(1, $search, $school, $animalType, $sex, $researcherType, $sort) ?>" class="pagination-btn" title="First">«</a>
                                <a href="<?= pageUrl($page - 1, $search, $school, $animalType, $sex, $researcherType, $sort) ?>" class="pagination-btn" title="Previous">‹</a>
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
                                <a href="<?= pageUrl($i, $search, $school, $animalType, $sex, $researcherType, $sort) ?>"
                                    class="pagination-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor;
                            if ($end < $totalPages) echo '<span class="pagination-ellipsis">…</span>';
                            ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= pageUrl($page + 1, $search, $school, $animalType, $sex, $researcherType, $sort) ?>" class="pagination-btn" title="Next">›</a>
                                <a href="<?= pageUrl($totalPages, $search, $school, $animalType, $sex, $researcherType, $sort) ?>" class="pagination-btn" title="Last">»</a>
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
                <div class="dashboard-page-header">
                    <h1 class="dashboard-page-title">Statistics</h1>
                    <a class="row-btn records-export-btn"
                        href="<?= ROOT ?>/personnel/records_export?<?= http_build_query($carriedFilters + $periodParams) ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#download-icon">
                        </svg>
                        Download as Excel
                    </a>
                </div>

                <!-- ===== Period filter (statistics only) ===== -->
                <form method="GET" action="" class="records-filters-row" id="statsPeriodForm">
                    <input type="hidden" name="tab" value="statistics">
                    <?php foreach ($carriedFilters as $name => $value): ?>
                        <input type="hidden" name="<?= htmlspecialchars($name, ENT_QUOTES) ?>" value="<?= htmlspecialchars($value, ENT_QUOTES) ?>">
                    <?php endforeach; ?>

                    <p class="sort-filter-label">
                        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#calendar-icon" />
                        </svg>
                        Period:
                    </p>
                    <select name="period" id="statsPeriod" class="records-filter-select" aria-label="Statistics period">
                        <?php foreach (RecordModel::PERIOD_PRESETS as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $periodPreset === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <span class="records-period-custom" id="statsPeriodCustom" <?= $periodPreset === 'custom' ? '' : 'hidden' ?>>
                        <input type="date" name="from" class="records-filter-select" aria-label="From date" value="<?= htmlspecialchars($periodFrom, ENT_QUOTES) ?>">
                        <span class="records-date-range-sep">to</span>
                        <input type="date" name="to" class="records-filter-select" aria-label="To date" value="<?= htmlspecialchars($periodTo, ENT_QUOTES) ?>">
                        <button type="submit" class="row-btn records-period-apply">Apply</button>
                    </span>

                    <p class="sort-filter-label">Based on:</p>
                    <select name="basis" id="statsBasis" class="records-filter-select" aria-label="Which date to filter by">
                        <?php foreach (RecordModel::PERIOD_BASES as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $periodBasis === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ($period): ?>
                        <a href="<?= ROOT ?>/personnel/records?<?= http_build_query(['tab' => 'statistics'] + $carriedFilters) ?>" class="row-btn records-clear-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#close-icon" />
                            </svg>
                            Reset period
                        </a>
                    <?php endif; ?>
                </form>

                <?php if ($activeFilterLabels): ?>
                    <div class="records-filter-note">
                        <span>Showing statistics for:
                            <?= htmlspecialchars(implode(' · ', array_map(fn($k, $v) => "$k: $v", array_keys($activeFilterLabels), $activeFilterLabels))) ?>
                        </span>
                        <?php if ($carriedFilters): ?>
                            <a href="<?= ROOT ?>/personnel/records?tab=statistics">Clear all filters</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($period && $stats['excluded_by_period'] > 0): ?>
                    <div class="records-data-note">
                        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#info-icon"></use>
                        </svg>
                        <span>
                            <?= number_format($stats['excluded_by_period']) ?> record(s) have no <?= $period['missing_by'] ?> and can't be placed in this period, so they are left out.
                        </span>
                    </div>
                <?php endif; ?>

                <!-- ===== Headline numbers ===== -->
                <div class="metrics-row records-metrics">
                    <div class="metric-card records-stat-card">
                        <div class="records-stat-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#review-icon"></use>
                            </svg>
                        </div>
                        <div>
                            <div class="metric-card-label">Protocols Reviewed This Month</div>
                            <div class="metric-card-value"><?= number_format($stats['reviewed_this_month']) ?></div>
                            <div class="records-stat-hint"><?= number_format($stats['protocols_reviewed']) ?> <?= $period ? 'in selected period' : 'all time' ?></div>
                        </div>
                    </div>
                    <div class="metric-card records-stat-card">
                        <div class="records-stat-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#check-circle-icon"></use>
                            </svg>
                        </div>
                        <div>
                            <div class="metric-card-label">Protocols Endorsed This Month</div>
                            <div class="metric-card-value"><?= number_format($stats['endorsed_this_month']) ?></div>
                            <div class="records-stat-hint"><?= number_format($stats['protocols_endorsed']) ?> <?= $period ? 'in selected period' : 'all time' ?></div>
                        </div>
                    </div>
                    <div class="metric-card records-stat-card">
                        <div class="records-stat-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#protocols-icon"></use>
                            </svg>
                        </div>
                        <div>
                            <div class="metric-card-label">Total Records</div>
                            <div class="metric-card-value"><?= number_format($stats['total']) ?></div>
                            <div class="records-stat-hint"><?= number_format($stats['released_this_year']) ?> released this year</div>
                        </div>
                    </div>
                    <div class="metric-card records-stat-card">
                        <div class="records-stat-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#shield-check-icon"></use>
                            </svg>
                        </div>
                        <div>
                            <div class="metric-card-label">Active Clearances</div>
                            <div class="metric-card-value"><?= number_format($stats['active_clearances']) ?></div>
                            <div class="records-stat-hint"><?= number_format($stats['expired_clearances']) ?> expired</div>
                        </div>
                    </div>
                    <div class="metric-card records-stat-card <?= $stats['expiring_soon'] > 0 ? 'records-stat-card--warn' : '' ?>">
                        <div class="records-stat-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#<?= $stats['expiring_soon'] > 0 ? 'alert-triangle-icon' : 'clock-icon' ?>"></use>
                            </svg>
                        </div>
                        <div>
                            <div class="metric-card-label">Expiring in 30 Days</div>
                            <div class="metric-card-value"><?= number_format($stats['expiring_soon']) ?></div>
                            <div class="records-stat-hint"><?= $stats['expiring_soon'] > 0 ? 'Clearances ending soon' : 'Nothing expiring soon' ?></div>
                        </div>
                    </div>
                    <div class="metric-card records-stat-card">
                        <div class="records-stat-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#beaker-icon"></use>
                            </svg>
                        </div>
                        <div>
                            <div class="metric-card-label">Total Animals Used</div>
                            <div class="metric-card-value"><?= number_format($stats['total_animals']) ?></div>
                            <div class="records-stat-hint">Avg <?= number_format($stats['avg_animals_per_protocol'], 1) ?> per record</div>
                        </div>
                    </div>
                </div>

                <?php if ($stats['incomplete_count'] > 0): ?>
                    <div class="records-data-note">
                        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#info-icon"></use>
                        </svg>
                        <span>
                            <?= number_format($stats['incomplete_count']) ?> of <?= number_format($stats['total']) ?> records are missing an animal count, duration, or release date.
                            Totals here only count what has been filled in, so they may be lower than the real figures.
                        </span>
                    </div>
                <?php endif; ?>

                <!-- ===== Breakdowns ===== -->
                <div class="records-charts-grid">
                    <div class="metric-card records-bar-card">
                        <div class="metric-card-label">Animals Used by Species</div>
                        <p class="records-card-hint">Total animals across all records</p>
                        <?php renderBarList($speciesRows, 'No animal data yet.'); ?>
                    </div>

                    <div class="metric-card records-bar-card">
                        <div class="metric-card-label">Clearance Status</div>
                        <p class="records-card-hint">Based on each record's research end date</p>
                        <?php renderDonut($statusRows, 'records', 'No records yet.'); ?>
                    </div>

                    <div class="metric-card records-bar-card">
                        <div class="metric-card-label">Records by School</div>
                        <p class="records-card-hint">Where researchers come from</p>
                        <?php renderBarList($schoolRows, 'No school data yet.'); ?>
                    </div>

                    <div class="metric-card records-bar-card">
                        <div class="metric-card-label">Records by Researcher Type</div>
                        <p class="records-card-hint">Student, faculty, staff, or researcher</p>
                        <?php renderDonut($researcherRows, 'records', 'No researcher type data yet.'); ?>
                    </div>

                    <div class="metric-card records-bar-card">
                        <div class="metric-card-label">Records by Researcher Sex</div>
                        <p class="records-card-hint">Recorded sex of the researcher</p>
                        <?php renderDonut($sexRows, 'records', 'No sex data yet.'); ?>
                    </div>

                    <div class="metric-card records-trend-card">
                        <div class="metric-card-label">Records Released, Last 12 Months</div>
                        <p class="records-card-hint">Counted by Date Released</p>
                        <?php if ($releaseTrendMax > 0): ?>
                            <div class="records-trend-chart">
                                <?php foreach ($releaseTrend as $m): ?>
                                    <div class="records-trend-col" title="<?= htmlspecialchars($m['month'] . ' ' . $m['year']) ?>">
                                        <div class="records-trend-count"><?= number_format($m['total']) ?></div>
                                        <div class="records-trend-bar-track">
                                            <div class="records-trend-bar-fill" style="--trend-pct: <?= round(($m['total'] / $releaseTrendMax) * 100) ?>%"></div>
                                        </div>
                                        <div class="records-trend-month"><?= htmlspecialchars($m['month']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="records-card-empty">No release dates recorded yet. Fill in Date Released on a record to see it here.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<?php include dirname(__DIR__) . '/includes/animal-type-options.php'; ?>

<script src="<?= asset_js('tabs.js') ?>" defer></script>

<!-- ===== ADD RECORD MODAL ===== -->
<div class="modal-backdrop" id="addModal" role="dialog" aria-modal="true" aria-labelledby="addModalTitle">
    <div class="modal-card records-modal-card">
        <div class="modal-header records-modal-header">
            <h2 id="addModalTitle">Add Record</h2>
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
                    <label for="add_reference_no">IPN <span class="records-required">*</span></label>
                    <input type="text" id="add_reference_no" name="reference_no" placeholder="e.g. BSU-IACUC-2025-001">
                </div>
                <div class="records-form-group records-form-full">
                    <label for="add_ar_number">AR Number</label>
                    <input type="text" id="add_ar_number" name="ar_number" placeholder="From the BAI clearance">
                </div>
                <div class="records-form-group records-form-full">
                    <label for="add_title">Title of Research <span class="records-required">*</span></label>
                    <textarea id="add_title" name="title_of_research" rows="3" placeholder="Full research title"></textarea>
                </div>
                <div class="records-form-group">
                    <label for="add_pi">Principal Investigator</label>
                    <input type="text" id="add_pi" name="principal_investigator" placeholder="Full name">
                </div>
                <div class="records-form-group">
                    <label for="add_school">School / Department</label>
                    <input type="text" id="add_school" name="school" placeholder="e.g. College of Agriculture">
                </div>
                <div class="records-form-group">
                    <label for="add_animal_type">Animal Type</label>
                    <input type="text" id="add_animal_type" name="animal_type" list="animal-type-options" placeholder="e.g. Mice, Rats">
                </div>
                <div class="records-form-group">
                    <label for="add_animal_count">Animal Count</label>
                    <input type="number" id="add_animal_count" name="animal_count" min="0" placeholder="0">
                </div>
                <div class="records-form-group">
                    <label for="add_sex">Researcher Sex</label>
                    <select id="add_sex" name="sex">
                        <option value="">Select</option>
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                </div>
                <div class="records-form-group">
                    <label for="add_researcher_type">Researcher Type</label>
                    <select id="add_researcher_type" name="researcher_type">
                        <option value="">Select</option>
                        <option>Student</option>
                        <option>Faculty</option>
                        <option>Staff</option>
                        <option>Researcher</option>
                    </select>
                </div>
                <div class="records-form-group">
                    <label for="add_research_adviser">Research Adviser</label>
                    <input type="text" id="add_research_adviser" name="research_adviser" placeholder="Full name">
                </div>
                <div class="records-form-group">
                    <label for="add_veterinarian">Veterinarian</label>
                    <input type="text" id="add_veterinarian" name="veterinarian" placeholder="Full name">
                </div>
                <div class="records-form-group records-form-full">
                    <label for="add_research_duration_start">Research Duration</label>
                    <div class="records-date-range">
                        <input type="date" id="add_research_duration_start" name="research_duration_start" aria-label="Research duration start date">
                        <span class="records-date-range-sep">to</span>
                        <input type="date" id="add_research_duration_end" name="research_duration_end" aria-label="Research duration end date">
                    </div>
                    <span class="helper">The end date also serves as this clearance's expiry. Once it passes and the researcher has no other protocols being processed, their account is deactivated automatically.</span>
                </div>
                <div class="records-form-group">
                    <label for="add_date_released">Date Released</label>
                    <input type="date" id="add_date_released" name="date_released">
                </div>
                <div class="records-form-group records-form-full">
                    <label for="add_received_by">Received By</label>
                    <input type="text" id="add_received_by" name="received_by" placeholder="Name of receiving officer">
                </div>
            </div>
        </div>
        <div class="records-modal-footer">
            <button type="button" class="row-btn" data-close="addModal">Cancel</button>
            <button type="button" class="row-btn row-btn-primary" id="addRecordSave">Save Record</button>
        </div>
    </div>
</div>

<!-- ===== EDIT RECORD MODAL ===== -->
<div class="modal-backdrop" id="editModal" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
    <div class="modal-card records-modal-card">
        <div class="modal-header records-modal-header">
            <h2 id="editModalTitle">Edit Record</h2>
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
                <div class="records-form-group records-form-full">
                    <label for="edit_reference_no">IPN</label>
                    <input type="text" id="edit_reference_no" name="reference_no" placeholder="e.g. BSU-IACUC-2025-001">
                </div>
                <div class="records-form-group records-form-full">
                    <label for="edit_ar_number">AR Number</label>
                    <input type="text" id="edit_ar_number" name="ar_number" placeholder="From the BAI clearance">
                </div>
                <div class="records-form-group records-form-full">
                    <label for="edit_title">Title of Research</label>
                    <textarea id="edit_title" name="title_of_research" rows="3"></textarea>
                </div>
                <div class="records-form-group">
                    <label for="edit_pi">Principal Investigator</label>
                    <input type="text" id="edit_pi" name="principal_investigator">
                </div>
                <div class="records-form-group">
                    <label for="edit_school">School / Department</label>
                    <input type="text" id="edit_school" name="school">
                </div>
                <div class="records-form-group">
                    <label for="edit_animal_type">Animal Type</label>
                    <input type="text" id="edit_animal_type" name="animal_type" list="animal-type-options">
                </div>
                <div class="records-form-group">
                    <label for="edit_animal_count">Animal Count</label>
                    <input type="number" id="edit_animal_count" name="animal_count" min="0">
                </div>
                <div class="records-form-group">
                    <label for="edit_sex">Researcher Sex</label>
                    <select id="edit_sex" name="sex">
                        <option value="">Select</option>
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                </div>
                <div class="records-form-group">
                    <label for="edit_researcher_type">Researcher Type</label>
                    <select id="edit_researcher_type" name="researcher_type">
                        <option value="">Select</option>
                        <option>Student</option>
                        <option>Faculty</option>
                        <option>Staff</option>
                        <option>Researcher</option>
                    </select>
                </div>
                <div class="records-form-group">
                    <label for="edit_research_adviser">Research Adviser</label>
                    <input type="text" id="edit_research_adviser" name="research_adviser">
                </div>
                <div class="records-form-group">
                    <label for="edit_veterinarian">Veterinarian</label>
                    <input type="text" id="edit_veterinarian" name="veterinarian">
                </div>
                <div class="records-form-group records-form-full">
                    <label for="edit_research_duration_start">Research Duration</label>
                    <div class="records-date-range">
                        <input type="date" id="edit_research_duration_start" name="research_duration_start" aria-label="Research duration start date">
                        <span class="records-date-range-sep">to</span>
                        <input type="date" id="edit_research_duration_end" name="research_duration_end" aria-label="Research duration end date">
                    </div>
                    <span class="helper">The end date also serves as this clearance's expiry. Once it passes and the researcher has no other protocols being processed, their account is deactivated automatically.</span>
                </div>
                <div class="records-form-group">
                    <label for="edit_date_released">Date Released</label>
                    <input type="date" id="edit_date_released" name="date_released">
                </div>
                <div class="records-form-group records-form-full">
                    <label for="edit_received_by">Received By</label>
                    <input type="text" id="edit_received_by" name="received_by">
                </div>
            </div>
        </div>
        <div class="records-modal-footer">
            <button type="button" class="row-btn" data-close="editModal">Cancel</button>
            <button type="button" class="row-btn row-btn-primary" id="editRecordSave">Save Changes</button>
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

        // ===== ADD =====
        const addRecordBtn = document.getElementById('addRecordBtn');
        if (addRecordBtn) {
            addRecordBtn.addEventListener('click', () => {
                hideErr('addError');
                document.getElementById('addModal').querySelectorAll('input,textarea,select').forEach(el => el.value = '');
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
                if (!title) {
                    showErr('addError', 'Title of research is required.');
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
                }).then(data => {
                    if (data.ok) {
                        closeModal('addModal');
                        sessionStorage.setItem('records_flash', 'Record added successfully.');
                        location.reload();
                    } else {
                        showErr('addError', data.message || 'Add failed.');
                    }
                }).catch(() => showErr('addError', 'Network error. Please try again.'));
            });
        }

        // ===== EDIT =====
        document.querySelectorAll('.edit-record-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                hideErr('editError');
                const id = btn.dataset.id;
                fetch(ROOT + '/personnel/records_get?id=' + encodeURIComponent(id))
                    .then(r => r.json())
                    .then(data => {
                        if (!data.ok) {
                            alert(data.message || 'Could not load record.');
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
                        document.getElementById('edit_school').value = d.school ?? '';
                        document.getElementById('edit_animal_type').value = d.animal_type ?? '';
                        document.getElementById('edit_animal_count').value = d.animal_count ?? '';
                        document.getElementById('edit_sex').value = d.sex ?? '';
                        document.getElementById('edit_researcher_type').value = d.researcher_type ?? '';
                        document.getElementById('edit_research_adviser').value = d.research_adviser ?? '';
                        document.getElementById('edit_veterinarian').value = d.veterinarian ?? '';
                        document.getElementById('edit_research_duration_start').value = d.research_duration_start ?? '';
                        document.getElementById('edit_research_duration_end').value = d.research_duration_end ?? '';
                        document.getElementById('edit_date_released').value = d.date_released ?? '';
                        document.getElementById('edit_received_by').value = d.received_by ?? '';
                        openModal('editModal');
                    })
                    .catch(() => alert('Network error. Please try again.'));
            });
        });

        document.getElementById('editRecordSave').addEventListener('click', async () => {
            hideErr('editError');

            const ipnChanged = document.getElementById('edit_reference_no').value.trim() !== editOriginalIpn;
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

            post('/personnel/records_edit', {
                id: document.getElementById('edit_id').value,
                reference_no: document.getElementById('edit_reference_no').value,
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
            }).then(data => {
                if (data.ok) {
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
                        if (data.ok) {
                            sessionStorage.setItem('records_flash', 'Record deleted.');
                            location.reload();
                        } else {
                            setButtonBusy(btn, false);
                            alert(data.message || 'Delete failed.');
                        }
                    }).catch(() => {
                        setButtonBusy(btn, false);
                        alert('Network error. Please try again.');
                    });
            });
        });

        // ===== Statistics period filter =====
        (function() {
            const form = document.getElementById('statsPeriodForm');
            if (!form) return;
            const period = document.getElementById('statsPeriod');
            const basis = document.getElementById('statsBasis');
            const custom = document.getElementById('statsPeriodCustom');

            period.addEventListener('change', () => {
                const isCustom = period.value === 'custom';
                custom.hidden = !isCustom;
                if (isCustom) {
                    custom.querySelector('input').focus();
                    return;
                }
                form.submit();
            });
            basis.addEventListener('change', () => form.submit());
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
            const flash = document.createElement('div');
            flash.className = 'alert success-message';
            flash.id = 'flashSuccess';
            flash.textContent = pendingFlash;
            const main = document.getElementById('main-content');
            main.insertBefore(flash, main.firstChild);
            setTimeout(() => flash.remove(), 4000);
        }

        // ===== Auto-dismiss PHP flash messages =====
        function dismissFlash(id, delay) {
            const el = document.getElementById(id);
            if (!el) return;
            setTimeout(() => el.remove(), delay);
        }
        dismissFlash('flashSuccess', 4000);
        dismissFlash('flashError', 7000);

    })();
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>