<?php

class Announcements extends Controller
{
  public function index()
  {
    require_once dirname(__DIR__) . '/models/AnnouncementModel.php';
    $announcementModel = new AnnouncementModel();

    $this->view('announcements', [
      'officeAnnouncements' => $announcementModel->getAll(),
    ]);
  }
}
