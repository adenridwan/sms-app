import { format, isValid, parse } from 'date-fns';
import { id as localeId } from 'date-fns/locale';
import { CalendarIcon, X } from 'lucide-react';

import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

/**
 * Format nilai masuk/keluar — sengaja sama persis dengan yang dihasilkan
 * `<input type="datetime-local">` (dan dengan `iso.slice(0, 16)` yang dipakai
 * saat memuat data lama), supaya komponen ini bisa menggantikan input bawaan
 * tanpa menyentuh state form maupun payload yang dikirim ke API.
 */
const VALUE_FORMAT = "yyyy-MM-dd'T'HH:mm";
const DATE_PART = 'yyyy-MM-dd';
const TIME_PART = 'HH:mm';

/**
 * `parse`, bukan `new Date(value)`: string tanpa zona waktu harus dibaca
 * sebagai waktu lokal. Lihat alasan yang sama di `date-picker.tsx`.
 */
function parseValue(value?: string | null): Date | undefined {
    if (!value) return undefined;

    const parsed = parse(value, VALUE_FORMAT, new Date());

    return isValid(parsed) ? parsed : undefined;
}

interface DateTimePickerProps {
    id?: string;
    value?: string | null;
    onChange: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    className?: string;
    /** Batas tahun pada dropdown kalender. */
    fromYear?: number;
    toYear?: number;
}

/**
 * Pemilih tanggal **beserta jam**, pendamping `DatePicker` (yang hanya
 * menangani tanggal). Dibuat karena `<input type="datetime-local">` bawaan
 * praktis tidak bisa dipakai di tema gelap aplikasi ini: ikon kalendernya
 * nyaris tak terlihat sehingga kolomnya terbaca seolah mati/nonaktif.
 */
export function DateTimePicker({
    id,
    value,
    onChange,
    placeholder = 'Pilih tanggal & jam',
    disabled,
    invalid,
    className,
    fromYear = new Date().getFullYear() - 5,
    toYear = new Date().getFullYear() + 10,
}: DateTimePickerProps) {
    const selected = parseValue(value);

    // Memilih tanggal saja tidak boleh menghapus jam yang sudah diisi, dan
    // sebaliknya mengubah jam tidak boleh menghapus tanggalnya.
    const handleDateSelect = (date: Date | undefined) => {
        if (!date) {
            onChange('');
            return;
        }

        const time = selected ? format(selected, TIME_PART) : '00:00';
        onChange(`${format(date, DATE_PART)}T${time}`);
    };

    const handleTimeChange = (time: string) => {
        if (!time) return;

        // Belum ada tanggal: pakai hari ini, supaya mengisi jam lebih dulu
        // tidak menghasilkan nilai setengah jadi yang ditolak validasi.
        const datePart = selected ? format(selected, DATE_PART) : format(new Date(), DATE_PART);
        onChange(`${datePart}T${time}`);
    };

    return (
        <div className={cn('flex gap-2', className)}>
            <Popover modal={true}>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        disabled={disabled}
                        className={cn(
                            'w-full justify-start px-3 font-normal',
                            !selected && 'text-muted-foreground',
                            invalid && 'border-destructive'
                        )}
                    >
                        <CalendarIcon className="mr-2 h-4 w-4 shrink-0 opacity-70" />
                        <span className="truncate">
                            {selected
                                ? format(selected, "d MMMM yyyy, HH:mm", { locale: localeId })
                                : placeholder}
                        </span>
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-3" onOpenAutoFocus={(e) => e.preventDefault()}>
                    <Calendar
                        mode="single"
                        selected={selected}
                        defaultMonth={selected}
                        captionLayout="dropdown"
                        startMonth={new Date(fromYear, 0)}
                        endMonth={new Date(toYear, 11)}
                        onSelect={handleDateSelect}
                    />
                    <div className="mt-3 space-y-1.5 border-t pt-3">
                        <Label htmlFor={id ? `${id}-time` : undefined} className="text-xs">
                            Jam
                        </Label>
                        <Input
                            id={id ? `${id}-time` : undefined}
                            type="time"
                            value={selected ? format(selected, TIME_PART) : ''}
                            onChange={(e) => handleTimeChange(e.target.value)}
                        />
                    </div>
                </PopoverContent>
            </Popover>

            {selected && !disabled && (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="shrink-0"
                    onClick={() => onChange('')}
                    aria-label="Kosongkan tanggal"
                >
                    <X className="h-4 w-4" />
                </Button>
            )}
        </div>
    );
}
