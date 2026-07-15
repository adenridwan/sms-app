<?php

use Illuminate\Support\Str;

if (!function_exists('tenant')) {
    /**
     * Get the current tenant.
     */
    function tenant(): ?object
    {
        return app('tenant')->current();
    }
}

if (!function_exists('tenant_id')) {
    /**
     * Get the current tenant ID.
     */
    function tenant_id(): ?string
    {
        return tenant()?->id;
    }
}

if (!function_exists('format_currency')) {
    /**
     * Format a number as Indonesian currency.
     */
    function format_currency(float|int $amount, string $currency = 'IDR'): string
    {
        return $currency . ' ' . number_format($amount, 0, ',', '.');
    }
}

if (!function_exists('format_date')) {
    /**
     * Format a date in Indonesian format.
     */
    function format_date($date, string $format = 'd F Y'): string
    {
        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }

        return $date->translatedFormat($format);
    }
}

if (!function_exists('format_datetime')) {
    /**
     * Format a datetime in Indonesian format.
     */
    function format_datetime($datetime, string $format = 'd F Y H:i'): string
    {
        if (is_string($datetime)) {
            $datetime = \Carbon\Carbon::parse($datetime);
        }

        return $datetime->translatedFormat($format);
    }
}

if (!function_exists('generate_code')) {
    /**
     * Generate a unique code with prefix.
     */
    function generate_code(string $prefix, int $length = 8): string
    {
        return strtoupper($prefix . '-' . Str::random($length));
    }
}

if (!function_exists('generate_invoice_number')) {
    /**
     * Generate an invoice number.
     */
    function generate_invoice_number(string $prefix = 'INV'): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}-{$date}-{$random}";
    }
}

if (!function_exists('calculate_age')) {
    /**
     * Calculate age from birth date.
     */
    function calculate_age($birthDate): int
    {
        if (is_string($birthDate)) {
            $birthDate = \Carbon\Carbon::parse($birthDate);
        }

        return $birthDate->age;
    }
}

if (!function_exists('grade_to_letter')) {
    /**
     * Convert numeric grade to letter grade.
     */
    function grade_to_letter(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'E',
        };
    }
}

if (!function_exists('grade_to_predicate')) {
    /**
     * Convert numeric grade to predicate.
     */
    function grade_to_predicate(float $score): string
    {
        return match (true) {
            $score >= 90 => 'Sangat Baik',
            $score >= 80 => 'Baik',
            $score >= 70 => 'Cukup',
            $score >= 60 => 'Kurang',
            default => 'Sangat Kurang',
        };
    }
}

if (!function_exists('day_name')) {
    /**
     * Get Indonesian day name from day number.
     */
    function day_name(int $dayOfWeek): string
    {
        $days = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];

        return $days[$dayOfWeek] ?? '';
    }
}

if (!function_exists('month_name')) {
    /**
     * Get Indonesian month name from month number.
     */
    function month_name(int $month): string
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $months[$month] ?? '';
    }
}

if (!function_exists('academic_year_name')) {
    /**
     * Generate academic year name from start year.
     */
    function academic_year_name(int $startYear): string
    {
        return $startYear . '/' . ($startYear + 1);
    }
}

if (!function_exists('storage_url')) {
    /**
     * Get the URL for a storage file.
     */
    function storage_url(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::url($path);
    }
}

if (!function_exists('sanitize_filename')) {
    /**
     * Sanitize a filename.
     */
    function sanitize_filename(string $filename): string
    {
        // Remove any character that isn't alphanumeric, dash, underscore, or dot
        $filename = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '_', $filename);

        // Remove multiple consecutive underscores
        $filename = preg_replace('/_+/', '_', $filename);

        return $filename;
    }
}

if (!function_exists('mask_email')) {
    /**
     * Mask an email address.
     */
    function mask_email(string $email): string
    {
        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1] ?? '';

        $maskedName = substr($name, 0, 2) . str_repeat('*', max(strlen($name) - 2, 0));

        return $maskedName . '@' . $domain;
    }
}

if (!function_exists('mask_phone')) {
    /**
     * Mask a phone number.
     */
    function mask_phone(string $phone): string
    {
        $length = strlen($phone);

        if ($length <= 4) {
            return $phone;
        }

        return substr($phone, 0, 4) . str_repeat('*', $length - 7) . substr($phone, -3);
    }
}
