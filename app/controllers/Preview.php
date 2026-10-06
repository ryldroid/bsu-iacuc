<?php

class Preview extends Controller
{
  use PublicPageData;

  // ===== PERSONNEL ONLY =====
  public function __construct()
  {
    if (!$this->isPersonnel()) {
      $this->redirect('home');
    }

    parent::__construct();
  }

  // ===== PREVIEW RENDER =====
  private function previewView(string $name, array $data): void
  {
    $this->view($name, $data + ['user' => null, 'previewMode' => true]);
  }

  // ===== PREVIEW HOME =====
  public function index()
  {
    $this->home();
  }

  public function home()
  {
    $this->previewView('home', $this->homePageData());
  }

  // ===== PREVIEW ANNOUNCEMENTS =====
  public function announcements()
  {
    $this->previewView('announcements', $this->announcementsPageData());
  }

  // ===== PREVIEW CONTACT =====
  public function contact()
  {
    $this->previewView('contact', $this->contactPageData());
  }
}
