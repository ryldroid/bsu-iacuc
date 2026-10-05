<?php

class Preview extends Controller
{
  // ===== PERSONNEL ONLY =====
  public function __construct()
  {
    if (!$this->isPersonnel()) {
      $this->redirect('home');
    }
  }

  // ===== PREVIEW HOME =====
  public function index()
  {
    $this->home();
  }

  public function home()
  {
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
      'user'                => null,
      'previewMode'         => true,
    ]);
  }

  // ===== PREVIEW ANNOUNCEMENTS =====
  public function announcements()
  {
    require_once dirname(__DIR__) . '/models/AnnouncementModel.php';
    $announcementModel = new AnnouncementModel();

    $this->view('announcements', [
      'officeAnnouncements' => $announcementModel->getAll(),
      'user'                => null,
      'previewMode'         => true,
    ]);
  }

  // ===== PREVIEW CONTACT =====
  public function contact()
  {
    require_once dirname(__DIR__) . '/models/ContactOfficeModel.php';
    $officeModel = new ContactOfficeModel();

    $this->view('contact', [
      'offices'     => $officeModel->getAll(),
      'user'        => null,
      'previewMode' => true,
    ]);
  }
}
