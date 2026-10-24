<?php

class Submissions extends Controller
{
    private ProtocolModel $protocolModel;

    // ===== SETUP =====
    public function __construct()
    {
        parent::__construct();

        $this->protocolModel = new ProtocolModel();
    }

    // ===== RESEARCHER ACCESS CHECK =====
    private function requireResearcher(bool $ajax = false): void
    {
        $this->requireLogin();

        if ($this->isPersonnel()) {
            $ajax
                ? $this->jsonError(403, 'This page is for researchers only.')
                : $this->redirect('personnel/home');
        }
    }

    // ===== MY PROTOCOLS PAGE =====
    public function index(): void
    {
        $this->requireResearcher();

        $userId    = (int) $_SESSION['user']['user_id'];
        $protocols = $this->protocolModel->getByUser($userId);

        $cert          = $this->userModel->getCert($userId);
        $currentUser   = $this->userModel->getUser($userId);
        $mustPayInPerson = UserModel::mustPayInPerson($currentUser['school'] ?? '');

        $statuses = [
            'Under Review',
            'Needs Revision',
            'Reviewed',
            'Endorsed',
            'Approved',
        ];

        $activityTimestamps = array_column($protocols, 'last_activity_at');
        $updatesBaseline    = $activityTimestamps ? max($activityTimestamps) : null;

        $this->view('submissions', [
            'protocols'       => $protocols,
            'actor'           => $this->actor(),
            'statuses'        => $statuses,
            'cert'            => $cert,
            'mustPayInPerson' => $mustPayInPerson,
            'csrf'            => $this->generateCsrfToken(),
            'updatesEndpoint' => 'submissions/checkupdates',
            'updatesBaseline' => $updatesBaseline,
        ]);
    }

    // ===== LIVE UPDATE CHECK =====
    public function checkupdates(): void
    {
        $this->requireResearcher(true);
        header('Content-Type: application/json');

        $userId = (int) $_SESSION['user']['user_id'];
        echo json_encode(['latest' => $this->protocolModel->getLatestActivityTimestamp($userId)]);
        exit;
    }
}
