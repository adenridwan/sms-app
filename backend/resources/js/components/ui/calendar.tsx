import { ChevronLeft, ChevronRight } from 'lucide-react';
import { DayPicker, type DayPickerProps } from 'react-day-picker';
import { id as localeId } from 'date-fns/locale';

import { cn } from '@/lib/utils';
import { buttonVariants } from '@/components/ui/button';

/**
 * Kalender berbasis react-day-picker v9 (sudah terpasang di package.json,
 * sebelumnya belum pernah dipakai). Seluruh gaya diberikan lewat `classNames`
 * memakai token tema aplikasi — stylesheet bawaan react-day-picker sengaja
 * tidak diimpor supaya tidak bentrok dengan Tailwind.
 */
export type CalendarProps = DayPickerProps & {
    className?: string;
};

function Calendar({ className, classNames, showOutsideDays = true, ...props }: CalendarProps) {
    return (
        <DayPicker
            locale={localeId}
            showOutsideDays={showOutsideDays}
            className={cn('p-0', className)}
            classNames={{
                months: 'flex flex-col gap-4',
                month: 'flex flex-col gap-3',
                month_caption: 'flex h-9 items-center justify-center px-9',
                caption_label: 'text-sm font-medium',
                dropdowns: 'flex items-center justify-center gap-2 text-sm font-medium',
                dropdown_root: 'relative',
                dropdown:
                    'h-8 rounded-md border border-input bg-background px-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                nav: 'absolute inset-x-0 top-0 flex h-9 items-center justify-between px-1',
                button_previous: cn(
                    buttonVariants({ variant: 'outline' }),
                    'h-7 w-7 bg-transparent p-0 opacity-60 hover:opacity-100'
                ),
                button_next: cn(
                    buttonVariants({ variant: 'outline' }),
                    'h-7 w-7 bg-transparent p-0 opacity-60 hover:opacity-100'
                ),
                month_grid: 'w-full border-collapse',
                weekdays: 'flex',
                weekday: 'w-9 text-[0.8rem] font-normal text-muted-foreground',
                week: 'mt-1 flex w-full',
                day: 'h-9 w-9 p-0 text-center text-sm',
                day_button: cn(
                    buttonVariants({ variant: 'ghost' }),
                    'h-9 w-9 rounded-md p-0 font-normal aria-selected:opacity-100'
                ),
                selected:
                    '[&>button]:bg-primary [&>button]:text-primary-foreground [&>button:hover]:bg-primary [&>button:hover]:text-primary-foreground',
                today: '[&>button]:bg-accent [&>button]:text-accent-foreground',
                outside: '[&>button]:text-muted-foreground [&>button]:opacity-50',
                disabled: '[&>button]:text-muted-foreground [&>button]:opacity-50',
                hidden: 'invisible',
                ...classNames,
            }}
            components={{
                Chevron: ({ orientation, ...chevronProps }) =>
                    orientation === 'left' ? (
                        <ChevronLeft className="h-4 w-4" {...chevronProps} />
                    ) : (
                        <ChevronRight className="h-4 w-4" {...chevronProps} />
                    ),
            }}
            {...props}
        />
    );
}
Calendar.displayName = 'Calendar';

export { Calendar };
