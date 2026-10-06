<?php

class Contact extends Controller
{
  use PublicPageData;

  // ===== CONTACT PAGE =====
  public function index()
  {
    $this->view('contact', $this->contactPageData());
  }
}
