<?php

class Submissions extends Controller
{
    public ProtocolModel $model;

    public function __construct()
    {
        require_once "../app/models/ProtocolModel.php";
        require_once "../app/models/UserModel.php";
        $this->model = new ProtocolModel();
    }

    private function requireResearcher(bool $ajax = false): void
    {
        $this->requireLogin();

        if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'reviewer'], true)) {
            $ajax
                ? $this->jsonError(403, 'This page is for researchers only.')
                : $this->redirect('admin/home');
        }
    }

    public function index(): void
    {
        $this->requireResearcher();

        $userId    = (int) $_SESSION['user']['user_id'];
        $protocols = $this->model->getByUser($userId);

        $userModel     = new UserModel();
        $hasCertOnFile = $userModel->hasCert($userId);
        $currentUser   = $userModel->getUser($userId);
        $isBsu         = stripos(trim($currentUser['school'] ?? ''), 'Benguet State University') !== false;

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
            'statuses'        => $statuses,
            'hasCertOnFile'   => $hasCertOnFile,
            'isBsu'           => $isBsu,
            'csrf'            => $this->generateCsrfToken(),
            'updatesEndpoint' => 'submissions/checkupdates',
            'updatesBaseline' => $updatesBaseline,
        ]);
    }

    public function checkupdates(): void
    {
        $this->requireResearcher(true);
        header('Content-Type: application/json');

        $userId = (int) $_SESSION['user']['user_id'];
        echo json_encode(['latest' => $this->model->getLatestActivityTimestamp($userId)]);
        exit;
    }
}
