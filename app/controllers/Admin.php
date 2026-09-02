<?php

class Admin extends Controller
{
    public UserModel $model;

    public function __construct()
    {
        require_once dirname(__DIR__) . '/models/UserModel.php';
        require_once dirname(__DIR__) . '/models/RecordModel.php';
        require_once dirname(__DIR__) . '/models/ProtocolModel.php';

        $this->model = new UserModel();
    }

    // ===== ACCESS CONTROL =====

    private function requireAdmin(bool $ajax = false): void
    {
        $this->requireStaff($ajax);
        if ($_SESSION['user']['role'] !== 'admin') {
            $ajax
                ? $this->jsonError(403, 'Admin access required.')
                : $this->redirect('admin/home');
        }
    }

    private function requireStaff(bool $ajax = false): void
    {
        if (!$this->isLoggedIn()) {
            $ajax
                ? $this->jsonError(401, 'Your session has expired. Please log in again.')
                : $this->redirect('admin/login');
        }
        if (!in_array($_SESSION['user']['role'] ?? '', ['admin', 'reviewer'])) {
            $ajax
                ? $this->jsonError(401, 'Your session has expired. Please log in again.')
                : $this->redirect('admin/login');
        }
    }

    protected function jsonError(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $message]);
        exit;
    }

    // ===== ADMIN PAGES =====

    public function home(): void
    {
        $this->requireStaff();

        $model     = new ProtocolModel();
        $protocols = $model->getAll();

        $statuses = ['Under Review', 'Needs Revision', 'Reviewed', 'Endorsed', 'Approved'];

        $this->view('admin/home', [
            'user'      => $_SESSION['user'],
            'csrf'      => $this->generateCsrfToken(),
            'protocols' => $protocols,
            'statuses'  => $statuses,
        ]);
    }

    public function records(): void
    {
        $this->requireStaff();

        $model          = new RecordModel();
        $search         = trim($_GET['search'] ?? '');
        $school         = trim($_GET['school'] ?? '');
        $animalType     = trim($_GET['animal'] ?? '');
        $gender         = trim($_GET['gender'] ?? '');
        $researcherType = trim($_GET['rtype']  ?? '');
        $perPage        = 25;
        $page           = max(1, (int) ($_GET['page'] ?? 1));
        $offset         = ($page - 1) * $perPage;

        $total      = $model->count($search, $school, $animalType, $gender, $researcherType);
        $records    = $model->getAll($search, $school, $animalType, $gender, $researcherType, $perPage, $offset);
        $totalPages = (int) ceil($total / $perPage);

        $this->view('admin/records', [
            'user'            => $_SESSION['user'],
            'csrf'            => $this->generateCsrfToken(),
            'records'         => $records,
            'total'           => $total,
            'page'            => $page,
            'totalPages'      => $totalPages,
            'perPage'         => $perPage,
            'search'          => $search,
            'school'          => $school,
            'animalType'      => $animalType,
            'gender'          => $gender,
            'researcherType'  => $researcherType,
            'schools'         => $model->distinctValues('school'),
            'animalTypes'     => $model->distinctValues('animal_type'),
            'genders'         => $model->distinctValues('gender'),
            'researcherTypes' => $model->distinctValues('researcher_type'),
            'flash_success'   => $_SESSION['flash_success'] ?? '',
            'flash_error'     => $_SESSION['flash_error']   ?? '',
        ]);
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    }

    // ===== Records AJAX: add =====

    public function records_add(): void
    {
        $this->requireAdmin(true);
        $this->requirePostMethod();
        $this->verifyCsrfToken(false);
        header('Content-Type: application/json');

        $model = new RecordModel();
        $ref   = trim($_POST['reference_no'] ?? '');

        if ($ref === '') {
            $this->jsonError(422, 'IPN is required.');
        }
        if ($model->refExists($ref)) {
            $this->jsonError(422, 'That IPN already exists.');
        }

        $d                 = $this->sanitizeRecordPost();
        $d['reference_no'] = $ref;
        $ok                = $model->insert($d);

        if ($ok) {
            $actor = $this->actor();
            $model->logAudit('record_added', $actor['id'], $actor['name'], $actor['role'], 'record', null, "Record added: $ref");
        }

        echo json_encode(['ok' => $ok, 'message' => $ok ? 'Record added.' : 'Insert failed.']);
        exit;
    }

    // ===== Records AJAX: get (for edit modal) =====

    public function records_get(): void
    {
        $this->requireAdmin(true);
        header('Content-Type: application/json');

        $model = new RecordModel();
        $id    = (int) ($_GET['id'] ?? 0);
        $row   = $id > 0 ? $model->getById($id) : null;

        echo json_encode(
            $row
                ? ['ok' => true, 'data' => $row]
                : ['ok' => false, 'message' => 'Record not found.']
        );
        exit;
    }

    // ===== Records AJAX: edit =====

    public function records_edit(): void
    {
        $this->requireAdmin(true);
        $this->requirePostMethod();
        $this->verifyCsrfToken(false);
        header('Content-Type: application/json');

        $model = new RecordModel();
        $id    = (int) ($_POST['id'] ?? 0);

        if ($id < 1 || !$model->exists($id)) {
            $this->jsonError(404, 'Record not found.');
        }

        $d       = $this->sanitizeRecordPost();
        $d['id'] = $id;
        $ok      = $model->update($d);

        if ($ok) {
            $actor = $this->actor();
            $ref   = $d['reference_no'] !== '' ? $d['reference_no'] : "#$id";
            $model->logAudit('record_edited', $actor['id'], $actor['name'], $actor['role'], 'record', $id, "Record edited: $ref");
        }

        echo json_encode(['ok' => $ok, 'message' => $ok ? 'Record updated.' : 'Update failed.']);
        exit;
    }

    // ===== Records AJAX: delete =====

    public function records_delete(): void
    {
        $this->requireAdmin(true);
        $this->requirePostMethod();
        $this->verifyCsrfToken(false);
        header('Content-Type: application/json');

        $model = new RecordModel();
        $id    = (int) ($_POST['id'] ?? 0);

        if ($id < 1) {
            $this->jsonError(400, 'No record id given.');
        }

        $ok = $model->delete($id);

        if ($ok) {
            $actor = $this->actor();
            $model->logAudit('record_deleted', $actor['id'], $actor['name'], $actor['role'], 'record', $id, "Record deleted: #$id");
        }

        echo json_encode(['ok' => $ok, 'message' => $ok ? 'Record deleted.' : 'Delete failed.']);
        exit;
    }

    private function sanitizeRecordPost(): array
    {
        $str = fn(string $k) => trim($_POST[$k] ?? '');
        return [
            'title_of_research'      => $str('title_of_research'),
            'school'                 => $str('school'),
            'animal_type'            => $str('animal_type'),
            'animal_count'           => $str('animal_count'),
            'principal_investigator' => $str('principal_investigator'),
            'gender'                 => $str('gender'),
            'researcher_type'        => $str('researcher_type'),
            'research_adviser'       => $str('research_adviser'),
            'veterinarian'           => $str('veterinarian'),
            'research_duration'      => $str('research_duration'),
            'date_released'          => $str('date_released'),
            'received_by'            => $str('received_by'),
        ];
    }

    // ===== OTHER PAGES =====

    // [MODIFIED by SPM - this method already existed; now also loads
    //  the announcements list and passes the user's role so the view can
    //  decide whether to show Add/Edit/Delete buttons (admin only).
    public function announcements(): void
    {
        $this->requireAdmin();

        require_once dirname(__DIR__) . '/models/AnnouncementModel.php';
        $model = new AnnouncementModel();

        $this->view('admin/announcements', [
            'user'          => $_SESSION['user'],
            'role'          => $_SESSION['user']['role'] ?? '',
            'csrf'          => $this->generateCsrfToken(),
            'announcements' => $model->getAll(),
        ]);
    }
    // END MODIFIED

    public function accounts(): void
    {
        $this->requireAdmin();
        $this->view('admin/accounts', [
            'user'    => $_SESSION['user'],
            'csrf'    => $this->generateCsrfToken(),
            'pending' => $this->model->getPendingUsers(),
        ]);
    }

    // ===== LOG IN =====

    public function login(): void
    {
        if ($this->isLoggedIn()) {
            $role = $_SESSION['user']['role'] ?? '';
            $this->redirect(in_array($role, ['admin', 'reviewer']) ? 'admin/home' : 'submissions');
        }

        $this->view('admin/login', [
            'csrf'  => $this->generateCsrfToken(),
            'error' => $_SESSION['flash_error'] ?? '',
        ]);
        unset($_SESSION['flash_error']);
    }

    public function login_process(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin/login');
        }

        $this->verifyCsrfToken(true);

        $input    = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $MAX_ATTEMPTS = 5;
        $WINDOW       = 900;

        if (
            $this->model->countRecentAttempts($ip, $WINDOW) >= $MAX_ATTEMPTS
            || $this->model->countRecentAttempts($input, $WINDOW) >= $MAX_ATTEMPTS
        ) {
            $_SESSION['flash_error'] = 'Too many failed login attempts. Please wait 15 minutes before trying again.';
            $this->redirect('admin/login');
        }

        if (empty($input) || empty($password)) {
            $_SESSION['flash_error'] = 'Please fill in all fields.';
            $this->redirect('admin/login');
        }

        $user = $this->model->getUserByUsername($input) ?? $this->model->getUserByEmail($input);

        if (!$user) {
            $this->model->recordLoginAttempt($ip);
            $this->model->recordLoginAttempt($input);
            $this->model->logAudit('login_failed', null, $input, '', '', null, 'Failed admin login attempt');
            $_SESSION['flash_error'] = 'No account found with that username or email.';
            $this->redirect('admin/login');
        }

        if (!in_array($user['role'], ['admin', 'reviewer'])) {
            $_SESSION['flash_error'] = 'This portal is for staff only.';
            $this->redirect('admin/login');
        }

        if (($user['status'] ?? 'active') === 'pending') {
            $_SESSION['flash_error'] = 'Your account is pending admin approval.';
            $this->redirect('admin/login');
        }

        if (!password_verify($password, $user['password'])) {
            $this->model->recordLoginAttempt($ip);
            $this->model->recordLoginAttempt($input);
            $this->model->logAudit('login_failed', null, $input, '', '', null, 'Failed admin login attempt');
            $_SESSION['flash_error'] = 'Invalid password. Please try again.';
            $this->redirect('admin/login');
        }

        $this->model->clearLoginAttempts($ip);
        $this->model->clearLoginAttempts($input);

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'user_id'        => $user['id'],
            'first_name'     => $user['first_name'],
            'username'       => $user['username'],
            'role'           => $user['role'],
            'email_verified' => (bool) $user['email_verified'],
        ];

        $this->model->logAudit('login_success', (int) $user['id'], $user['username'], $user['role'], 'user', (int) $user['id'], 'Staff logged in');
        $this->redirect('admin/home');
    }

    // ===== LOG OUT =====

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin/home');
        }

        $actor = $this->actor();
        $this->model->logAudit('logout', $actor['id'], $actor['name'], $actor['role'], 'user', $actor['id'], 'Staff logged out');

        session_destroy();
        session_start();
        $this->redirect('admin/login');
    }

    // ===== REGISTRATION (token-gated) =====

    private function getValidInvite(string $token): array
    {
        $invite = $this->model->getValidInviteToken($token);
        if (!$invite) {
            $this->renderError(403, 'Invalid Invite Link', [
                'This invite link is invalid or has expired.',
                'Please contact an administrator to request a new one.',
            ]);
        }
        return $invite;
    }

    public function register(): void
    {
        $token  = $_GET['token'] ?? '';
        $invite = $this->getValidInvite($token);

        $this->view('admin/register', [
            'csrf'        => $this->generateCsrfToken(),
            'token'       => htmlspecialchars($token),
            'preset_role' => $invite['role'],
            'errors'      => [],
            'old'         => [],
        ]);
    }

    public function register_process(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin/login');
        }

        $this->verifyCsrfToken();

        $token  = $_POST['invite_token'] ?? '';
        $invite = $this->getValidInvite($token);
        $role   = $invite['role'];

        $username     = trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['username'] ?? ''));
        $first_name   = ucfirst(mb_strtolower(trim(preg_replace('/[^\p{L}\p{M}\s\-\']/u', '', $_POST['first_name'] ?? ''))));
        $last_name    = trim(preg_replace('/[^\p{L}\p{M}\s\-\']/u', '', $_POST['last_name'] ?? ''));
        $email        = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password     = $_POST['password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        $old    = compact('username', 'first_name', 'last_name', 'email', 'role');
        $errors = [];

        if (empty($username) || strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = 'Username must be 3-50 characters (letters, numbers, _ or -).';
        }
        if (empty($first_name) || strlen($first_name) < 2) {
            $errors[] = 'First name must be at least 2 characters.';
        }
        if (empty($last_name) || strlen($last_name) < 2) {
            $errors[] = 'Last name must be at least 2 characters.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        $pw_reqs = $this->validatePasswordRequirements($password);
        if (!empty($pw_reqs)) {
            $errors[] = 'Password must contain: ' . implode(', ', $pw_reqs) . '.';
        }
        if ($password !== $confirm_pass) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $existing = $this->model->getUserByEmail($email);
            if ($existing) {
                $errors[] = $existing['role'] === 'researcher'
                    ? 'This email belongs to a researcher account. Please use a different email, or delete your researcher account first.'
                    : 'That email is already taken.';
            }
            if ($this->model->getUserByUsername($username)) {
                $errors[] = 'That username is already taken.';
            }
        }

        if (!empty($errors)) {
            $this->view('admin/register', [
                'csrf'        => $this->generateCsrfToken(),
                'token'       => htmlspecialchars($token),
                'preset_role' => $role,
                'errors'      => $errors,
                'old'         => $old,
            ]);
            return;
        }

        $ok = $this->model->insertUser($username, $first_name, $last_name, $email, password_hash($password, PASSWORD_DEFAULT), $role, 'pending');

        if ($ok) {
            $newUserId = $this->model->connection->insert_id;
            $this->model->consumeInviteToken($token, $newUserId);
            $this->sendEmailVerification(['id' => $newUserId, 'first_name' => $first_name, 'email' => $email]);
            Mailer::sendTemplate('application_received', ['first_name' => $first_name, 'role' => $role], $email, $first_name, 'BSU-IACUC: Application Received');
            $this->view('admin/register', [
                'csrf'    => $this->generateCsrfToken(),
                'token'   => htmlspecialchars($token),
                'errors'  => [],
                'old'     => [],
                'success' => true,
            ]);
        } else {
            $this->view('admin/register', [
                'csrf'        => $this->generateCsrfToken(),
                'token'       => htmlspecialchars($token),
                'preset_role' => $role,
                'errors'      => ['Registration failed. Please try again.'],
                'old'         => $old,
            ]);
        }
    }

    public function generate_invite(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin/accounts');
        }

        $this->verifyCsrfToken(true);

        $role = $_POST['invite_role'] ?? 'admin';
        if (!in_array($role, ['admin', 'reviewer'])) {
            $role = 'admin';
        }

        $token = $this->model->createInviteToken($role, 48);

        $this->view('admin/accounts', [
            'user'        => $_SESSION['user'],
            'csrf'        => $this->generateCsrfToken(),
            'invite_url'  => ROOT . '/admin/register?token=' . $token,
            'invite_role' => $role,
        ]);
    }

    // ===== APPROVE / REJECT =====

    public function approve(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin/accounts');
        }

        $this->verifyCsrfToken(true);

        $id = (int) ($_POST['user_id'] ?? 0);
        if ($id > 0) {
            $applicant = $this->model->getUser($id);
            $actor     = $this->actor();

            $this->model->logAudit('user_approved', $actor['id'], $actor['name'], $actor['role'], 'user', $id, 'Approved staff account: ' . ($applicant['username'] ?? $id));
            $this->model->approveUser($id);

            if ($applicant) {
                Notifier::send(
                    $id,
                    'account_verified',
                    'Account Verified',
                    'Your account has been verified. You can now log in.',
                    'users/login',
                    [
                        'template' => 'application_approved',
                        'vars'     => ['first_name' => $applicant['first_name']],
                        'to'       => $applicant['email'],
                        'name'     => $applicant['first_name'],
                        'subject'  => 'BSU-IACUC: Application Approved',
                    ]
                );
            }
        }

        $_SESSION['flash_success'] = 'Account approved.';
        $this->redirect('admin/accounts');
    }

    public function reject(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin/accounts');
        }

        $this->verifyCsrfToken(true);

        $id = (int) ($_POST['user_id'] ?? 0);
        if ($id > 0) {
            $applicant = $this->model->getUser($id);
            $actor     = $this->actor();

            $this->model->logAudit('user_rejected', $actor['id'], $actor['name'], $actor['role'], 'user', $id, 'Rejected staff account: ' . ($applicant['username'] ?? $id));
            $this->model->rejectUser($id);

            if ($applicant) {
                Mailer::sendTemplate('application_rejected', ['first_name' => $applicant['first_name']], $applicant['email'], $applicant['first_name'], 'BSU-IACUC: Application Update');
            }
        }

        $_SESSION['flash_success'] = 'Account rejected and removed.';
        $this->redirect('admin/accounts');
    }

    // ===== FORGOT / RESET PASSWORD =====

    public function forgot_password(): void
    {
        $this->handleForgotPassword('admin/forgot_password', 'admin/reset_password', 'admin/login');
    }

    public function reset_password(): void
    {
        $this->handleResetPassword('admin/reset_password', 'admin/login');
    }

    // ===== ANNOUNCEMENTS ("From Our Office" section) — add/edit/delete =====
    // ADDED by SPM - the view-only `announcements()`
    //  Does NOT touch the "From Our Partner Pages" Facebook section, which stays auto-updating.]

    private function announcementImageDir(): string
    {
        return dirname(__DIR__, 2) . '/portal/assets/uploads/announcements/';
    }

    // Validates and saves an uploaded image 
    private function saveAnnouncementImage(string $inputName, ?string &$reason = null): string|false|null
    {
        if (empty($_FILES[$inputName]['tmp_name']) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $_FILES[$inputName];

        if ($file['size'] > 5 * 1024 * 1024) {
            $reason = 'That image is larger than the 5 MB limit.';
            return false;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $allowedExts, true)) {
            $reason = 'Please upload a JPG, PNG, WEBP, or GIF image.';
            return false;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $mimeMap = [
            'jpg'  => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png'  => ['image/png', 'image/x-png'],
            'webp' => ['image/webp'],
            'gif'  => ['image/gif'],
        ];
        if (!in_array($mime, $mimeMap[$ext], true)) {
            $reason = 'That file doesn\'t look like a real image (its content doesn\'t match a "' . $ext . '" file). Please re-save or export it and try again.';
            return false;
        }

        $dir = $this->announcementImageDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $safeName = bin2hex(random_bytes(8)) . '.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], $dir . $safeName)) {
            $reason = 'Could not save the uploaded image. Please try again.';
            return false;
        }

        return $safeName;
    }

    private function deleteAnnouncementImage(?string $filename): void
    {
        if (!$filename) return;
        $path = $this->announcementImageDir() . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function announcements_add(): void
    {
        $this->requireAdmin(true);
        $this->requirePostMethod();
        $this->verifyCsrfToken(false);
        header('Content-Type: application/json');

        require_once dirname(__DIR__) . '/models/AnnouncementModel.php';
        $model = new AnnouncementModel();

        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body'] ?? '');

        if ($title === '') {
            $this->jsonError(422, 'Title is required.');
        }
        if ($body === '') {
            $this->jsonError(422, 'Announcement content is required.');
        }

        // [ADDED by SPM - optional image upload]
        $imageReason = null;
        $imageName   = $this->saveAnnouncementImage('image', $imageReason);
        if ($imageName === false) {
            $this->jsonError(422, $imageReason ?: 'Image upload failed.');
        }
        // END ADDED

        $actor = $this->actor();
        $ok    = $model->insert($title, $body, $imageName, $actor['id'] ?: null);

        if ($ok) {
            $model->logAudit('announcement_added', $actor['id'], $actor['name'], $actor['role'], 'announcement', null, "Announcement added: $title");
        } elseif ($imageName) {
            // insert failed after the image was already written to disk - clean it up
            $this->deleteAnnouncementImage($imageName);
        }

        echo json_encode(['ok' => $ok, 'message' => $ok ? 'Announcement added.' : 'Insert failed.']);
        exit;
    }

    public function announcements_get(): void
    {
        $this->requireAdmin(true);
        header('Content-Type: application/json');

        require_once dirname(__DIR__) . '/models/AnnouncementModel.php';
        $model = new AnnouncementModel();

        $id  = (int) ($_GET['id'] ?? 0);
        $row = $id > 0 ? $model->getById($id) : null;

        echo json_encode(
            $row
                ? ['ok' => true, 'data' => $row]
                : ['ok' => false, 'message' => 'Announcement not found.']
        );
        exit;
    }

    public function announcements_edit(): void
    {
        $this->requireAdmin(true);
        $this->requirePostMethod();
        $this->verifyCsrfToken(false);
        header('Content-Type: application/json');

        require_once dirname(__DIR__) . '/models/AnnouncementModel.php';
        $model = new AnnouncementModel();

        $id    = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body'] ?? '');

        $existing = $id > 0 ? $model->getById($id) : null;
        if (!$existing) {
            $this->jsonError(404, 'Announcement not found.');
        }
        if ($title === '') {
            $this->jsonError(422, 'Title is required.');
        }
        if ($body === '') {
            $this->jsonError(422, 'Announcement content is required.');
        }

        // [ADDED by SPM - optional image replace/remove on edit.
        $imageReason = null;
        $newImage    = $this->saveAnnouncementImage('image', $imageReason);
        if ($newImage === false) {
            $this->jsonError(422, $imageReason ?: 'Image upload failed.');
        }

        $removeImage = ($_POST['remove_image'] ?? '') === '1';

        if ($newImage !== null) {
            $imageToSave = $newImage;
            $this->deleteAnnouncementImage($existing['image_path']);
        } elseif ($removeImage) {
            $imageToSave = null;
            $this->deleteAnnouncementImage($existing['image_path']);
        } else {
            $imageToSave = 'KEEP';
        }
        // END ADDED

        $ok = $model->update($id, $title, $body, $imageToSave);

        if ($ok) {
            $actor = $this->actor();
            $model->logAudit('announcement_edited', $actor['id'], $actor['name'], $actor['role'], 'announcement', $id, "Announcement edited: $title");
        }

        echo json_encode(['ok' => $ok, 'message' => $ok ? 'Announcement updated.' : 'Update failed.']);
        exit;
    }

    public function announcements_delete(): void
    {
        $this->requireAdmin(true);
        $this->requirePostMethod();
        $this->verifyCsrfToken(false);
        header('Content-Type: application/json');

        require_once dirname(__DIR__) . '/models/AnnouncementModel.php';
        $model = new AnnouncementModel();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            $this->jsonError(400, 'No announcement id given.');
        }

        // ADDED by SPM- clean up the image file (if any) along with the row
        $existing = $model->getById($id);
        // END ADDED

        $ok = $model->delete($id);

        if ($ok) {
            $actor = $this->actor();
            $model->logAudit('announcement_deleted', $actor['id'], $actor['name'], $actor['role'], 'announcement', $id, "Announcement deleted: #$id");
            if ($existing) {
                $this->deleteAnnouncementImage($existing['image_path']);
            }
        }

        echo json_encode(['ok' => $ok, 'message' => $ok ? 'Announcement deleted.' : 'Delete failed.']);
        exit;
    }
    // END ADDED
}