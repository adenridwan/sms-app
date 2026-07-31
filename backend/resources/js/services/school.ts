import api from './api';
import type { ApiResponse } from '@/types';

export interface SchoolProfile {
    id: string;
    name: string;
    npsn: string | null;
    level: string | null;
    email: string;
    phone: string | null;
    address: string | null;
    logo_url: string | null;
}

export interface SchoolListItem {
    id: string;
    name: string;
    npsn: string | null;
    level: string | null;
    status: string;
    logo_url: string | null;
}

export const schoolApi = {
    get: () => api.get<ApiResponse<SchoolProfile>>('/settings/school'),

    /** Update memakai multipart (POST) karena membawa berkas logo. */
    update: (data: FormData) =>
        api.post<ApiResponse<SchoolProfile>>('/settings/school', data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),

    /** Tambah sekolah baru (khusus super admin). Multipart untuk logo opsional. */
    create: (data: FormData) =>
        api.post<ApiResponse<SchoolProfile>>('/admin/schools', data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),

    /** Daftar semua sekolah (khusus super admin). */
    list: () => api.get<ApiResponse<SchoolListItem[]>>('/admin/schools'),

    activate: (id: string) => api.post<ApiResponse>(`/admin/schools/${id}/activate`),

    deactivate: (id: string) => api.post<ApiResponse>(`/admin/schools/${id}/deactivate`),

    remove: (id: string) => api.delete<ApiResponse>(`/admin/schools/${id}`),
};
