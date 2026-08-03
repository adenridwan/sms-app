<?php

namespace App\Support;

/**
 * Update baris KEY=VALUE di file .env tanpa mengganggu baris lain, dengan
 * backup otomatis (.env.bak) sebelum menulis. Dipakai oleh
 * DatabaseConnectionController untuk mengubah kredensial DB dari UI —
 * fitur sensitif, lihat CLAUDE.md § Keselamatan Database.
 */
class EnvFileWriter
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * @param array<string, string|null> $values key => value baru. Value null berarti KEY="" (dikosongkan, bukan dihapus).
     */
    public function update(array $values): void
    {
        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new \RuntimeException("Tidak bisa membaca file env: {$this->path}");
        }

        // Backup sebelum menulis apa pun.
        file_put_contents($this->path . '.bak', $contents);

        // Pertahankan gaya line ending file aslinya (CRLF di Windows kalau
        // memang sudah begitu) supaya diff-nya minimal — bukan cuma kosmetik,
        // pernah menulis ulang seluruh file dengan PHP_EOL yang beda dari
        // aslinya sehingga tiap baris "berubah" walau isinya sama.
        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $lines = preg_split('/\r\n|\r|\n/', $contents);

        foreach ($values as $key => $value) {
            $formatted = $key . '=' . $this->formatValue($value ?? '');
            $found = false;

            foreach ($lines as $i => $line) {
                if (preg_match('/^' . preg_quote($key, '/') . '=/', $line)) {
                    $lines[$i] = $formatted;
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $lines[] = $formatted;
            }
        }

        file_put_contents($this->path, implode($eol, $lines));
    }

    /**
     * Bungkus dengan tanda kutip ganda bila perlu (spasi/karakter khusus),
     * escape backslash & tanda kutip supaya parser dotenv tidak salah baca
     * (lihat insiden PG_DUMP_PATH — backslash mentah dalam kutip ganda
     * diinterpretasikan sebagai escape sequence oleh phpdotenv).
     */
    private function formatValue(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.\-\/:]+$/', $value)) {
            return $value;
        }

        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        return '"' . $escaped . '"';
    }
}
