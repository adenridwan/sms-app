import { format, isValid, parse } from 'date-fns';
import { id as localeId } from 'date-fns/locale';
import { CalendarIcon } from 'lucide-react';

import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

/** Format yang dipakai API & database (kolom `date` Postgres). */
const VALUE_FORMAT = 'yyyy-MM-dd';

/**
 * `parse` (bukan `new Date(value)`) supaya "1990-05-17" dibaca sebagai
 * tanggal lokal, bukan UTC — kalau tidak, zona waktu Indonesia menggeser
 * tampilannya mundur satu hari.
 */
function parseValue(value?: string | null): Date | undefined {
    if (!value) return undefined;

    const parsed = parse(value, VALUE_FORMAT, new Date());

    return isValid(parsed) ? parsed : undefined;
}

interface DatePickerProps {
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
 * Pemilih tanggal dengan kalender + dropdown bulan/tahun, pengganti
 * `<input type="date">` yang ikon kalendernya nyaris tak terlihat pada tema
 * gelap. Nilai masuk/keluar tetap string `yyyy-MM-dd` supaya pemakaiannya
 * sama persis dengan input sebelumnya.
 */
export function DatePicker({
    id,
    value,
    onChange,
    placeholder = 'Pilih tanggal',
    disabled,
    invalid,
    className,
    fromYear = new Date().getFullYear() - 80,
    toYear = new Date().getFullYear() + 5,
}: DatePickerProps) {
    const selected = parseValue(value);

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    disabled={disabled}
                    className={cn(
                        'w-full justify-start px-3 font-normal',
                        !selected && 'text-muted-foreground',
                        invalid && 'border-destructive',
                        className
                    )}
                >
                    <CalendarIcon className="mr-2 h-4 w-4 shrink-0 opacity-70" />
                    {selected ? format(selected, 'd MMMM yyyy', { locale: localeId }) : placeholder}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-3">
                <Calendar
                    mode="single"
                    selected={selected}
                    defaultMonth={selected}
                    captionLayout="dropdown"
                    startMonth={new Date(fromYear, 0)}
                    endMonth={new Date(toYear, 11)}
                    onSelect={(date) => onChange(date ? format(date, VALUE_FORMAT) : '')}
                />
            </PopoverContent>
        </Popover>
    );
}
