import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatCurrency(amount: number, currency = 'IDR'): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency,
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

export function formatDate(date: string | Date, options?: Intl.DateTimeFormatOptions): string {
    const defaultOptions: Intl.DateTimeFormatOptions = {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        ...options,
    };
    return new Intl.DateTimeFormat('id-ID', defaultOptions).format(new Date(date));
}

export function formatDateTime(date: string | Date): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date));
}

export function formatTime(time: string): string {
    const [hours, minutes] = time.split(':');
    return `${hours}:${minutes}`;
}

export function getInitials(name: string): string {
    return name
        .split(' ')
        .map((word) => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

export function truncate(str: string, length: number): string {
    if (str.length <= length) return str;
    return str.slice(0, length) + '...';
}

export function debounce<T extends (...args: unknown[]) => unknown>(
    func: T,
    wait: number
): (...args: Parameters<T>) => void {
    let timeout: NodeJS.Timeout;
    return (...args: Parameters<T>) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
}

export function generateId(): string {
    return Math.random().toString(36).substring(2, 9);
}

export const statusColors: Record<string, string> = {
    active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    inactive: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    suspended: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    unpaid: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    partial: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    overdue: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    present: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    absent: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    late: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    sick: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    permitted: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
};

export function getStatusColor(status: string): string {
    return statusColors[status.toLowerCase()] || statusColors.inactive;
}
