<?php

require_once dirname(__DIR__) . '/core/Model.php';

class RecordModel extends Model
{
  // ===== FILTERS & PERIOD =====
  private function buildFilters(
    string $search,
    string $school,
    string $animalType,
    string $sex,
    string $researcherType,
    ?array $period = null
  ): array {
    $conditions = [];
    $params     = [];
    $types      = '';

    if ($search !== '') {
      $like = '%' . $search . '%';
      $conditions[] = "(reference_no LIKE ? OR ar_number LIKE ? OR title_of_research LIKE ? OR school LIKE ?
                              OR animal_type LIKE ? OR principal_investigator LIKE ?
                              OR sex LIKE ? OR researcher_type LIKE ?
                              OR research_adviser LIKE ? OR veterinarian LIKE ?
                              OR received_by LIKE ?)";
      for ($i = 0; $i < 11; $i++) {
        $params[] = &$like;
        $types   .= 's';
      }
    }
    if ($school !== '') {
      $conditions[] = 'school = ?';
      $params[] = &$school;
      $types .= 's';
    }
    if ($animalType !== '') {
      $conditions[] = 'animal_type = ?';
      $params[] = &$animalType;
      $types .= 's';
    }
    if ($sex !== '') {
      $conditions[] = 'sex = ?';
      $params[] = &$sex;
      $types .= 's';
    }
    if ($researcherType !== '') {
      $conditions[] = 'researcher_type = ?';
      $params[] = &$researcherType;
      $types .= 's';
    }

    if ($period !== null) {
      $conditions[] = $period['sql'];
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    return [$where, $params, $types];
  }

  public const PERIOD_PRESETS = [
    'all'            => 'All time',
    'this_month'     => 'This month',
    'last_month'     => 'Last month',
    'this_quarter'   => 'This quarter',
    'this_year'      => 'This year',
    'last_year'      => 'Last year',
    'last_12_months' => 'Last 12 months',
    'custom'         => 'Custom range',
  ];

  public const PERIOD_BASES = [
    'released' => 'Date Released',
    'duration' => 'Research Duration',
  ];

  public function resolvePeriod(string $preset, string $basis, string $from = '', string $to = ''): ?array
  {
    if (! isset(self::PERIOD_PRESETS[$preset]) || $preset === 'all') return null;
    if (! isset(self::PERIOD_BASES[$basis])) $basis = 'released';

    $today = strtotime('today');
    $qStartMonth = (int) ((ceil((int) date('n', $today) / 3) - 1) * 3 + 1);

    switch ($preset) {
      case 'this_month':
        $start = date('Y-m-01', $today);
        $end   = date('Y-m-t', $today);
        break;
      case 'last_month':
        $ref   = strtotime('first day of last month', $today);
        $start = date('Y-m-01', $ref);
        $end   = date('Y-m-t', $ref);
        break;
      case 'this_quarter':
        $start = date('Y-') . str_pad((string) $qStartMonth, 2, '0', STR_PAD_LEFT) . '-01';
        $end   = date('Y-m-t', strtotime($start . ' +2 months'));
        break;
      case 'this_year':
        $start = date('Y-01-01', $today);
        $end   = date('Y-12-31', $today);
        break;
      case 'last_year':
        $start = date('Y-01-01', strtotime('-1 year', $today));
        $end   = date('Y-12-31', strtotime('-1 year', $today));
        break;
      case 'last_12_months':
        $start = date('Y-m-01', strtotime('-11 months', $today));
        $end   = date('Y-m-t', $today);
        break;
      default:
        $start = $this->validDate($from);
        $end   = $this->validDate($to);
        if ($start !== null && $end !== null && $start > $end) {
          [$start, $end] = [$end, $start];
        }
        if ($start === null && $end === null) return null;
    }

    if ($basis === 'released') {
      $parts = ['date_released IS NOT NULL'];
      if ($start !== null) $parts[] = "date_released >= '$start'";
      if ($end !== null)   $parts[] = "date_released <= '$end'";
      $missing = 'date_released IS NULL';
    } else {
      $parts = ['research_duration_end IS NOT NULL'];
      if ($start !== null) $parts[] = "research_duration_end >= '$start'";
      if ($end !== null)   $parts[] = "(research_duration_start IS NULL OR research_duration_start <= '$end')";
      $missing = 'research_duration_end IS NULL';
    }

    $fmt = fn(string $d) => date(DATE_FORMAT, strtotime($d));
    if ($start !== null && $end !== null) {
      $range = $fmt($start) . ' to ' . $fmt($end);
    } elseif ($start !== null) {
      $range = 'From ' . $fmt($start);
    } else {
      $range = 'Until ' . $fmt($end);
    }

    return [
      'preset'      => $preset,
      'basis'       => $basis,
      'from'        => $start,
      'to'          => $end,
      'sql'         => '(' . implode(' AND ', $parts) . ')',
      'missing_sql' => $missing,
      'label'       => $range . ' (' . self::PERIOD_BASES[$basis] . ')',
      'missing_by'  => $basis === 'released' ? 'a release date' : 'a research end date',
    ];
  }

  private function validDate(string $value): ?string
  {
    $d = DateTime::createFromFormat('Y-m-d', trim($value));
    return ($d && $d->format('Y-m-d') === trim($value)) ? $d->format('Y-m-d') : null;
  }

  public const SORT_OPTIONS = [
    'newest'             => ['sql' => 'id DESC',                'label' => 'Newest Added'],
    'oldest'             => ['sql' => 'id ASC',                 'label' => 'Oldest Added'],
    'ipn_asc'            => ['sql' => 'reference_no ASC',       'label' => 'IPN (A–Z)'],
    'ipn_desc'           => ['sql' => 'reference_no DESC',      'label' => 'IPN (Z–A)'],
    'ar_asc'             => ['sql' => 'ar_number ASC',          'label' => 'AR No. (A–Z)'],
    'ar_desc'            => ['sql' => 'ar_number DESC',         'label' => 'AR No. (Z–A)'],
    'title_asc'          => ['sql' => 'title_of_research ASC',  'label' => 'Title (A–Z)'],
    'title_desc'         => ['sql' => 'title_of_research DESC', 'label' => 'Title (Z–A)'],
    'date_released_desc' => ['sql' => 'date_released DESC',     'label' => 'Date Released (Newest)'],
    'date_released_asc'  => ['sql' => 'date_released ASC',      'label' => 'Date Released (Oldest)'],
  ];

  private function sortClause(string $sort): string
  {
    return self::SORT_OPTIONS[$sort]['sql'] ?? self::SORT_OPTIONS['newest']['sql'];
  }

  // ===== RECORD LIST =====
  public function getAll(
    string $search = '',
    string $school = '',
    string $animalType = '',
    string $sex = '',
    string $researcherType = '',
    string $sort = 'newest',
    int $limit = 25,
    int $offset = 0,
    ?array $period = null
  ): array {
    [$where, $params, $types] = $this->buildFilters($search, $school, $animalType, $sex, $researcherType, $period);

    $stmt = $this->connection->prepare(
      "SELECT * FROM `records` $where ORDER BY {$this->sortClause($sort)} LIMIT ? OFFSET ?"
    );
    if (! $stmt) return [];

    $params[] = &$limit;
    $types .= 'i';
    $params[] = &$offset;
    $types .= 'i';

    if ($types) {
      array_unshift($params, $types);
      call_user_func_array([$stmt, 'bind_param'], $params);
    }

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  public function count(
    string $search = '',
    string $school = '',
    string $animalType = '',
    string $sex = '',
    string $researcherType = ''
  ): int {
    [$where, $params, $types] = $this->buildFilters($search, $school, $animalType, $sex, $researcherType);

    $stmt = $this->connection->prepare("SELECT COUNT(*) FROM `records` $where");
    if (! $stmt) return 0;

    if ($types) {
      array_unshift($params, $types);
      call_user_func_array([$stmt, 'bind_param'], $params);
    }

    $stmt->execute();
    return (int) $stmt->get_result()->fetch_row()[0];
  }


  // ===== STATISTICS =====
  public function stats(
    string $search = '',
    string $school = '',
    string $animalType = '',
    string $sex = '',
    string $researcherType = '',
    ?array $period = null
  ): array {
    [$where, $params, $types] = $this->buildFilters($search, $school, $animalType, $sex, $researcherType, $period);

    // ===== PERIOD FILTER =====
    $excludedByPeriod = 0;
    if ($period !== null) {
      [$baseWhere, $baseParams, $baseTypes] = $this->buildFilters($search, $school, $animalType, $sex, $researcherType);
      $excludedByPeriod = (int) ($this->scalarQuery(
        "SELECT COUNT(*) FROM `records` " . $this->andClause($baseWhere, $period['missing_sql']),
        $baseParams,
        $baseTypes
      ) ?? 0);
    }

    // ===== TOTALS =====
    $total = (int) ($this->scalarQuery("SELECT COUNT(*) FROM `records` $where", $params, $types) ?? 0);

    $processedThisMonth = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records` " . $this->andClause(
        $where,
        "date_released IS NOT NULL AND YEAR(date_released) = YEAR(CURDATE()) AND MONTH(date_released) = MONTH(CURDATE())"
      ),
      $params,
      $types
    ) ?? 0);

    $processedThisQuarter = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records` " . $this->andClause(
        $where,
        "date_released IS NOT NULL AND YEAR(date_released) = YEAR(CURDATE()) AND QUARTER(date_released) = QUARTER(CURDATE())"
      ),
      $params,
      $types
    ) ?? 0);

    $totalAnimals = (int) ($this->scalarQuery("SELECT COALESCE(SUM(animal_count), 0) FROM `records` $where", $params, $types) ?? 0);

    $animalBreakdown = $this->groupedQuery(
      'animal_type',
      $this->andClause($where, "animal_type IS NOT NULL AND animal_type != '' AND animal_count IS NOT NULL"),
      $params,
      $types,
      'SUM(animal_count)'
    );

    // ===== BREAKDOWNS =====
    $schoolBreakdown = $this->groupedQuery(
      'school',
      $this->andClause($where, "school IS NOT NULL AND school != ''"),
      $params,
      $types
    );

    $researcherTypeBreakdown = $this->groupedQuery(
      'researcher_type',
      $this->andClause($where, "researcher_type IS NOT NULL AND researcher_type != ''"),
      $params,
      $types
    );

    $sexBreakdown = $this->groupedQuery(
      'sex',
      $this->andClause($where, "sex IS NOT NULL AND sex != ''"),
      $params,
      $types
    );

    // ===== DATA QUALITY & DISTINCT COUNTS =====
    $incompleteCount = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records` " . $this->andClause(
        $where,
        "(animal_count IS NULL OR research_duration_start IS NULL OR research_duration_end IS NULL OR date_released IS NULL)"
      ),
      $params,
      $types
    ) ?? 0);

    $distinctSchools = (int) ($this->scalarQuery(
      "SELECT COUNT(DISTINCT school) FROM `records` " . $this->andClause($where, "school IS NOT NULL AND school != ''"),
      $params,
      $types
    ) ?? 0);

    $distinctResearchers = (int) ($this->scalarQuery(
      "SELECT COUNT(DISTINCT principal_investigator) FROM `records` " . $this->andClause($where, "principal_investigator IS NOT NULL AND principal_investigator != ''"),
      $params,
      $types
    ) ?? 0);

    $protocolsWithCount = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records` " . $this->andClause($where, "animal_count IS NOT NULL"),
      $params,
      $types
    ) ?? 0);
    $avgAnimalsPerProtocol = $protocolsWithCount > 0 ? round($totalAnimals / $protocolsWithCount, 1) : 0.0;

    // ===== STUDY STATUS =====
    $ongoingStudies = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records` " . $this->andClause(
        $where,
        "research_duration_start IS NOT NULL AND research_duration_end IS NOT NULL AND research_duration_end >= CURDATE()"
      ),
      $params,
      $types
    ) ?? 0);

    $completedStudies = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records` " . $this->andClause(
        $where,
        "research_duration_start IS NOT NULL AND research_duration_end IS NOT NULL AND research_duration_end < CURDATE()"
      ),
      $params,
      $types
    ) ?? 0);

    // ===== MONTHLY TREND =====
    $monthlyTrend = $this->quarterMonthlyTrend($where, $params, $types);

    $activeClearances = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records` " . $this->andClause($where, "research_duration_end IS NOT NULL AND research_duration_end >= CURDATE()"),
      $params,
      $types
    ) ?? 0);

    // ===== CLEARANCE EXPIRY =====
    $expiringSoon = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records` " . $this->andClause(
        $where,
        "research_duration_end IS NOT NULL AND research_duration_end >= CURDATE() AND research_duration_end <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
      ),
      $params,
      $types
    ) ?? 0);

    $expiredClearances = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records` " . $this->andClause($where, "research_duration_end IS NOT NULL AND research_duration_end < CURDATE()"),
      $params,
      $types
    ) ?? 0);

    $noDurationCount = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records` " . $this->andClause($where, "research_duration_end IS NULL"),
      $params,
      $types
    ) ?? 0);

    // ===== RELEASED THIS YEAR =====
    $releasedThisYear = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records` " . $this->andClause($where, "date_released IS NOT NULL AND YEAR(date_released) = YEAR(CURDATE())"),
      $params,
      $types
    ) ?? 0);

    // ===== SPECIES BREAKDOWN =====
    $speciesBreakdown = $this->groupedQuery(
      'animal_type',
      $this->andClause($where, "animal_type IS NOT NULL AND animal_type != ''"),
      $params,
      $types,
      'COALESCE(SUM(animal_count), 0)',
      ', COUNT(*) AS protocols'
    );

    // ===== PROTOCOL STATUS COUNTS =====
    $protocolsReviewed = $this->protocolStatusCount('Reviewed', $period);
    $protocolsEndorsed = $this->protocolStatusCount('Endorsed', $period);
    $thisMonth         = $this->resolvePeriod('this_month', 'released');
    $reviewedThisMonth = $this->protocolStatusCount('Reviewed', $thisMonth);
    $endorsedThisMonth = $this->protocolStatusCount('Endorsed', $thisMonth);

    // ===== RESULT =====
    return [
      'total'                      => $total,
      'processed_this_month'       => $processedThisMonth,
      'processed_this_quarter'     => $processedThisQuarter,
      'total_animals'              => $totalAnimals,
      'animal_breakdown'           => $animalBreakdown,
      'school_breakdown'           => $schoolBreakdown,
      'researcher_type_breakdown'  => $researcherTypeBreakdown,
      'sex_breakdown'              => $sexBreakdown,
      'incomplete_count'           => $incompleteCount,
      'distinct_schools'           => $distinctSchools,
      'distinct_researchers'       => $distinctResearchers,
      'protocols_with_count'       => $protocolsWithCount,
      'avg_animals_per_protocol'   => $avgAnimalsPerProtocol,
      'ongoing_studies'            => $ongoingStudies,
      'completed_studies'          => $completedStudies,
      'monthly_trend'              => $monthlyTrend,
      'active_clearances'          => $activeClearances,
      'expiring_soon'              => $expiringSoon,
      'expired_clearances'         => $expiredClearances,
      'no_duration_count'          => $noDurationCount,
      'released_this_year'         => $releasedThisYear,
      'species_breakdown'          => $speciesBreakdown,
      'protocols_reviewed'         => $protocolsReviewed,
      'protocols_endorsed'         => $protocolsEndorsed,
      'reviewed_this_month'        => $reviewedThisMonth,
      'endorsed_this_month'        => $endorsedThisMonth,
      'release_trend'              => $this->releaseTrend($where, $params, $types),
      'excluded_by_period'         => $excludedByPeriod,
    ];
  }

  // ===== STATISTICS HELPERS =====
  private function protocolStatusCount(string $status, ?array $period): int
  {
    $sql    = "SELECT COUNT(DISTINCT target_id) FROM `audit_logs` WHERE action = 'status_updated' AND target_type = 'protocol' AND details = ?";
    $detail = "Status changed to: $status";
    $params = [$detail];
    $types  = 's';

    if ($period !== null && $period['from'] !== null) {
      $sql .= ' AND created_at >= ?';
      $params[] = $period['from'] . ' 00:00:00';
      $types   .= 's';
    }
    if ($period !== null && $period['to'] !== null) {
      $sql .= ' AND created_at <= ?';
      $params[] = $period['to'] . ' 23:59:59';
      $types   .= 's';
    }

    return (int) ($this->scalarQuery($sql, $params, $types) ?? 0);
  }

  private function quarterMonthlyTrend(string $where, array $params, string $types): array
  {
    $sql = "SELECT MONTH(date_released) AS m, COUNT(*) AS total FROM `records` " . $this->andClause(
      $where,
      "date_released IS NOT NULL AND YEAR(date_released) = YEAR(CURDATE()) AND QUARTER(date_released) = QUARTER(CURDATE())"
    ) . " GROUP BY MONTH(date_released)";

    $stmt = $this->connection->prepare($sql);
    if (! $stmt) return [];

    if ($types) {
      $bound = $params;
      array_unshift($bound, $types);
      call_user_func_array([$stmt, 'bind_param'], $bound);
    }

    $stmt->execute();
    $counts = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
      $counts[(int) $row['m']] = (int) $row['total'];
    }

    $quarterStartMonth = (int) ((ceil((int) date('n') / 3) - 1) * 3 + 1);
    $trend = [];
    for ($i = 0; $i < 3; $i++) {
      $m = $quarterStartMonth + $i;
      $trend[] = [
        'month' => date('M', mktime(0, 0, 0, $m, 1)),
        'total' => $counts[$m] ?? 0,
      ];
    }
    return $trend;
  }

  private function releaseTrend(string $where, array $params, string $types): array
  {
    $sql = "SELECT DATE_FORMAT(date_released, '%Y-%m') AS ym, COUNT(*) AS total FROM `records` " . $this->andClause(
      $where,
      "date_released IS NOT NULL AND date_released >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)"
    ) . " GROUP BY ym";

    $stmt = $this->connection->prepare($sql);
    if (! $stmt) return [];

    if ($types) {
      $bound = $params;
      array_unshift($bound, $types);
      call_user_func_array([$stmt, 'bind_param'], $bound);
    }

    $stmt->execute();
    $counts = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
      $counts[$row['ym']] = (int) $row['total'];
    }

    $trend = [];
    for ($i = 11; $i >= 0; $i--) {
      $ts = strtotime(date('Y-m-01') . " -$i months");
      $trend[] = [
        'month' => date('M', $ts),
        'year'  => date('Y', $ts),
        'total' => $counts[date('Y-m', $ts)] ?? 0,
      ];
    }
    return $trend;
  }

  private function andClause(string $where, string $extra): string
  {
    return $where === '' ? "WHERE $extra" : "$where AND $extra";
  }

  private function scalarQuery(string $sql, array $params, string $types)
  {
    $stmt = $this->connection->prepare($sql);
    if (! $stmt) return null;

    if ($types) {
      $bound = $params;
      array_unshift($bound, $types);
      call_user_func_array([$stmt, 'bind_param'], $bound);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    return $row ? $row[0] : null;
  }

  private function groupedQuery(string $column, string $where, array $params, string $types, string $aggExpr = 'COUNT(*)', string $extraSelect = ''): array
  {
    $stmt = $this->connection->prepare(
      "SELECT `$column` AS label, $aggExpr AS total$extraSelect FROM `records` $where GROUP BY `$column` ORDER BY total DESC"
    );
    if (! $stmt) return [];

    if ($types) {
      $bound = $params;
      array_unshift($bound, $types);
      call_user_func_array([$stmt, 'bind_param'], $bound);
    }

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  public function distinctValues(string $column): array
  {
    $allowed = ['school', 'animal_type', 'sex', 'researcher_type'];
    if (! in_array($column, $allowed, true)) return [];

    $result = $this->connection->query(
      "SELECT DISTINCT `$column` FROM `records`
             WHERE `$column` IS NOT NULL AND `$column` != ''
             ORDER BY `$column`"
    );
    if (! $result) return [];

    return array_column($result->fetch_all(MYSQLI_ASSOC), $column);
  }

  // ===== LOOKUPS =====
  public function getById(int $id): ?array
  {
    $stmt = $this->connection->prepare("SELECT * FROM `records` WHERE id = ?");
    if (! $stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }

  public function exists(int $id): bool
  {
    $stmt = $this->connection->prepare("SELECT 1 FROM `records` WHERE id = ?");
    if (! $stmt) return false;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_row();
  }

  public function refExists(string $ref): bool
  {
    $stmt = $this->connection->prepare("SELECT 1 FROM `records` WHERE reference_no = ?");
    if (! $stmt) return false;
    $stmt->bind_param('s', $ref);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_row();
  }

  public function arExists(string $ar, int $exceptId = 0): bool
  {
    $stmt = $this->connection->prepare("SELECT 1 FROM `records` WHERE ar_number = ? AND id != ?");
    if (! $stmt) return false;
    $stmt->bind_param('si', $ar, $exceptId);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_row();
  }

  // ===== ADD RECORD =====
  public function insert(array $d): bool
  {
    $stmt = $this->connection->prepare(
      "INSERT INTO `records`
             (reference_no, ar_number, title_of_research, school, animal_type, animal_count,
              principal_investigator, sex, researcher_type, research_adviser,
              veterinarian, research_duration_start, research_duration_end, date_released, received_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    if (! $stmt) return false;

    $arNumber    = $d['ar_number'] !== '' ? $d['ar_number'] : null;
    $animalCount = isset($d['animal_count']) && $d['animal_count'] !== '' ? (int)$d['animal_count'] : null;
    $durationStart = $d['research_duration_start'] !== '' ? $d['research_duration_start'] : null;
    $durationEnd   = $d['research_duration_end'] !== '' ? $d['research_duration_end'] : null;
    $dateReleased = $d['date_released'] !== '' ? $d['date_released'] : null;

    $stmt->bind_param(
      'sssssisssssssss',
      $d['reference_no'],
      $arNumber,
      $d['title_of_research'],
      $d['school'],
      $d['animal_type'],
      $animalCount,
      $d['principal_investigator'],
      $d['sex'],
      $d['researcher_type'],
      $d['research_adviser'],
      $d['veterinarian'],
      $durationStart,
      $durationEnd,
      $dateReleased,
      $d['received_by']
    );
    return $stmt->execute();
  }

  public function insertFromProtocol(string $refNo, string $title, string $pi, string $school = '', ?int $userId = null, ?int $protocolId = null, ?string $sex = null, ?string $filePath = null, ?string $fileOriginalName = null): bool
  {
    if ($refNo !== '' && $this->refExists($refNo)) {
      return false;
    }

    $refNoOrNull = $refNo !== '' ? $refNo : null;

    $stmt = $this->connection->prepare(
      "INSERT INTO `records` (reference_no, title_of_research, principal_investigator, school, sex, user_id, protocol_id, file_path, file_original_name)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (! $stmt) return false;
    $stmt->bind_param('sssssiiss', $refNoOrNull, $title, $pi, $school, $sex, $userId, $protocolId, $filePath, $fileOriginalName);
    return $stmt->execute();
  }

  // ===== PROTOCOL LINK =====
  public function getByProtocolId(int $protocolId): ?array
  {
    $stmt = $this->connection->prepare("SELECT * FROM `records` WHERE protocol_id = ?");
    if (! $stmt) return null;
    $stmt->bind_param('i', $protocolId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
  }

  public function setReferenceNoByProtocolId(int $protocolId, string $refNo): bool
  {
    $stmt = $this->connection->prepare("UPDATE `records` SET reference_no = ? WHERE protocol_id = ?");
    if (! $stmt) return false;
    $stmt->bind_param('si', $refNo, $protocolId);
    return $stmt->execute();
  }

  public function setArNumberByProtocolId(int $protocolId, string $arNumber): bool
  {
    $stmt = $this->connection->prepare("UPDATE `records` SET ar_number = ? WHERE protocol_id = ?");
    if (! $stmt) return false;
    $stmt->bind_param('si', $arNumber, $protocolId);
    return $stmt->execute();
  }

  // ===== EDIT & DELETE =====
  public function update(array $d): bool
  {
    $stmt = $this->connection->prepare(
      "UPDATE `records` SET
               reference_no             = ?,
               ar_number                = ?,
               title_of_research        = ?,
               school                   = ?,
               animal_type              = ?,
               animal_count             = ?,
               principal_investigator   = ?,
               sex                   = ?,
               researcher_type          = ?,
               research_adviser         = ?,
               veterinarian             = ?,
               research_duration_start  = ?,
               research_duration_end    = ?,
               date_released            = ?,
               received_by              = ?
             WHERE id = ?"
    );
    if (! $stmt) return false;

    $ref          = $d['reference_no'] !== '' ? $d['reference_no'] : null;
    $arNumber     = $d['ar_number'] !== '' ? $d['ar_number'] : null;
    $animalCount  = isset($d['animal_count']) && $d['animal_count'] !== '' ? (int)$d['animal_count'] : null;
    $durationStart = $d['research_duration_start'] !== '' ? $d['research_duration_start'] : null;
    $durationEnd   = $d['research_duration_end'] !== '' ? $d['research_duration_end'] : null;
    $dateReleased = $d['date_released'] !== '' ? $d['date_released'] : null;

    $stmt->bind_param(
      'sssssisssssssssi',
      $ref,
      $arNumber,
      $d['title_of_research'],
      $d['school'],
      $d['animal_type'],
      $animalCount,
      $d['principal_investigator'],
      $d['sex'],
      $d['researcher_type'],
      $d['research_adviser'],
      $d['veterinarian'],
      $durationStart,
      $durationEnd,
      $dateReleased,
      $d['received_by'],
      $d['id']
    );
    return $stmt->execute();
  }

  public function delete(int $id): bool
  {
    $stmt = $this->connection->prepare("DELETE FROM `records` WHERE id = ?");
    if (! $stmt) return false;
    $stmt->bind_param('i', $id);
    return $stmt->execute();
  }

  // ===== AUTOMATIC DEACTIVATION ON CLEARANCE EXPIRY =====

  public function getUsersWithExpiredClearances(): array
  {
    $stmt = $this->connection->prepare(
      "SELECT DISTINCT r.user_id
       FROM `records` r
       JOIN `users` u ON u.id = r.user_id
       WHERE r.user_id IS NOT NULL
         AND r.research_duration_end IS NOT NULL
         AND r.research_duration_end < CURDATE()
         AND u.status = 'active'"
    );
    if (! $stmt) return [];
    $stmt->execute();
    return array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'user_id');
  }

  public function runExpiryDeactivationSweep(): int
  {
    require_once dirname(__DIR__) . '/models/ProtocolModel.php';
    require_once dirname(__DIR__) . '/models/UserModel.php';

    $protocolModel = new ProtocolModel();
    $userModel     = new UserModel();

    $deactivated = 0;
    foreach ($this->getUsersWithExpiredClearances() as $userId) {
      $userId = (int) $userId;
      if ($protocolModel->hasActiveProtocols($userId)) {
        continue;
      }
      if ($userModel->deactivateUser($userId)) {
        $userModel->logAudit(
          'account_deactivated',
          null,
          'System',
          'system',
          'user',
          $userId,
          'Auto-deactivated: animal research clearance expired with no protocols pending'
        );
        $deactivated++;
      }
    }

    return $deactivated;
  }
}
