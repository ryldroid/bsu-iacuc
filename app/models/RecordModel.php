<?php

class RecordModel extends Model
{
  // ===== STATUS OPTIONS =====
  public const STATUSES = ['Ongoing', 'Completed', 'Cancelled'];
  public const DEFAULT_STATUS = 'Ongoing';

  private function cleanStatus(?string $status): string
  {
    return in_array($status, self::STATUSES, true) ? $status : self::DEFAULT_STATUS;
  }

  // ===== FILTERS & PERIOD =====
  private function buildFilters(
    string $search,
    string $school,
    string $animalType,
    string $sex,
    string $researcherType,
    ?array $period = null,
    string $status = ''
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

    if ($status !== '') {
      $conditions[] = 'status = ?';
      $params[] = &$status;
      $types .= 's';
    }

    if ($period !== null) {
      $conditions[] = $period['sql'];
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    return [$where, $params, $types];
  }

  public const PERIOD_PRESETS = [
    'this_month'     => 'This month',
    'last_month'     => 'Last month',
    'this_quarter'   => 'This quarter',
    'this_year'      => 'This year',
    'last_year'      => 'Last year',
    'last_12_months' => 'Last 12 months',
    'all'            => 'All time',
    'custom'         => 'Custom range',
  ];

  public const DEFAULT_PERIOD = 'this_month';

  // A record's date for period purposes: the release date if staff filled it in,
  // otherwise the day its protocol was marked Reviewed (when the record was created).
  private const RECORD_DATE_SQL = "COALESCE(date_released, (
      SELECT DATE(MIN(al.created_at)) FROM `audit_logs` al
      WHERE al.action = 'status_updated' AND al.target_type = 'protocol'
        AND al.target_id = records.protocol_id
        AND al.details = 'Status changed to: Reviewed'))";

  public function resolvePeriod(string $preset, string $from = '', string $to = ''): ?array
  {
    if (! isset(self::PERIOD_PRESETS[$preset]) || $preset === 'all') return null;

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

    $parts = [];
    if ($start !== null) $parts[] = self::RECORD_DATE_SQL . " >= '$start'";
    if ($end !== null)   $parts[] = self::RECORD_DATE_SQL . " <= '$end'";

    $fmt = fn(string $d) => date(DATE_FORMAT, strtotime($d));
    if ($start !== null && $end !== null) {
      $label = $fmt($start) . ' to ' . $fmt($end);
    } elseif ($start !== null) {
      $label = 'From ' . $fmt($start);
    } else {
      $label = 'Until ' . $fmt($end);
    }

    return [
      'preset'      => $preset,
      'from'        => $start,
      'to'          => $end,
      'sql'         => '(' . implode(' AND ', $parts) . ')',
      'missing_sql' => self::RECORD_DATE_SQL . ' IS NULL',
      'label'       => $label,
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
    ?array $period = null,
    string $status = ''
  ): array {
    [$where, $params, $types] = $this->buildFilters($search, $school, $animalType, $sex, $researcherType, $period, $status);

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
    string $researcherType = '',
    string $status = ''
  ): int {
    [$where, $params, $types] = $this->buildFilters($search, $school, $animalType, $sex, $researcherType, null, $status);

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
  public function stats(?array $period = null): array
  {
    // Record-based stats use the period's record date; workflow stats use event timestamps.
    [$where, $params, $types] = $this->buildFilters('', '', '', '', '', $period);

    // ===== PROTOCOL WORKFLOW (audit log / uploads, within the period) =====
    $reviewed = $this->protocolEventCount('status_updated', 'Status changed to: Reviewed', $period);
    $endorsed = $this->protocolEventCount('status_updated', 'Status changed to: Endorsed', $period);
    $signed   = $this->protocolEventCount('signed_scan_uploaded', null, $period);
    $revised  = $this->protocolEventCount('protocol_revised', null, $period);

    $revisionSubmissions = $this->protocolEventCount('protocol_revised', null, $period, true);
    $avgRevisions        = $revised > 0 ? round($revisionSubmissions / $revised, 1) : 0.0;

    [$window, $windowParams, $windowTypes] = $this->timeWindow('pv.uploaded_at', $period);
    $clearancesReturned = (int) ($this->scalarQuery(
      "SELECT COUNT(DISTINCT pv.protocol_id) FROM `protocol_versions` pv
       JOIN `protocols` p ON p.id = pv.protocol_id AND p.deleted_at IS NULL
       WHERE pv.file_type = 'clearance'$window",
      $windowParams,
      $windowTypes
    ) ?? 0);

    // ===== REVISIONS PER PROTOCOL / PER PI =====
    [$window, $windowParams, $windowTypes] = $this->timeWindow('al.created_at', $period);
    $revisionsByProtocol = $this->rows(
      "SELECT p.title AS label,
              CONCAT(p.title, ' (', COALESCE(NULLIF(p.ar_number, ''), NULLIF(p.reference_no, ''), CONCAT('Protocol #', p.id)), ')') AS tooltip,
              COUNT(*) AS total
       FROM `audit_logs` al
       JOIN `protocols` p ON p.id = al.target_id AND p.deleted_at IS NULL
       WHERE al.action = 'protocol_revised' AND al.target_type = 'protocol'$window
       GROUP BY p.id ORDER BY total DESC, p.id ASC LIMIT 8",
      $windowParams,
      $windowTypes
    );
    $revisionsByPi = $this->rows(
      "SELECT TRIM(CONCAT(u.first_name, ' ', u.last_name)) AS label,
              COUNT(*) AS total, COUNT(DISTINCT p.id) AS protocols
       FROM `audit_logs` al
       JOIN `protocols` p ON p.id = al.target_id AND p.deleted_at IS NULL
       JOIN `users` u ON u.id = p.user_id
       WHERE al.action = 'protocol_revised' AND al.target_type = 'protocol'$window
       GROUP BY u.id ORDER BY total DESC, label ASC LIMIT 8",
      $windowParams,
      $windowTypes
    );

    // ===== RECORDS (within the period) =====
    $totalRecords    = (int) ($this->scalarQuery("SELECT COUNT(*) FROM `records` $where", $params, $types) ?? 0);
    $totalRecordsAll = (int) ($this->scalarQuery("SELECT COUNT(*) FROM `records`", [], '') ?? 0);

    $totalAnimals = (int) ($this->scalarQuery("SELECT COALESCE(SUM(animal_count), 0) FROM `records` $where", $params, $types) ?? 0);
    $withCount    = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records` " . $this->andClause($where, "animal_count IS NOT NULL"),
      $params,
      $types
    ) ?? 0);

    $speciesBreakdown = $this->groupedQuery(
      'animal_type',
      $this->andClause($where, "animal_type IS NOT NULL AND animal_type != '' AND animal_count IS NOT NULL"),
      $params,
      $types,
      'SUM(animal_count)',
      ', COUNT(*) AS protocols'
    );
    $schoolBreakdown = $this->groupedQuery(
      'school',
      $this->andClause($where, "school IS NOT NULL AND school != ''"),
      $params,
      $types
    );
    $sexBreakdown = $this->groupedQuery(
      'sex',
      $this->andClause($where, "sex IS NOT NULL AND sex != ''"),
      $params,
      $types
    );
    $researcherTypeBreakdown = $this->groupedQuery(
      'researcher_type',
      $this->andClause($where, "researcher_type IS NOT NULL AND researcher_type != ''"),
      $params,
      $types
    );

    $incompleteCount = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records` " . $this->andClause(
        $where,
        "(animal_count IS NULL OR animal_type IS NULL OR animal_type = '' OR researcher_type IS NULL OR researcher_type = '')"
      ),
      $params,
      $types
    ) ?? 0);

    $excludedByPeriod = 0;
    if ($period !== null) {
      $excludedByPeriod = (int) ($this->scalarQuery(
        "SELECT COUNT(*) FROM `records` WHERE " . $period['missing_sql'],
        [],
        ''
      ) ?? 0);
    }

    // ===== ACTIVE CLEARANCES (snapshot as of today, not limited to the period) =====
    $activeClearances = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records` WHERE research_duration_end IS NOT NULL AND research_duration_end >= CURDATE()",
      [],
      ''
    ) ?? 0);
    $expiringSoon = (int) ($this->scalarQuery(
      "SELECT COUNT(*) FROM `records`
       WHERE research_duration_end IS NOT NULL AND research_duration_end >= CURDATE()
         AND research_duration_end <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)",
      [],
      ''
    ) ?? 0);

    return [
      'reviewed'                  => $reviewed,
      'revised'                   => $revised,
      'revision_submissions'      => $revisionSubmissions,
      'avg_revisions'             => $avgRevisions,
      'revisions_by_protocol'     => $revisionsByProtocol,
      'revisions_by_pi'           => $revisionsByPi,
      'endorsed'                  => $endorsed,
      'signed'                    => $signed,
      'clearances_returned'       => $clearancesReturned,
      'total_records'             => $totalRecords,
      'total_records_all'         => $totalRecordsAll,
      'active_clearances'         => $activeClearances,
      'expiring_soon'             => $expiringSoon,
      'total_animals'             => $totalAnimals,
      'avg_animals_per_record'    => $withCount > 0 ? round($totalAnimals / $withCount, 1) : 0.0,
      'species_breakdown'         => $speciesBreakdown,
      'school_breakdown'          => $schoolBreakdown,
      'sex_breakdown'             => $sexBreakdown,
      'researcher_type_breakdown' => $researcherTypeBreakdown,
      'incomplete_count'          => $incompleteCount,
      'excluded_by_period'        => $excludedByPeriod,
    ];
  }

  // ===== STATISTICS HELPERS =====

  // Protocols (or raw events when $countEvents) that logged $action within the period.
  private function protocolEventCount(string $action, ?string $detail, ?array $period, bool $countEvents = false): int
  {
    [$window, $params, $types] = $this->timeWindow('al.created_at', $period);

    $sql = "SELECT " . ($countEvents ? 'COUNT(*)' : 'COUNT(DISTINCT al.target_id)') . "
            FROM `audit_logs` al
            JOIN `protocols` p ON p.id = al.target_id AND p.deleted_at IS NULL
            WHERE al.target_type = 'protocol' AND al.action = ?";
    array_unshift($params, $action);
    $types = 's' . $types;

    if ($detail !== null) {
      $sql .= ' AND al.details = ?';
      array_splice($params, 1, 0, [$detail]);
      $types = 's' . $types;
    }

    return (int) ($this->scalarQuery($sql . $window, $params, $types) ?? 0);
  }

  // Extra "AND column BETWEEN period" fragment for timestamp columns.
  private function timeWindow(string $column, ?array $period): array
  {
    $sql    = '';
    $params = [];
    $types  = '';

    if ($period !== null && $period['from'] !== null) {
      $sql     .= " AND $column >= ?";
      $params[] = $period['from'] . ' 00:00:00';
      $types   .= 's';
    }
    if ($period !== null && $period['to'] !== null) {
      $sql     .= " AND $column <= ?";
      $params[] = $period['to'] . ' 23:59:59';
      $types   .= 's';
    }

    return [$sql, $params, $types];
  }

  // bind_param needs references; plain values trigger PHP 8 warnings via call_user_func_array.
  private function bindAll(mysqli_stmt $stmt, array $params, string $types): void
  {
    if ($types === '') return;

    $bound = [$types];
    foreach (array_keys($params) as $i) {
      $bound[] = &$params[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bound);
  }

  private function rows(string $sql, array $params, string $types): array
  {
    $stmt = $this->connection->prepare($sql);
    if (! $stmt) return [];

    $this->bindAll($stmt, $params, $types);

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }

  private function andClause(string $where, string $extra): string
  {
    return $where === '' ? "WHERE $extra" : "$where AND $extra";
  }

  private function scalarQuery(string $sql, array $params, string $types)
  {
    $stmt = $this->connection->prepare($sql);
    if (! $stmt) return null;

    $this->bindAll($stmt, $params, $types);

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

    $this->bindAll($stmt, $params, $types);

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
              veterinarian, research_duration_start, research_duration_end, date_released, received_by, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    if (! $stmt) return false;

    $arNumber    = $d['ar_number'] !== '' ? $d['ar_number'] : null;
    $animalCount = isset($d['animal_count']) && $d['animal_count'] !== '' ? (int)$d['animal_count'] : null;
    $durationStart = $d['research_duration_start'] !== '' ? $d['research_duration_start'] : null;
    $durationEnd   = $d['research_duration_end'] !== '' ? $d['research_duration_end'] : null;
    $dateReleased = $d['date_released'] !== '' ? $d['date_released'] : null;
    $status       = $this->cleanStatus($d['status'] ?? null);

    $stmt->bind_param(
      'sssssissssssssss',
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
      $d['received_by'],
      $status
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

  // ===== SNAPSHOT FROM PROTOCOL =====
  // Creates the record for a Reviewed protocol (once) and attaches its latest protocol file.
  public function snapshotFromProtocol(array $protocol, ?array $version = null): bool
  {
    $protocolId = (int) $protocol['protocol_id'];
    if ($this->getByProtocolId($protocolId)) {
      return true;
    }

    $filePath     = null;
    $fileOriginal = null;

    if ($version) {
      $root       = dirname(__DIR__, 2);
      $source     = $root . '/storage/uploads/protocols/' . $version['file_path'];
      $recordsDir = $root . '/storage/uploads/records/';

      if (!is_dir($recordsDir)) {
        @mkdir($recordsDir, 0750, true);
      }

      $ext         = pathinfo($version['file_path'], PATHINFO_EXTENSION) ?: 'pdf';
      $safeName    = bin2hex(random_bytes(8)) . '.' . $ext;
      $destination = $recordsDir . $safeName;

      // Hard link saves disk space, but link() is disabled on some hosts, so fall back to a copy.
      $stored = is_file($source) && (
        (function_exists('link') && @link($source, $destination)) || @copy($source, $destination)
      );

      if ($stored) {
        $filePath     = $safeName;
        $fileOriginal = $version['original_name'] ?: basename($source);
      } else {
        error_log("Could not attach protocol file to record snapshot (protocol #$protocolId).");
      }
    }

    $pi = trim(($protocol['submitter_first_name'] ?? '') . ' ' . ($protocol['submitter_last_name'] ?? ''));

    return $this->insertFromProtocol(
      $protocol['reference_no'] ?? '',
      $protocol['research_title'] ?? '',
      $pi,
      $protocol['submitter_school'] ?? '',
      (int) $protocol['user_id'],
      $protocolId,
      $protocol['submitter_sex'] ?? null,
      $filePath,
      $fileOriginal
    );
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
               received_by              = ?,
               status                   = ?
             WHERE id = ?"
    );
    if (! $stmt) return false;

    $ref          = $d['reference_no'] !== '' ? $d['reference_no'] : null;
    $arNumber     = $d['ar_number'] !== '' ? $d['ar_number'] : null;
    $animalCount  = isset($d['animal_count']) && $d['animal_count'] !== '' ? (int)$d['animal_count'] : null;
    $durationStart = $d['research_duration_start'] !== '' ? $d['research_duration_start'] : null;
    $durationEnd   = $d['research_duration_end'] !== '' ? $d['research_duration_end'] : null;
    $dateReleased = $d['date_released'] !== '' ? $d['date_released'] : null;
    $status       = $this->cleanStatus($d['status'] ?? null);

    $stmt->bind_param(
      'sssssissssssssssi',
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
      $status,
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
