<?php

/**
 * NEW FILE
 *
 * Why this exists:
 * When people copy text from Facebook (or from one of the many "bold /
 * italic / fancy text" generator sites that people use because Facebook
 * posts don't support real formatting), the "bold" or "italic" look isn't
 * real bold/italic styling - it's made of entirely different Unicode
 * characters that just *look* like styled Latin letters (e.g. the "bold A"
 * someone pastes is not "A" + bold, it's the character U+1D400).
 *
 * Our site's fonts don't have glyphs for those look-alike characters, so
 * the browser silently falls back to whatever system font *does* have
 * them for just those letters. That's why a pasted announcement can look
 * like it's using two different fonts in the same sentence/title - it is,
 * even though the underlying HTML/CSS never asked for that.
 *
 * normalize_pasted_text() maps those look-alike characters back to plain
 * ASCII letters/digits, and tidies up a couple of other invisible
 * paste artifacts (non-breaking spaces, zero-width spaces) along the way.
 * Normal text (including normal accented text, emoji, etc.) passes through
 * completely untouched.
 */

function normalize_pasted_text(?string $text): string
{
  if ($text === null || $text === '') {
    return '';
  }

  // A handful of styled letters don't have a slot inside the main
  // "Mathematical Alphanumeric Symbols" block below - Unicode reserves
  // those specific code points as unused and reuses older, unrelated
  // "Letterlike Symbols" characters for them instead (e.g. script capital
  // "B" is U+212C, not a code point next to script "A"). Map those back too.
  static $legacy = [
    0x210E => 'h', // italic h
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
    0x2134 => 'o', // script
    0x212D => 'C',
    0x210C => 'H',
    0x2111 => 'I',
    0x211C => 'R',
    0x2128 => 'Z', // fraktur
    0x2102 => 'C',
    0x210D => 'H',
    0x2115 => 'N',
    0x2119 => 'P',
    0x211A => 'Q',
    0x211D => 'R',
    0x2124 => 'Z', // double-struck
  ];

  // Start of each contiguous "capital A..Z, lowercase a..z" (52 code
  // points) run inside the Mathematical Alphanumeric Symbols block -
  // covers bold, italic, bold italic, script, bold script, fraktur,
  // double-struck, bold fraktur, sans-serif (plain/bold/italic/bold
  // italic), and monospace look-alikes.
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

  // Start of each contiguous "0..9" (10 code points) run for the styled
  // digit sets (bold, double-struck, sans-serif, sans-serif bold, monospace).
  static $digitBlocks = [0x1D7CE, 0x1D7D8, 0x1D7E2, 0x1D7EC, 0x1D7F6];

  $pattern = '/[' .
    '\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}' . // NBSP + invisible spacers
    '\x{FF01}-\x{FF5E}' .                          // fullwidth ASCII look-alikes
    '\x{1D400}-\x{1D7FF}' .                        // math alphanumeric symbols
    '\x{2102}\x{210A}-\x{2112}\x{2115}\x{2119}-\x{211D}\x{2124}\x{2128}\x{212C}\x{212D}\x{212F}\x{2130}\x{2131}\x{2133}\x{2134}' . // legacy holes
    ']/u';

  return preg_replace_callback(
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

      // Fullwidth ASCII look-alikes ("Ａ", "１", "！") share a fixed
      // offset from their plain ASCII counterpart.
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
            ? chr(65 + $offset)         // A-Z
            : chr(97 + ($offset - 26)); // a-z
        }
      }

      foreach ($digitBlocks as $start) {
        if ($cp >= $start && $cp <= $start + 9) {
          return chr(48 + ($cp - $start));
        }
      }

      // Styled Greek letters etc. inside the same block that we don't
      // specifically handle - leave as-is rather than guessing.
      return $m[0];
    },
    $text
  );
}
// END NEW FILE