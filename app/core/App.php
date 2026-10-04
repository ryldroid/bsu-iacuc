<?php

class App
{
  private $controller = 'Home';
  private $method = 'index';

  // ===== ROUTER (LOAD CONTROLLER) =====
  public function loadController()
  {
    $URL = explode('/', $_GET['url'] ?? 'home');

    $controllerName = ucfirst($URL[0]);
    $filename = "../app/controllers/{$controllerName}.php";

    if (file_exists($filename)) {
      require_once $filename;
      $this->controller = $controllerName;
    } else {
      $this->show404();
      return;
    }

    $controller = new $this->controller();

    if (!empty($URL[1])) {
      if (method_exists($controller, $URL[1])) {
        $this->method = $URL[1];
      } else {
        $this->show404();
        return;
      }
    }

    $params = array_slice($URL, 2);
    call_user_func_array([$controller, $this->method], $params);
  }

  // ===== 404 PAGE =====
  private function show404(): void
  {
    ErrorPage::render(404, 'Page Not Found', [
      'Page could not be found. It may have been removed, had its name changed, or is temporarily unavailable.',
    ]);
  }
}
