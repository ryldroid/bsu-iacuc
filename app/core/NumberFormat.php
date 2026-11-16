<?php

class NumberFormat
{
    private const DEFAULTS = [
        'ipn_prefix'        => '',
        'ipn_separator'     => '',
        'ipn_digits'        => '4',
        'ipn_year'          => 'yy',
        'ipn_year_position' => 'after',
        'ar_prefix'         => 'AR',
        'ar_separator'      => '-',
        'ar_digits'         => '4',
        'ar_year'           => 'yyyy',
        'ar_year_position'  => 'before',
    ];

    public const LABELS     = ['ipn' => 'IPN', 'ar' => 'AR number'];
    public const SEPARATORS = ['' => 'None', '-' => 'Dash ( - )', '.' => 'Dot ( . )'];
    public const YEARS      = ['yy' => '2 digits (26)', 'yyyy' => '4 digits (2026)', 'none' => 'No year'];
    public const POSITIONS  = ['after' => 'After the number', 'before' => 'Before the number'];

    private static array $loaded = [];

    private function __construct(private string $kind, private array $format) {}

    public static function of(string $kind): self
    {
        if (!isset(self::$loaded[$kind])) {
            $saved               = (new SiteSettingModel())->getAll();
            self::$loaded[$kind] = new self($kind, self::clean($kind, $saved) ?? self::defaults($kind));
        }
        return self::$loaded[$kind];
    }

    public static function clean(string $kind, array $input): ?array
    {
        $defaults = self::defaults($kind);
        $format   = array_map(
            fn($value) => trim((string) $value),
            array_merge($defaults, array_intersect_key($input, $defaults))
        );
        $get = fn(string $key) => $format[$kind . '_' . $key];

        $valid = preg_match('/^[A-Za-z0-9.-]{0,6}$/', $get('prefix'))
            && isset(self::SEPARATORS[$get('separator')])
            && ctype_digit($get('digits')) && $get('digits') >= 1 && $get('digits') <= 8
            && isset(self::YEARS[$get('year')])
            && isset(self::POSITIONS[$get('year_position')]);

        return $valid ? $format : null;
    }

    public function values(): array
    {
        return $this->format;
    }

    public function format(int $sequence): string
    {
        return implode($this->get('separator'), $this->pieces(
            $this->get('prefix'),
            sprintf('%0' . $this->get('digits') . 'd', $sequence),
            $this->year()
        ));
    }

    public function pattern(): string
    {
        return '^' . implode(self::escape($this->get('separator')), $this->pieces(
            self::escape($this->get('prefix')),
            '(\d{' . $this->get('digits') . '})',
            '(\d{' . ($this->get('year') === 'yyyy' ? 4 : 2) . '})'
        )) . '$';
    }

    public function sequenceThisYear(string $number): ?int
    {
        if (!preg_match('/' . $this->pattern() . '/', $number, $matches)) {
            return null;
        }

        $yearFirst = $this->get('year') !== 'none' && $this->get('year_position') === 'before';
        $sequence  = (int) $matches[$yearFirst ? 2 : 1];
        $year      = $this->get('year') === 'none' ? null : $matches[$yearFirst ? 1 : 2];

        return ($year === null || $year === substr(date('Y'), -strlen($year))) ? $sequence : null;
    }

    public function leading(): string
    {
        $pieces = $this->pieces($this->get('prefix'), "\0", $this->year());
        $before = array_slice($pieces, 0, array_search("\0", $pieces, true));

        return $before ? implode($this->get('separator'), $before) . $this->get('separator') : '';
    }

    public function message(): string
    {
        return self::LABELS[$this->kind] . ' must follow the format ' . $this->format(1) . '.';
    }

    public function forJs(): array
    {
        $example = $this->format(1);

        return [
            'pattern'     => $this->pattern(),
            'placeholder' => 'e.g. ' . $example,
            'maxlength'   => strlen($example),
            'numeric'     => ctype_digit($example),
            'message'     => $this->message(),
            'leading'     => $this->leading(),
        ];
    }

    private function get(string $key): string
    {
        return $this->format[$this->kind . '_' . $key];
    }

    private function year(): string
    {
        return ['yy' => date('y'), 'yyyy' => date('Y'), 'none' => ''][$this->get('year')];
    }

    private function pieces(string $prefix, string $sequence, string $year): array
    {
        $numbers = match (true) {
            $this->get('year') === 'none'            => [$sequence],
            $this->get('year_position') === 'before' => [$year, $sequence],
            default                                  => [$sequence, $year],
        };

        return array_values(array_filter([$prefix, ...$numbers], fn($piece) => $piece !== ''));
    }

    private static function defaults(string $kind): array
    {
        return array_filter(self::DEFAULTS, fn($key) => str_starts_with($key, $kind . '_'), ARRAY_FILTER_USE_KEY);
    }

    private static function escape(string $text): string
    {
        return str_replace('.', '\.', $text);
    }
}
