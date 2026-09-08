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

    public function index(): void
    {
        $this->requireLogin();

        $userId    = (int) $_SESSION['user']['user_id'];
        $protocols = $this->model->getByUser($userId);

        $userModel     = new UserModel();
        $hasCertOnFile = $userModel->hasCert($userId);

        $statuses = [
            'Under Review',
            'Needs Revision',
            'Reviewed',
            'Endorsed',
            'Approved',
        ];

        $this->view('submissions', [
            'protocols'     => $protocols,
            'statuses'      => $statuses,
            'hasCertOnFile' => $hasCertOnFile,
            'csrf'          => $this->generateCsrfToken(),
        ]);
    }
}
