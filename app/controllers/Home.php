<?php

class Home extends Controller
{
  public function index()
  {
    $role = $_SESSION['user']['role'] ?? null;
    if (in_array($role, ['staff', 'reviewer'])) {
      $this->redirect('personnel/home');
    }

    $this->view('home');
  }
}
