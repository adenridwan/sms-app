import { useState, useCallback, useEffect } from 'react';
import { toast } from 'sonner';
import { scannerApi } from '@/services/attendance';
import type { OfflineScan, ScanType } from '@/types/attendance';

const DB_NAME = 'attendance_offline_db';
const DB_VERSION = 1;
const STORE_NAME = 'offline_scans';

interface QueuedScan {
    unique_code: string;
    waktu: ScanType;
    scanned_at: string;
    latitude?: number;
    longitude?: number;
}

let dbInstance: IDBDatabase | null = null;

const openDB = (): Promise<IDBDatabase> => {
    return new Promise((resolve, reject) => {
        if (dbInstance) {
            resolve(dbInstance);
            return;
        }

        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onerror = () => {
            reject(new Error('Failed to open IndexedDB'));
        };

        request.onsuccess = () => {
            dbInstance = request.result;
            resolve(dbInstance);
        };

        request.onupgradeneeded = (event) => {
            const db = (event.target as IDBOpenDBRequest).result;

            if (!db.objectStoreNames.contains(STORE_NAME)) {
                const store = db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
                store.createIndex('synced', 'synced', { unique: false });
                store.createIndex('scanned_at', 'scanned_at', { unique: false });
            }
        };
    });
};

const addToQueue = async (scan: QueuedScan): Promise<void> => {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([STORE_NAME], 'readwrite');
        const store = transaction.objectStore(STORE_NAME);

        const data: Omit<OfflineScan, 'id'> & { id?: string } = {
            ...scan,
            id: `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
            scan_type: scan.waktu,
            synced: false,
        };

        const request = store.add(data);

        request.onerror = () => reject(new Error('Failed to add scan to queue'));
        request.onsuccess = () => resolve();
    });
};

const getPendingScans = async (): Promise<OfflineScan[]> => {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([STORE_NAME], 'readonly');
        const store = transaction.objectStore(STORE_NAME);
        const index = store.index('synced');
        const request = index.getAll(IDBKeyRange.only(false));

        request.onerror = () => reject(new Error('Failed to get pending scans'));
        request.onsuccess = () => resolve(request.result);
    });
};

const markAsSynced = async (ids: string[]): Promise<void> => {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([STORE_NAME], 'readwrite');
        const store = transaction.objectStore(STORE_NAME);

        let completed = 0;
        let hasError = false;

        ids.forEach((id) => {
            const getRequest = store.get(id);

            getRequest.onsuccess = () => {
                if (getRequest.result) {
                    const data = getRequest.result;
                    data.synced = true;
                    const updateRequest = store.put(data);

                    updateRequest.onsuccess = () => {
                        completed++;
                        if (completed === ids.length && !hasError) {
                            resolve();
                        }
                    };

                    updateRequest.onerror = () => {
                        hasError = true;
                        reject(new Error('Failed to mark scan as synced'));
                    };
                } else {
                    completed++;
                    if (completed === ids.length && !hasError) {
                        resolve();
                    }
                }
            };

            getRequest.onerror = () => {
                hasError = true;
                reject(new Error('Failed to get scan for update'));
            };
        });
    });
};

const deleteSynced = async (): Promise<void> => {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([STORE_NAME], 'readwrite');
        const store = transaction.objectStore(STORE_NAME);
        const index = store.index('synced');
        const request = index.openCursor(IDBKeyRange.only(true));

        request.onerror = () => reject(new Error('Failed to delete synced scans'));

        request.onsuccess = () => {
            const cursor = request.result;
            if (cursor) {
                cursor.delete();
                cursor.continue();
            } else {
                resolve();
            }
        };
    });
};

export function useOfflineQueue() {
    const [pendingCount, setPendingCount] = useState(0);
    const [syncing, setSyncing] = useState(false);

    const refreshPendingCount = useCallback(async () => {
        try {
            const pending = await getPendingScans();
            setPendingCount(pending.length);
        } catch (error) {
            console.error('Failed to get pending count:', error);
        }
    }, []);

    useEffect(() => {
        refreshPendingCount();
    }, [refreshPendingCount]);

    const queueScan = useCallback(async (scan: QueuedScan): Promise<void> => {
        await addToQueue(scan);
        await refreshPendingCount();
    }, [refreshPendingCount]);

    const syncQueue = useCallback(async (): Promise<void> => {
        if (syncing || !navigator.onLine) {
            return;
        }

        setSyncing(true);

        try {
            const pending = await getPendingScans();

            if (pending.length === 0) {
                toast.info('Tidak ada scan yang perlu disinkronkan');
                return;
            }

            const scansToSync = pending.map((scan) => ({
                unique_code: scan.unique_code,
                waktu: scan.scan_type,
                scanned_at: scan.scanned_at,
                latitude: scan.latitude,
                longitude: scan.longitude,
            }));

            const response = await scannerApi.syncOffline(scansToSync);
            const result = response.data.data;

            if (result) {
                // Mark successful syncs
                const successfulIds = result.results
                    .filter((r) => r.success)
                    .map((r) => {
                        const matchingScan = pending.find((p) => p.unique_code === r.unique_code);
                        return matchingScan?.id;
                    })
                    .filter(Boolean) as string[];

                if (successfulIds.length > 0) {
                    await markAsSynced(successfulIds);
                }

                // Delete synced records
                await deleteSynced();

                if (result.failed > 0) {
                    toast.warning(
                        `Berhasil: ${result.success}, Gagal: ${result.failed}`,
                        { description: 'Beberapa scan gagal disinkronkan' }
                    );
                } else {
                    toast.success(`${result.success} scan berhasil disinkronkan`);
                }
            }
        } catch (error) {
            console.error('Sync failed:', error);
            toast.error('Gagal menyinkronkan scan');
        } finally {
            setSyncing(false);
            await refreshPendingCount();
        }
    }, [syncing, refreshPendingCount]);

    return {
        queueScan,
        pendingCount,
        syncQueue,
        syncing,
        refreshPendingCount,
    };
}

export default useOfflineQueue;
