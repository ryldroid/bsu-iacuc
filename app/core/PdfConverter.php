<?php

class PdfConverter
{
  public const HOURLY_LIMIT  = 5;
  public const LIMIT_REACHED = 'You have reached the hourly limit for file conversions. Please try again later, or export your file to PDF and upload that instead.';

  private const JOB_URL     = 'https://sync.api.cloudconvert.com/v2/jobs';
  private const MAX_BYTES   = 10 * 1024 * 1024;
  private const UNAVAILABLE = 'Conversion is unavailable right now. Please export your file to PDF and upload that instead.';
  private const UNREADABLE  = 'We couldn\'t convert that file. Please make sure it opens correctly in Word, or export it to PDF and upload that instead.';
  private const TOO_LARGE   = 'The converted PDF is larger than the 10 MB limit. Please reduce the file size (for example, compress images) and try again.';

  // ===== CONVERT WORD FILE TO PDF =====
  public static function convert(string $sourcePath, string $destPath, ?string &$reason = null, ?string &$detail = null): bool
  {
    // Local LibreOffice first (free, no credits). Falls back to CloudConvert if unavailable/failed.
    $local = self::convertLocally($sourcePath, $destPath, $reason, $detail);
    if ($local !== null) {
      return $local;
    }
    $localNote = $detail;

    if (!CLOUDCONVERT_API_KEY) {
      $reason = self::UNAVAILABLE;
      $detail = 'CLOUDCONVERT_API_KEY is not configured' . ($localNote ? ' | local: ' . $localNote : '');
      error_log('[PdfConverter] ' . $detail);
      return false;
    }

    @set_time_limit(120);

    $ext     = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $payload = json_encode([
      'tasks' => [
        'import'  => ['operation' => 'import/base64', 'file' => base64_encode((string) file_get_contents($sourcePath)), 'filename' => 'protocol.' . $ext],
        'convert' => ['operation' => 'convert', 'input' => 'import', 'input_format' => $ext, 'output_format' => 'pdf'],
        'export'  => ['operation' => 'export/url', 'input' => 'convert'],
      ],
    ]);

    $status   = 0;
    $response = self::call(self::JOB_URL, $payload, 60, $status, $error);
    $job      = json_decode((string) $response, true);

    if ($response === false || $status < 200 || $status >= 300 || !is_array($job)) {
      $reason = self::UNAVAILABLE;
      $detail = $error !== '' ? $error : self::describeHttpFailure($status, $job);
      if ($localNote) {
        $detail .= ' | local: ' . $localNote;
      }
      error_log('[PdfConverter] ' . $detail);
      return false;
    }

    $tasks = $job['data']['tasks'] ?? [];

    if (($job['data']['status'] ?? '') !== 'finished') {
      $reason = self::UNAVAILABLE;
      $detail = 'The conversion job did not finish';
      foreach ($tasks as $task) {
        if (($task['status'] ?? '') === 'error') {
          $reason = ($task['operation'] ?? '') === 'convert' ? self::UNREADABLE : self::UNAVAILABLE;
          $detail = ($task['operation'] ?? 'task') . ': ' . ($task['message'] ?? 'unknown error');
          break;
        }
      }
      error_log('[PdfConverter] ' . $detail);
      return false;
    }

    $downloadUrl = null;
    foreach ($tasks as $task) {
      if (($task['operation'] ?? '') === 'export/url' && !empty($task['result']['files'][0]['url'])) {
        $downloadUrl = $task['result']['files'][0]['url'];
      }
    }

    if (!$downloadUrl) {
      $reason = self::UNAVAILABLE;
      $detail = 'The conversion result had no download link';
      return false;
    }

    $sink = fopen($destPath, 'wb');
    if (!$sink) {
      $reason = self::UNAVAILABLE;
      $detail = 'Could not write the converted file';
      return false;
    }

    $downloaded = self::call($downloadUrl, null, 30, $status, $error, $sink);
    fclose($sink);

    if ($downloaded === false || $status !== 200) {
      @unlink($destPath);
      $reason = self::UNAVAILABLE;
      $detail = $error !== '' ? $error : 'Download returned HTTP ' . $status;
      error_log('[PdfConverter] ' . $detail);
      return false;
    }

    $size = (int) @filesize($destPath);
    $head = (string) @file_get_contents($destPath, false, null, 0, 5);

    if ($size < 1 || $head !== '%PDF-') {
      @unlink($destPath);
      $reason = self::UNREADABLE;
      $detail = 'The converted file is not a valid PDF';
      return false;
    }

    if ($size > self::MAX_BYTES) {
      @unlink($destPath);
      $reason = self::TOO_LARGE;
      $detail = 'The converted PDF is ' . $size . ' bytes';
      return false;
    }

    return true;
  }

  // ===== CONVERT LOCALLY WITH LIBREOFFICE (true = done, false = fatal for this file, null = not available, try CloudConvert) =====
  private static function convertLocally(string $sourcePath, string $destPath, ?string &$reason, ?string &$detail): ?bool
  {
    $detail = null;

    if (!function_exists('proc_open')) {
      $detail = 'proc_open is disabled in php.ini';
      return null;
    }

    $soffice = self::findLibreOffice();
    if ($soffice === null) {
      $detail = 'LibreOffice not found (install it or set LIBREOFFICE_PATH in .env)';
      return null;
    }

    @set_time_limit(180);

    $work    = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/iacuc_lo_' . bin2hex(random_bytes(6));
    $outDir  = $work . '/out';
    $profile = $work . '/profile';

    if (!@mkdir($outDir, 0777, true) || !@mkdir($profile, 0777, true)) {
      $detail = 'Could not create a temp folder for conversion';
      return null;
    }

    $profileUrl = 'file:///' . ltrim(str_replace('\\', '/', $profile), '/');

    $cmd = [
      $soffice,
      '--headless',
      '--norestore',
      '--nolockcheck',
      '--nodefault',
      '--nologo',
      '-env:UserInstallation=' . $profileUrl,
      '--convert-to',
      'pdf:writer_pdf_Export',
      '--outdir',
      $outDir,
      $sourcePath,
    ];

    $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($proc)) {
      self::removeDir($work);
      $detail = 'Could not start LibreOffice';
      return null;
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $output   = '';
    $deadline = time() + 120;
    $timedOut = false;

    while (true) {
      $output .= (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
      $state = proc_get_status($proc);
      if (!$state['running']) {
        break;
      }
      if (time() > $deadline) {
        $timedOut = true;
        proc_terminate($proc);
        break;
      }
      usleep(200000);
    }

    $output .= (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);

    $produced = glob($outDir . '/*.pdf') ?: [];
    $pdf      = $produced[0] ?? null;

    if ($timedOut || $pdf === null || !is_file($pdf)) {
      self::removeDir($work);
      $detail = $timedOut ? 'LibreOffice timed out' : 'LibreOffice produced no PDF: ' . trim(mb_substr($output, 0, 200));
      error_log('[PdfConverter] ' . $detail);
      return null;
    }

    $size = (int) @filesize($pdf);
    $head = (string) @file_get_contents($pdf, false, null, 0, 5);

    if ($size < 1 || $head !== '%PDF-') {
      self::removeDir($work);
      $detail = 'LibreOffice output is not a valid PDF';
      error_log('[PdfConverter] ' . $detail);
      return null;
    }

    if ($size > self::MAX_BYTES) {
      self::removeDir($work);
      $reason = self::TOO_LARGE;
      $detail = 'The converted PDF is ' . $size . ' bytes';
      return false;
    }

    $moved = @rename($pdf, $destPath) || (@copy($pdf, $destPath) && @unlink($pdf));
    self::removeDir($work);

    if (!$moved) {
      $detail = 'Could not save the converted PDF';
      error_log('[PdfConverter] ' . $detail);
      return null;
    }

    return true;
  }

  // ===== LOCATE LIBREOFFICE =====
  private static function findLibreOffice(): ?string
  {
    $override = EnvLoader::get('LIBREOFFICE_PATH');
    if ($override && is_file($override)) {
      return $override;
    }

    if (PHP_OS_FAMILY === 'Windows') {
      $candidates = [
        'C:/Program Files/LibreOffice/program/soffice.exe',
        'C:/Program Files (x86)/LibreOffice/program/soffice.exe',
      ];
    } else {
      $candidates = [
        '/usr/bin/soffice',
        '/usr/local/bin/soffice',
        '/usr/bin/libreoffice',
        '/opt/libreoffice/program/soffice',
        '/Applications/LibreOffice.app/Contents/MacOS/soffice',
      ];
    }

    foreach ($candidates as $candidate) {
      if (is_file($candidate)) {
        return $candidate;
      }
    }

    return null;
  }

  // ===== DELETE A TEMP FOLDER =====
  private static function removeDir(string $dir): void
  {
    if (!is_dir($dir)) {
      return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
      $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
  }

  // ===== EXPLAIN A FAILED CLOUDCONVERT RESPONSE =====
  private static function describeHttpFailure(int $status, $job): string
  {
    $message = is_array($job) ? (string) ($job['message'] ?? '') : '';

    $hint = match (true) {
      $status === 0                    => 'No response (could not reach CloudConvert)',
      $status === 401, $status === 403 => 'API key was rejected or lacks the task.read/task.write scopes',
      $status === 402                  => 'CloudConvert account is out of conversion credits',
      $status === 422                  => 'CloudConvert rejected the job',
      $status === 429                  => 'CloudConvert rate limit reached',
      default                          => 'CloudConvert returned an error',
    };

    return 'HTTP ' . $status . ': ' . $hint . ($message !== '' ? ' (' . $message . ')' : '');
  }

  // ===== CA BUNDLE (fixes cURL error 60 on Windows/XAMPP/WAMP) =====
  public static function caBundle(): ?string
  {
    $override = EnvLoader::get('CLOUDCONVERT_CA_BUNDLE');
    if ($override && is_file($override)) {
      return $override;
    }

    foreach ([ini_get('curl.cainfo'), ini_get('openssl.cafile')] as $configured) {
      if ($configured && is_file($configured)) {
        return null;
      }
    }

    $phpDir = dirname(PHP_BINARY);

    foreach (
      [
        $phpDir . '/extras/ssl/cacert.pem',
        $phpDir . '/cacert.pem',
        dirname($phpDir) . '/apache/bin/curl-ca-bundle.crt',
        'C:/xampp/php/extras/ssl/cacert.pem',
        'C:/xampp/apache/bin/curl-ca-bundle.crt',
        'C:/wamp64/bin/php/cacert.pem',
        'C:/laragon/etc/ssl/cacert.pem',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
        dirname(__DIR__, 2) . '/storage/cacert.pem',
      ] as $candidate
    ) {
      if (is_file($candidate)) {
        return $candidate;
      }
    }

    return null;
  }

  // ===== HTTP CALL =====
  private static function call(string $url, ?string $body, int $timeout, ?int &$status, ?string &$error, $sink = null): string|bool
  {
    $options = [
      CURLOPT_RETURNTRANSFER => $sink === null,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT        => $timeout,
    ];

    if ($body !== null) {
      $options[CURLOPT_POST]       = true;
      $options[CURLOPT_POSTFIELDS] = $body;
      $options[CURLOPT_HTTPHEADER] = [
        'Authorization: Bearer ' . CLOUDCONVERT_API_KEY,
        'Content-Type: application/json',
      ];
    }

    if ($sink !== null) {
      $options[CURLOPT_FILE] = $sink;
    }

    $ca = self::caBundle();
    if ($ca !== null) {
      $options[CURLOPT_CAINFO] = $ca;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);
    $result = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno  = curl_errno($ch);
    $error  = $errno ? 'cURL error ' . $errno . ': ' . curl_error($ch) : '';
    curl_close($ch);

    if ($errno === 60 || $errno === 77) {
      $error .= ' (PHP cannot verify HTTPS certificates. Set curl.cainfo in php.ini or CLOUDCONVERT_CA_BUNDLE in .env to a cacert.pem file.)';
    }

    if ($error !== '') {
      error_log('[PdfConverter] ' . $error);
    }

    return $result;
  }
}
