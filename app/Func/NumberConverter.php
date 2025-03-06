<?php

namespace App\Func;

use InvalidArgumentException;

class NumberConverter
{
    /**
     * @param float|int $value 変換する値
     * @param string $fromUnit 元の単位
     * @param string $toUnit 変換先の単位
     * @param bool $includeUnit 単位を含めるかどうか
     * @param int $precision 小数点以下の桁数
     * @return string|float 変換された値
     */
    public static function convert(
        float|int $value,
        string $fromUnit,
        string $toUnit,
        bool $includeUnit = false,
        int $precision = 0
    ): string|float {
        if ($toUnit !== 'auto') {
            if ($fromUnit === 'MiB' && $toUnit === 'MB') {
                $multiplier = bcdiv('1000', '1024', 10);
                $result = bcmul((string)$value, $multiplier, 10);
                $formattedResult = phpversion() >= 8.4 ? bcround($result, $precision) : round($result, $precision);
                return $includeUnit ? "{$formattedResult} MB" : $formattedResult;
            } elseif ($fromUnit === 'MB' && $toUnit === 'MiB') {
                $multiplier = bcdiv('1024', '1000', 10);
                $result = bcmul((string)$value, $multiplier, 10);
                $formattedResult = phpversion() >= 8.4 ? bcround($result, $precision) : round($result, $precision);
                return $includeUnit ? "{$formattedResult} MiB" : $formattedResult;
            }
        }
        $units = [
            'B'   => '1',
            'KB'  => '1000',
            'MB'  => '1000000',
            'GB'  => '1000000000',
            'TB'  => '1000000000000',
            'KiB' => '1024',
            'MiB' => '1048576',
            'GiB' => '1073741824',
            'TiB' => '1099511627776',
        ];
        if ($toUnit !== 'auto' && $toUnit !== 'iauto' && (!isset($units[$fromUnit]) || !isset($units[$toUnit]))) {
            throw new InvalidArgumentException('Invalid unit specified');
        }
        $bytes = bcmul((string)$value, $units[$fromUnit], 10);
        if ($toUnit === 'auto') {
            $selectedUnit = 'B';
            foreach (['TB', 'GB', 'MB', 'KB', 'B'] as $unit) {
                if (bccomp($bytes, $units[$unit], 10) >= 0) {
                    $result = bcdiv($bytes, $units[$unit], 10);
                    $selectedUnit = $unit;
                    break;
                }
            }
            if (!isset($result)) {
                $result = $bytes;
            }
            $formattedResult = phpversion() >= 8.4 ? bcround($result, $precision) : round($result, $precision);
            return $includeUnit ? "{$formattedResult} {$selectedUnit}" : $formattedResult;
        } elseif ($toUnit === 'iauto') {
            $selectedUnit = 'B';
            foreach (['TiB', 'GiB', 'MiB', 'KiB', 'B'] as $unit) {
                if (bccomp($bytes, $units[$unit], 10) >= 0) {
                    $result = bcdiv($bytes, $units[$unit], 10);
                    $selectedUnit = $unit;
                    break;
                }
            }
            if (!isset($result)) {
                $result = $bytes;
            }
            $formattedResult = phpversion() >= 8.4 ? bcround($result, $precision) : round($result, $precision);
            return $includeUnit ? "{$formattedResult} {$selectedUnit}" : $formattedResult;
        }
        $result = bcdiv($bytes, $units[$toUnit], 10);
        $formattedResult = phpversion() >= 8.4 ? bcround($result, $precision) : round($result, $precision);
        return $includeUnit ? "{$formattedResult} {$toUnit}" : $formattedResult;
    }

    /**
     * @param float|int $value 変換する値
     * @param bool $isCore コア数またはパーセント
     * @param int $precision 小数点以下の桁数
     * @return float|int 変換された値
     */
    public static function convertCpuCore(
        float|int $value,
        bool $isCore = true,
        int $precision = 2
    ): float|int {
        $result = $isCore
            ? bcdiv((string)$value, '100', 10)
            : bcmul((string)$value, '100', 10);
        return phpversion() >= 8.4 ? bcround($result, $precision) : round($result, $precision);
    }
}
