<?php

// ===== Session lockout test (run: php tests/session-lockout.php) =====

if (($argv[1] ?? '') === 'child') {
  $status = $argv[2];

  class UserModel
  {
    public function getUser($id)
    {
      $status = $GLOBALS['status'];
      return $status === 'missing' ? null : ['id' => $id, 'status' => $status];
    }
  }

  require __DIR__ . '/../app/core/Access.php';

  $_SESSION['user'] = ['user_id' => 7, 'role' => 'staff'];
  Access::enforce('Personnel', 'accounts');

  echo 'ALLOWED';
  exit;
}

$cases = [
  'active'      => true,
  'deactivated' => true,
  'deleted'     => false,
  'missing'     => false,
];

$failures = 0;

foreach ($cases as $status => $shouldBeAllowed) {
  $output  = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' child ' . $status . ' 2>&1');
  $allowed = str_contains($output, 'ALLOWED');
  $passed  = $allowed === $shouldBeAllowed;

  echo ($passed ? 'PASS' : 'FAIL') . '  ' . $status . ' staff session ' . ($shouldBeAllowed ? 'stays signed in' : 'is locked out') . PHP_EOL;

  if (!$passed) {
    $failures++;
  }
}

exit($failures === 0 ? 0 : 1);
