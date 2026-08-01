import api from './api';
import type { ApiResponse } from '@/types';

export interface MenuMatrixRole {
    name: string;
    label: string;
}

export interface MenuMatrixChild {
    key: string;
    title: string;
}

export interface MenuMatrixGroup {
    key: string;
    title: string;
    children: MenuMatrixChild[];
}

export interface MenuCell {
    visible: boolean;
    locked: boolean;
}

export interface MenuMatrix {
    roles: MenuMatrixRole[];
    tree: MenuMatrixGroup[];
    /** cells[role][menu_key] */
    cells: Record<string, Record<string, MenuCell>>;
}

export const menuSettingsApi = {
    get: () => api.get<ApiResponse<MenuMatrix>>('/settings/menu'),

    /** hidden[role] = daftar menu_key yang disembunyikan */
    update: (hidden: Record<string, string[]>) =>
        api.put<ApiResponse<null>>('/settings/menu', { hidden }),
};
