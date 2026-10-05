<?php

class Announcements extends Controller
{
  // ===== ANNOUNCEMENTS PAGE =====
  public function index()
  {
    if ($this->isPersonnel()) {
      $this->redirect('personnel/home');
    }

    require_once dirname(__DIR__) . '/models/AnnouncementModel.php';
    $announcementModel = new AnnouncementModel();

    $this->view('announcements', [
      'officeAnnouncements' => $announcementModel->getAll(),
    ]);
  }
}
