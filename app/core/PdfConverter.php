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
    if (!CLOUDCONVERT_API_KEY) {
      $reason = self::UNAVAILABLE;
      $detail = 'CLOUDCONVERT_API_KEY is not configured';
      return false;
    }

    $ext     = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $payload = json_encode([
      'tasks' => [
        'import'  => ['operation' => 'import/base64', 'file' => base64_encode((string) file_get_contents($sourcePath)), 'filename' => 'protocol.' . $ext],
        'convert' => ['operation' => 'convert', 'input' => 'import', 'input_format' => $ext, 'output_format' => 'pdf'],
        'export'  => ['operation' => 'export/url', 'input' => 'convert'],
      ],
    ]);

    $response = self::call(self::JOB_URL, $payload, 60, $status, $error);
    $job      = json_decode((string) $response, true);

    if ($response === false || $status < 200 || $status >= 300 || !is_array($job)) {
      $reason = self::UNAVAILABLE;
      $detail = $error !== '' ? $error : 'CloudConvert returned HTTP ' . $status;
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

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);
    $result = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);

    return $result;
  }
}
