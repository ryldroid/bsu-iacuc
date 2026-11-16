<?php

class IpnFormat
{
    public const DEFAULTS = [
        'ipn_prefix'        => '',
        'ipn_separator'     => '',
        'ipn_digits'        => '4',
        'ipn_year'          => 'yy',
        'ipn_year_position' => 'after',
    ];

    public const SEPARATORS = ['' => 'None', '-' => 'Dash ( - )', '.' => 'Dot ( . )'];
    public const YEARS      = ['yy' => '2 digits (26)', 'yyyy' => '4 digits (2026)', 'none' => 'No year'];
    public const POSITIONS  = ['after' => 'After the number', 'before' => 'Before the number'];

    private static ?array $current = null;

    public static function load(): array
    {
        if (self::$current === null) {
            $saved         = (new SiteSettingModel())->getAll();
            self::$current = self::clean($saved) ?? self::DEFAULTS;
        }
        return self::$current;
    }

    public static function clean(array $input): ?array
    {
        $format = array_map(
            fn($value) => trim((string) $value),
            array_merge(self::DEFAULTS, array_intersect_key($input, self::DEFAULTS))
        );

        $valid = preg_match('/^[A-Za-z0-9.-]{0,6}$/', $format['ipn_prefix'])
            && isset(self::SEPARATORS[$format['ipn_separator']])
            && ctype_digit($format['ipn_digits']) && $format['ipn_digits'] >= 1 && $format['ipn_digits'] <= 8
            && isset(self::YEARS[$format['ipn_year']])
            && isset(self::POSITIONS[$format['ipn_year_position']]);

        return $valid ? $format : null;
    }

    public static function format(int $sequence): string
    {
        $format = self::load();
        $year   = ['yy' => date('y'), 'yyyy' => date('Y'), 'none' => ''][$format['ipn_year']];

        return implode($format['ipn_separator'], self::pieces(
            $format,
            $format['ipn_prefix'],
            sprintf('%0' . $format['ipn_digits'] . 'd', $sequence),
            $year
        ));
    }

    public static function pattern(): string
    {
        $format = self::load();

        return '^' . implode(self::escape($format['ipn_separator']), self::pieces(
            $format,
            self::escape($format['ipn_prefix']),
            '(\d{' . $format['ipn_digits'] . '})',
            '(\d{' . ($format['ipn_year'] === 'yyyy' ? 4 : 2) . '})'
        )) . '$';
    }

    public static function sequenceThisYear(string $ipn): ?int
    {
        $format = self::load();
        if (!preg_match('/' . self::pattern() . '/', $ipn, $matches)) {
            return null;
        }

        $yearFirst = $format['ipn_year'] !== 'none' && $format['ipn_year_position'] === 'before';
        $sequence  = (int) $matches[$yearFirst ? 2 : 1];
        $year      = $format['ipn_year'] === 'none' ? null : $matches[$yearFirst ? 1 : 2];

        return ($year === null || $year === substr(date('Y'), -strlen($year))) ? $sequence : null;
    }

    public static function message(): string
    {
        return 'IPN must follow the format ' . self::format(1) . '.';
    }

    public static function forJs(): array
    {
        $example = self::format(1);

        return [
            'pattern'     => self::pattern(),
            'placeholder' => 'e.g. ' . $example,
            'maxlength'   => strlen($example),
            'numeric'     => ctype_digit($example),
            'message'     => self::message(),
        ];
    }

    private static function pieces(array $format, string $prefix, string $sequence, string $year): array
    {
        $numbers = match (true) {
            $format['ipn_year'] === 'none'            => [$sequence],
            $format['ipn_year_position'] === 'before' => [$year, $sequence],
            default                                   => [$sequence, $year],
        };

        return array_values(array_filter([$prefix, ...$numbers], fn($piece) => $piece !== ''));
    }

    private static function escape(string $text): string
    {
        return str_replace('.', '\.', $text);
    }
}
