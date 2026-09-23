<?php

class Contact extends Controller
{
  public function index()
  {
    require_once dirname(__DIR__) . '/models/ContactOfficeModel.php';
    $officeModel = new ContactOfficeModel();

    $this->view('contact', [
      'offices' => $officeModel->getAll(),
    ]);
  }
}
