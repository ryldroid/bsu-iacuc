<?php

class Home extends Controller
{
  public function index()
  {
    $role = $_SESSION['user']['role'] ?? null;
    if (in_array($role, ['staff', 'reviewer'])) {
      $this->redirect('personnel/home');
    }

    require_once dirname(__DIR__) . '/models/SiteSettingModel.php';
    $settingsModel = new SiteSettingModel();

    $this->view('home', [
      'siteSettings' => $settingsModel->getAll(),
    ]);
  }
}
