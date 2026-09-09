<?php

require_once dirname(__DIR__) . '/core/Model.php';

class RecordModel extends Model
{
  private function buildFilters(
    string $search,
    string $school,
    string $animalType,
    string $gender,
    string $researcherType
  ): array {
    $conditions = [];
    $params     = [];
    $types      = '';

    if ($search !== '') {
      $like = '%' . $search . '%';
      $conditions[] = "(reference_no LIKE ? OR title_of_research LIKE ? OR school LIKE ?
                              OR animal_type LIKE ? OR principal_investigator LIKE ?
                              OR gender LIKE ? OR researcher_type LIKE ?
                              OR research_adviser LIKE ? OR veterinarian LIKE ?
                              OR received_by LIKE ?)";
      for ($i = 0; $i < 10; $i++) {
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
    if ($gender !== '') {
      $conditions[] = 'gender = ?';
      $params[] = &$gender;
      $types .= 's';
    }
    if ($researcherType !== '') {
      $conditions[] = 'researcher_type = ?';
      $params[] = &$researcherType;
      $types .= 's';
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    return [$where, $params, $types];
  }

  public const SORT_OPTIONS = [
    'newest'             => ['sql' => 'id DESC',                'label' => 'Newest Added'],
    'oldest'             => ['sql' => 'id ASC',                 'label' => 'Oldest Added'],
    'ipn_asc'            => ['sql' => 'reference_no ASC',       'label' => 'IPN (A–Z)'],
    'ipn_desc'           => ['sql' => 'reference_no DESC',      'label' => 'IPN (Z–A)'],
    'title_asc'          => ['sql' => 'title_of_research ASC',  'label' => 'Title (A–Z)'],
    'title_desc'         => ['sql' => 'title_of_research DESC', 'label' => 'Title (Z–A)'],
    'date_released_desc' => ['sql' => 'date_released DESC',     'label' => 'Date Released (Newest)'],
    'date_released_asc'  => ['sql' => 'date_released ASC',      'label' => 'Date Released (Oldest)'],
  ];

  private function sortClause(string $sort): string
  {
    return self::SORT_OPTIONS[$sort]['sql'] ?? self::SORT_OPTIONS['newest']['sql'];
  }

  public function getAll(
    string $search = '',
    string $school = '',
    string $animalType = '',
    string $gender = '',
    string $researcherType = '',
    string $sort = 'newest',
    int $limit = 25,
    int $offset = 0
  ): array {
    [$where, $params, $types] = $this->buildFilters($search, $school, $animalType, $gender, $researcherType);

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
    string $gender = '',
    string $researcherType = ''
  ): int {
    [$where, $params, $types] = $this->buildFilters($search, $school, $animalType, $gender, $researcherType);

    $stmt = $this->connection->prepare("SELECT COUNT(*) FROM `records` $where");
    if (! $stmt) return 0;

    if ($types) {
      array_unshift($params, $types);
      call_user_func_array([$stmt, 'bind_param'], $params);
    }

    $stmt->execute();
    return (int) $stmt->get_result()->fetch_row()[0];
  }


  public function stats(
    string $search = '',
    string $school = '',
    string $animalType = '',
    string $gender = '',
    string $researcherType = ''
  ): array {
    [$where, $params, $types] = $this->buildFilters($search, $school, $animalType, $gender, $researcherType);

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
      'gender',
      $this->andClause($where, "gender IS NOT NULL AND gender != ''"),
      $params,
      $types
    );

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

    $distinctAdvisers = (int) ($this->scalarQuery(
      "SELECT COUNT(DISTINCT research_adviser) FROM `records` " . $this->andClause($where, "research_adviser IS NOT NULL AND research_adviser != ''"),
      $params,
      $types
    ) ?? 0);

    $protocolsWithCount = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records` " . $this->andClause($where, "animal_count IS NOT NULL"),
      $params,
      $types
    ) ?? 0);
    $avgAnimalsPerProtocol = $protocolsWithCount > 0 ? round($totalAnimals / $protocolsWithCount, 1) : 0.0;

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

    $monthlyTrend = $this->quarterMonthlyTrend($where, $params, $types);

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
      'distinct_advisers'          => $distinctAdvisers,
      'protocols_with_count'       => $protocolsWithCount,
      'avg_animals_per_protocol'   => $avgAnimalsPerProtocol,
      'ongoing_studies'            => $ongoingStudies,
      'completed_studies'          => $completedStudies,
      'monthly_trend'              => $monthlyTrend,
    ];
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

  private function groupedQuery(string $column, string $where, array $params, string $types, string $aggExpr = 'COUNT(*)'): array
  {
    $stmt = $this->connection->prepare(
      "SELECT `$column` AS label, $aggExpr AS total FROM `records` $where GROUP BY `$column` ORDER BY total DESC"
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
    $allowed = ['school', 'animal_type', 'gender', 'researcher_type'];
    if (! in_array($column, $allowed, true)) return [];

    $result = $this->connection->query(
      "SELECT DISTINCT `$column` FROM `records`
             WHERE `$column` IS NOT NULL AND `$column` != ''
             ORDER BY `$column`"
    );
    if (! $result) return [];

    return array_column($result->fetch_all(MYSQLI_ASSOC), $column);
  }

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

  public function insert(array $d): bool
  {
    $stmt = $this->connection->prepare(
      "INSERT INTO `records`
             (reference_no, title_of_research, school, animal_type, animal_count,
              principal_investigator, gender, researcher_type, research_adviser,
              veterinarian, research_duration_start, research_duration_end, date_released, received_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    if (! $stmt) return false;

    $animalCount = isset($d['animal_count']) && $d['animal_count'] !== '' ? (int)$d['animal_count'] : null;
    $durationStart = $d['research_duration_start'] !== '' ? $d['research_duration_start'] : null;
    $durationEnd   = $d['research_duration_end'] !== '' ? $d['research_duration_end'] : null;
    $dateReleased = $d['date_released'] !== '' ? $d['date_released'] : null;

    $stmt->bind_param(
      'ssssisssssssss',
      $d['reference_no'],
      $d['title_of_research'],
      $d['school'],
      $d['animal_type'],
      $animalCount,
      $d['principal_investigator'],
      $d['gender'],
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

  public function insertFromProtocol(string $refNo, string $title, string $pi, string $school = ''): bool
  {
    if ($refNo !== '' && $this->refExists($refNo)) {
      return false;
    }

    $refNoOrNull = $refNo !== '' ? $refNo : null;

    $stmt = $this->connection->prepare(
      "INSERT INTO `records` (reference_no, title_of_research, principal_investigator, school)
             VALUES (?, ?, ?, ?)"
    );
    if (! $stmt) return false;
    $stmt->bind_param('ssss', $refNoOrNull, $title, $pi, $school);
    return $stmt->execute();
  }

  public function update(array $d): bool
  {
    $stmt = $this->connection->prepare(
      "UPDATE `records` SET
               reference_no             = ?,
               title_of_research        = ?,
               school                   = ?,
               animal_type              = ?,
               animal_count             = ?,
               principal_investigator   = ?,
               gender                   = ?,
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
    $animalCount  = isset($d['animal_count']) && $d['animal_count'] !== '' ? (int)$d['animal_count'] : null;
    $durationStart = $d['research_duration_start'] !== '' ? $d['research_duration_start'] : null;
    $durationEnd   = $d['research_duration_end'] !== '' ? $d['research_duration_end'] : null;
    $dateReleased = $d['date_released'] !== '' ? $d['date_released'] : null;

    $stmt->bind_param(
      'ssssisssssssssi',
      $ref,
      $d['title_of_research'],
      $d['school'],
      $d['animal_type'],
      $animalCount,
      $d['principal_investigator'],
      $d['gender'],
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
}
