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

    require_once dirname(__DIR__) . '/models/FaqModel.php';
    $faqModel = new FaqModel();

    require_once dirname(__DIR__) . '/models/AnnouncementModel.php';
    $announcementModel = new AnnouncementModel();

    $this->view('home', [
      'siteSettings'        => $settingsModel->getAll(),
      'faqs'                => $faqModel->getAll(),
      'latestAnnouncements' => array_slice($announcementModel->getAll(), 0, 3),
    ]);
  }
}
