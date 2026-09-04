<?php

namespace App\Domain\Payroll\Services;

use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollSlip;
use App\Infrastructure\Persistence\Eloquent\Payroll\PayrollSlipItem;
use App\Infrastructure\Persistence\Eloquent\Payroll\SalaryComponent;
use Illuminate\Support\Collection;

/**
 * Service untuk menghitung komponen gaji dengan tipe percentage dan formula.
 */
class ComponentCalculationService
{
    /**
     * Referensi yang tersedia untuk percentage_of
     */
    public const PERCENTAGE_REFERENCES = [
        'base_salary' => 'Gaji Pokok',
        'gross_salary' => 'Gaji Kotor (Total Pendapatan)',
    ];

    /**
     * Operator yang tersedia untuk formula
     */
    public const FORMULA_OPERATORS = [
        '+' => 'Tambah',
        '-' => 'Kurang',
        '*' => 'Kali',
        '/' => 'Bagi',
    ];

    /**
     * Hitung nilai komponen berdasarkan tipe kalkulasinya.
     *
     * @param SalaryComponent $component Komponen yang akan dihitung
     * @param PayrollSlip $slip Slip gaji untuk konteks nilai
     * @param array $calculatedItems Item yang sudah dihitung (untuk referensi formula)
     * @return float Nilai hasil perhitungan
     */
    public function calculateComponentValue(
        SalaryComponent $component,
        PayrollSlip $slip,
        array $calculatedItems = []
    ): float {
        return match ($component->calculation_type) {
            SalaryComponent::CALC_FIXED => (float) $component->default_value,
            SalaryComponent::CALC_PERCENTAGE => $this->calculatePercentage($component, $slip, $calculatedItems),
            SalaryComponent::CALC_FORMULA => $this->calculateFormula($component, $slip, $calculatedItems),
            default => (float) $component->default_value,
        };
    }

    /**
     * Hitung nilai persentase.
     */
    protected function calculatePercentage(
        SalaryComponent $component,
        PayrollSlip $slip,
        array $calculatedItems
    ): float {
        $percentage = (float) $component->default_value;
        $baseValue = 0;

        // Tentukan base value berdasarkan percentage_of
        switch ($component->percentage_of) {
            case 'base_salary':
                $baseValue = (float) $slip->base_salary;
                break;

            case 'gross_salary':
                // Ambil dari slip atau hitung dari items
                if ($slip->gross_salary > 0) {
                    $baseValue = (float) $slip->gross_salary;
                } else {
                    // Hitung dari items yang sudah ada
                    $baseValue = $slip->items()
                        ->where('type', 'earning')
                        ->sum('amount');
                }
                break;

            default:
                // Cek apakah referensi ke komponen lain
                if ($component->percentage_component_id) {
                    $baseValue = $this->getComponentValue(
                        $component->percentage_component_id,
                        $slip,
                        $calculatedItems
                    );
                } elseif ($component->percentage_of) {
                    // Coba cari dari calculated items by code
                    $baseValue = $this->getComponentValueByCode(
                        $component->percentage_of,
                        $slip,
                        $calculatedItems
                    );
                }
                break;
        }

        return $baseValue * ($percentage / 100);
    }

    /**
     * Hitung nilai formula.
     *
     * Formula disimpan sebagai JSON array:
     * [
     *   {"type": "component", "id": "uuid", "code": "GAJI_POKOK"},
     *   {"type": "operator", "value": "+"},
     *   {"type": "component", "id": "uuid", "code": "TUNJ_JABATAN"},
     *   {"type": "operator", "value": "*"},
     *   {"type": "number", "value": 0.1}
     * ]
     *
     * Atau format sederhana:
     * [
     *   {"type": "base_salary"},
     *   {"type": "operator", "value": "*"},
     *   {"type": "number", "value": 0.05}
     * ]
     */
    protected function calculateFormula(
        SalaryComponent $component,
        PayrollSlip $slip,
        array $calculatedItems
    ): float {
        $formula = $component->formula;

        if (empty($formula)) {
            return (float) $component->default_value;
        }

        // Jika formula adalah string, coba decode sebagai JSON
        if (is_string($formula)) {
            $formula = json_decode($formula, true);
        }

        if (!is_array($formula) || empty($formula)) {
            return (float) $component->default_value;
        }

        // Evaluasi formula
        return $this->evaluateFormula($formula, $slip, $calculatedItems);
    }

    /**
     * Evaluasi formula expression.
     *
     * Menggunakan pendekatan sederhana: kumpulkan semua nilai dan operator,
     * lalu evaluasi dari kiri ke kanan dengan precedence (* dan / dulu).
     */
    protected function evaluateFormula(array $formula, PayrollSlip $slip, array $calculatedItems): float
    {
        $values = [];
        $operators = [];

        foreach ($formula as $item) {
            $type = $item['type'] ?? '';

            switch ($type) {
                case 'component':
                    $value = $this->getComponentValue(
                        $item['id'] ?? null,
                        $slip,
                        $calculatedItems
                    );
                    $values[] = $value;
                    break;

                case 'base_salary':
                    $values[] = (float) $slip->base_salary;
                    break;

                case 'gross_salary':
                    $values[] = (float) $slip->gross_salary;
                    break;

                case 'number':
                    $values[] = (float) ($item['value'] ?? 0);
                    break;

                case 'operator':
                    $operators[] = $item['value'] ?? '+';
                    break;
            }
        }

        if (empty($values)) {
            return 0;
        }

        // Evaluasi dengan precedence: * dan / dulu
        return $this->evaluateWithPrecedence($values, $operators);
    }

    /**
     * Evaluasi dengan operator precedence.
     */
    protected function evaluateWithPrecedence(array $values, array $operators): float
    {
        // Pass 1: Evaluasi * dan /
        $newValues = [$values[0]];
        $newOperators = [];

        for ($i = 0; $i < count($operators); $i++) {
            $op = $operators[$i];
            $nextValue = $values[$i + 1] ?? 0;

            if ($op === '*' || $op === '/') {
                $lastIndex = count($newValues) - 1;
                if ($op === '*') {
                    $newValues[$lastIndex] *= $nextValue;
                } else {
                    $newValues[$lastIndex] = $nextValue != 0
                        ? $newValues[$lastIndex] / $nextValue
                        : 0;
                }
            } else {
                $newValues[] = $nextValue;
                $newOperators[] = $op;
            }
        }

        // Pass 2: Evaluasi + dan -
        $result = $newValues[0];
        for ($i = 0; $i < count($newOperators); $i++) {
            $op = $newOperators[$i];
            $value = $newValues[$i + 1] ?? 0;

            if ($op === '+') {
                $result += $value;
            } elseif ($op === '-') {
                $result -= $value;
            }
        }

        return $result;
    }

    /**
     * Ambil nilai komponen dari slip atau calculated items.
     */
    protected function getComponentValue(?string $componentId, PayrollSlip $slip, array $calculatedItems): float
    {
        if (!$componentId) {
            return 0;
        }

        // Cek di calculated items dulu
        if (isset($calculatedItems[$componentId])) {
            return (float) $calculatedItems[$componentId];
        }

        // Cek di slip items
        $item = $slip->items()->where('salary_component_id', $componentId)->first();
        if ($item) {
            return (float) $item->amount;
        }

        // Fallback ke default value komponen
        $component = SalaryComponent::find($componentId);
        return $component ? (float) $component->default_value : 0;
    }

    /**
     * Ambil nilai komponen berdasarkan kode.
     */
    protected function getComponentValueByCode(string $code, PayrollSlip $slip, array $calculatedItems): float
    {
        // Cek di slip items by code
        $item = $slip->items()->where('component_code', $code)->first();
        if ($item) {
            return (float) $item->amount;
        }

        // Cek di calculated items by code (jika ada mapping)
        foreach ($calculatedItems as $id => $value) {
            $component = SalaryComponent::find($id);
            if ($component && $component->code === $code) {
                return (float) $value;
            }
        }

        return 0;
    }

    /**
     * Preview formula dengan nilai aktual.
     *
     * Mengembalikan array dengan nilai setiap elemen untuk ditampilkan di GUI.
     */
    public function previewFormula(
        array $formula,
        PayrollSlip $slip,
        array $calculatedItems = []
    ): array {
        $preview = [];

        foreach ($formula as $item) {
            $type = $item['type'] ?? '';
            $previewItem = $item;

            switch ($type) {
                case 'component':
                    $value = $this->getComponentValue($item['id'] ?? null, $slip, $calculatedItems);
                    $previewItem['current_value'] = $value;
                    $previewItem['formatted_value'] = 'Rp ' . number_format($value, 0, ',', '.');
                    break;

                case 'base_salary':
                    $value = (float) $slip->base_salary;
                    $previewItem['current_value'] = $value;
                    $previewItem['formatted_value'] = 'Rp ' . number_format($value, 0, ',', '.');
                    break;

                case 'gross_salary':
                    $value = (float) $slip->gross_salary;
                    $previewItem['current_value'] = $value;
                    $previewItem['formatted_value'] = 'Rp ' . number_format($value, 0, ',', '.');
                    break;

                case 'number':
                    $value = (float) ($item['value'] ?? 0);
                    $previewItem['current_value'] = $value;
                    // Format number: jika < 1 maka percentage, else currency
                    if ($value < 1 && $value > 0) {
                        $previewItem['formatted_value'] = ($value * 100) . '%';
                    } else {
                        $previewItem['formatted_value'] = number_format($value, 2, ',', '.');
                    }
                    break;
            }

            $preview[] = $previewItem;
        }

        // Hitung hasil akhir
        $result = $this->evaluateFormula($formula, $slip, $calculatedItems);

        return [
            'items' => $preview,
            'result' => $result,
            'result_formatted' => 'Rp ' . number_format($result, 0, ',', '.'),
        ];
    }

    /**
     * Validasi formula structure.
     */
    public function validateFormula(array $formula): array
    {
        $errors = [];
        $expectOperand = true; // Dimulai dengan operand

        foreach ($formula as $index => $item) {
            $type = $item['type'] ?? '';

            if ($expectOperand) {
                // Expecting operand (component, base_salary, gross_salary, number)
                if (!in_array($type, ['component', 'base_salary', 'gross_salary', 'number'])) {
                    $errors[] = "Item #{$index}: Expected operand, got '{$type}'";
                }

                if ($type === 'component' && empty($item['id'])) {
                    $errors[] = "Item #{$index}: Component ID is required";
                }

                if ($type === 'number' && !isset($item['value'])) {
                    $errors[] = "Item #{$index}: Number value is required";
                }

                $expectOperand = false;
            } else {
                // Expecting operator
                if ($type !== 'operator') {
                    $errors[] = "Item #{$index}: Expected operator, got '{$type}'";
                }

                if (!in_array($item['value'] ?? '', array_keys(self::FORMULA_OPERATORS))) {
                    $errors[] = "Item #{$index}: Invalid operator '{$item['value']}'";
                }

                $expectOperand = true;
            }
        }

        // Formula harus berakhir dengan operand
        // Jika $expectOperand = true di akhir, berarti item terakhir adalah operator (menunggu operand)
        if ($expectOperand && !empty($formula)) {
            $errors[] = "Formula must end with an operand";
        }

        return $errors;
    }
}
