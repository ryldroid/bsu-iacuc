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

    // Only plain class-name characters; never build a file path from anything else
    if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $controllerName)) {
      $this->show404();
      return;
    }

    $filename = "../app/controllers/{$controllerName}.php";

    if (file_exists($filename)) {
      require_once $filename;
      $this->controller = $controllerName;
    } else {
      $this->show404();
      return;
    }

    if (!empty($URL[1])) {
      if ($this->isRoutable($this->controller, $URL[1])) {
        $this->method = $URL[1];
      } else {
        $this->show404();
        return;
      }
    }

    $controller = new $this->controller();

    // ===== ACCESS POLICY (default deny, see core/Access.php) =====
    Access::enforce($this->controller, $this->method);
    Idempotency::begin();

    $params = array_slice($URL, 2);
    call_user_func_array([$controller, $this->method], $params);
  }

  // A URL may only reach a public method declared on the controller itself.
  // Inherited helpers (view, redirect, ...), constructors and magic methods are never routable.
  private function isRoutable(string $class, string $method): bool
  {
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $method) || !method_exists($class, $method)) {
      return false;
    }

    $reflection = new ReflectionMethod($class, $method);

    return $reflection->isPublic()
      && !$reflection->isStatic()
      && !$reflection->isConstructor()
      && $reflection->getDeclaringClass()->getName() === $class;
  }

  // ===== 404 PAGE =====
  private function show404(): void
  {
    ErrorPage::render(404, 'Page Not Found', [
      'Page could not be found. It may have been removed, had its name changed, or is temporarily unavailable.',
    ]);
  }
}
