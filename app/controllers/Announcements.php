<?php

class Announcements extends Controller
{
  use PublicPageData;

  // ===== ANNOUNCEMENTS PAGE =====
  public function index()
  {
    if ($this->isPersonnel()) {
      $this->redirect('personnel/home');
    }

    $this->view('announcements', $this->announcementsPageData());
  }
}
