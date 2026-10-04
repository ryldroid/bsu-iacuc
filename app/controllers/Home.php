<?php

class Home extends Controller
{
  // ===== HOME PAGE =====
  public function index()
  {
    if ($this->isPersonnel()) {
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
