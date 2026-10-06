<?php

class Home extends Controller
{
  use PublicPageData;

  // ===== HOME PAGE =====
  public function index()
  {
    if ($this->isPersonnel()) {
      $this->redirect('personnel/home');
    }

    $this->view('home', $this->homePageData());
  }
}
