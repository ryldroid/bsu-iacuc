<?php

// ===== EMOJI RANGES =====
function emoji_class_body(string $escape): string
{
  $ranges = [[0x1F000, 0x1FAFF], [0x2600, 0x27BF], [0x2B50], [0x2B55], [0xFE0F], [0x20E3]];

  return implode('', array_map(
    fn(array $range): string => sprintf('%s{%X}', $escape, $range[0]) . (isset($range[1]) ? sprintf('-%s{%X}', $escape, $range[1]) : ''),
    $ranges
  ));
}

// ===== NORMALIZE PASTED TEXT =====
function normalize_pasted_text(?string $text): string
{
  if ($text === null || $text === '') {
    return '';
  }

  // ===== SPECIAL-CASE LETTERS =====
  static $legacy = [
    0x210E => 'h',
    0x212C => 'B',
    0x2130 => 'E',
    0x2131 => 'F',
    0x210B => 'H',
    0x2110 => 'I',
    0x2112 => 'L',
    0x2133 => 'M',
    0x211B => 'R',
    0x212F => 'e',
    0x210A => 'g',
    0x2134 => 'o',
    0x212D => 'C',
    0x210C => 'H',
    0x2111 => 'I',
    0x211C => 'R',
    0x2128 => 'Z',
    0x2102 => 'C',
    0x210D => 'H',
    0x2115 => 'N',
    0x2119 => 'P',
    0x211A => 'Q',
    0x211D => 'R',
    0x2124 => 'Z',
  ];

  // ===== STYLED LETTER BLOCKS =====
  static $letterBlocks = [
    0x1D400,
    0x1D434,
    0x1D468,
    0x1D49C,
    0x1D4D0,
    0x1D504,
    0x1D538,
    0x1D56C,
    0x1D5A0,
    0x1D5D4,
    0x1D608,
    0x1D63C,
    0x1D670,
  ];

  // ===== STYLED DIGIT BLOCKS =====
  static $digitBlocks = [0x1D7CE, 0x1D7D8, 0x1D7E2, 0x1D7EC, 0x1D7F6];

  // ===== MATCH PATTERN =====
  $pattern = '/[' .
    '\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}' .
    '\x{FF01}-\x{FF5E}' .
    '\x{1D400}-\x{1D7FF}' .
    '\x{2102}\x{210A}-\x{2112}\x{2115}\x{2119}-\x{211D}\x{2124}\x{2128}\x{212C}\x{212D}\x{212F}\x{2130}\x{2131}\x{2133}\x{2134}' .
    ']/u';

  // ===== STRIP EMOJI =====
  $text = preg_replace('/[' . emoji_class_body('\\x') . ']/u', '', $text);

  // ===== REPLACE LOOK-ALIKES =====
  $result = preg_replace_callback(
    $pattern,
    function (array $m) use ($legacy, $letterBlocks, $digitBlocks): string {
      $cp = mb_ord($m[0], 'UTF-8');
      if ($cp === false) {
        return $m[0];
      }

      if ($cp === 0x00A0) {
        return ' ';
      }
      if (in_array($cp, [0x200B, 0x200C, 0x200D, 0x2060, 0xFEFF], true)) {
        return '';
      }

      if ($cp >= 0xFF01 && $cp <= 0xFF5E) {
        return mb_chr($cp - 0xFEE0, 'UTF-8');
      }

      if (isset($legacy[$cp])) {
        return $legacy[$cp];
      }

      foreach ($letterBlocks as $start) {
        if ($cp >= $start && $cp <= $start + 51) {
          $offset = $cp - $start;
          return $offset < 26
            ? chr(65 + $offset)
            : chr(97 + ($offset - 26));
        }
      }

      foreach ($digitBlocks as $start) {
        if ($cp >= $start && $cp <= $start + 9) {
          return chr(48 + ($cp - $start));
        }
      }

      return $m[0];
    },
    $text
  );

  // ===== COLLAPSE SPACES =====
  return trim(preg_replace('/[^\S\r\n]{2,}/u', ' ', $result));
}

// ===== STRIP WRAPPING QUOTES =====
function strip_wrapping_quotes(string $text): string
{
  return trim(preg_replace('/^["\x{201C}\x{201D}]([^"\x{201C}\x{201D}]*)["\x{201C}\x{201D}]$/u', '$1', trim($text)));
}

// ===== ESCAPE TEXT, TURNING [text](https://address) INTO LINKS =====
function render_text_with_links(string $text): string
{
  return preg_replace_callback(
    '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
    fn(array $match): string => '<strong><a href="' . $match[2] . '" class="underlined" target="_blank" rel="noopener noreferrer">' . $match[1] . '</a></strong>',
    htmlspecialchars($text, ENT_QUOTES)
  );
}
