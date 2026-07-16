import { useState, useCallback, useEffect } from 'react';
import type { LookupResult } from '@/types/attendance';

const DB_NAME = 'attendance_roster_db';
const DB_VERSION = 1;
const STORE_NAME = 'roster';
const METADATA_STORE = 'metadata';
const CACHE_DURATION = 24 * 60 * 60 * 1000; // 24 hours in milliseconds

interface RosterEntry extends LookupResult {
    unique_code: string;
    cached_at: number;
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
                const store = db.createObjectStore(STORE_NAME, { keyPath: 'unique_code' });
                store.createIndex('type', 'type', { unique: false });
                store.createIndex('cached_at', 'cached_at', { unique: false });
            }

            if (!db.objectStoreNames.contains(METADATA_STORE)) {
                db.createObjectStore(METADATA_STORE, { keyPath: 'key' });
            }
        };
    });
};

const getMetadata = async (key: string): Promise<string | number | null> => {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([METADATA_STORE], 'readonly');
        const store = transaction.objectStore(METADATA_STORE);
        const request = store.get(key);

        request.onerror = () => reject(new Error('Failed to get metadata'));
        request.onsuccess = () => {
            resolve(request.result?.value ?? null);
        };
    });
};

const setMetadata = async (key: string, value: string | number): Promise<void> => {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([METADATA_STORE], 'readwrite');
        const store = transaction.objectStore(METADATA_STORE);
        const request = store.put({ key, value });

        request.onerror = () => reject(new Error('Failed to set metadata'));
        request.onsuccess = () => resolve();
    });
};

export function useOfflineRoster() {
    const [isLoaded, setIsLoaded] = useState(false);
    const [lastUpdated, setLastUpdated] = useState<Date | null>(null);
    const [count, setCount] = useState(0);

    const refreshMetadata = useCallback(async () => {
        try {
            const timestamp = await getMetadata('last_updated');
            if (timestamp && typeof timestamp === 'number') {
                setLastUpdated(new Date(timestamp));
            }

            const db = await openDB();
            const transaction = db.transaction([STORE_NAME], 'readonly');
            const store = transaction.objectStore(STORE_NAME);
            const countRequest = store.count();

            countRequest.onsuccess = () => {
                setCount(countRequest.result);
            };
        } catch (error) {
            console.error('Failed to refresh metadata:', error);
        }
    }, []);

    useEffect(() => {
        refreshMetadata().then(() => setIsLoaded(true));
    }, [refreshMetadata]);

    const lookup = useCallback(async (uniqueCode: string): Promise<LookupResult | null> => {
        try {
            const db = await openDB();
            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAME], 'readonly');
                const store = transaction.objectStore(STORE_NAME);
                const request = store.get(uniqueCode);

                request.onerror = () => reject(new Error('Failed to lookup'));
                request.onsuccess = () => {
                    const result = request.result as RosterEntry | undefined;
                    if (result) {
                        // Check if cache is still valid
                        const now = Date.now();
                        if (now - result.cached_at < CACHE_DURATION) {
                            const { unique_code, cached_at, ...lookupResult } = result;
                            resolve(lookupResult);
                        } else {
                            resolve(null); // Cache expired
                        }
                    } else {
                        resolve(null);
                    }
                };
            });
        } catch (error) {
            console.error('Lookup failed:', error);
            return null;
        }
    }, []);

    const cacheEntry = useCallback(async (uniqueCode: string, data: LookupResult): Promise<void> => {
        try {
            const db = await openDB();
            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAME], 'readwrite');
                const store = transaction.objectStore(STORE_NAME);

                const entry: RosterEntry = {
                    ...data,
                    unique_code: uniqueCode,
                    cached_at: Date.now(),
                };

                const request = store.put(entry);

                request.onerror = () => reject(new Error('Failed to cache entry'));
                request.onsuccess = () => {
                    refreshMetadata();
                    resolve();
                };
            });
        } catch (error) {
            console.error('Cache entry failed:', error);
        }
    }, [refreshMetadata]);

    const bulkCache = useCallback(async (entries: Array<{ uniqueCode: string; data: LookupResult }>): Promise<void> => {
        try {
            const db = await openDB();
            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAME], 'readwrite');
                const store = transaction.objectStore(STORE_NAME);
                const now = Date.now();

                let completed = 0;
                let hasError = false;

                entries.forEach(({ uniqueCode, data }) => {
                    const entry: RosterEntry = {
                        ...data,
                        unique_code: uniqueCode,
                        cached_at: now,
                    };

                    const request = store.put(entry);

                    request.onsuccess = () => {
                        completed++;
                        if (completed === entries.length && !hasError) {
                            setMetadata('last_updated', now)
                                .then(() => refreshMetadata())
                                .then(() => resolve());
                        }
                    };

                    request.onerror = () => {
                        hasError = true;
                        reject(new Error('Failed to bulk cache'));
                    };
                });

                if (entries.length === 0) {
                    resolve();
                }
            });
        } catch (error) {
            console.error('Bulk cache failed:', error);
        }
    }, [refreshMetadata]);

    const clearCache = useCallback(async (): Promise<void> => {
        try {
            const db = await openDB();
            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAME, METADATA_STORE], 'readwrite');

                const rosterStore = transaction.objectStore(STORE_NAME);
                const metadataStore = transaction.objectStore(METADATA_STORE);

                rosterStore.clear();
                metadataStore.clear();

                transaction.oncomplete = () => {
                    setLastUpdated(null);
                    setCount(0);
                    resolve();
                };

                transaction.onerror = () => {
                    reject(new Error('Failed to clear cache'));
                };
            });
        } catch (error) {
            console.error('Clear cache failed:', error);
        }
    }, []);

    const clearExpired = useCallback(async (): Promise<number> => {
        try {
            const db = await openDB();
            const cutoff = Date.now() - CACHE_DURATION;

            return new Promise((resolve, reject) => {
                const transaction = db.transaction([STORE_NAME], 'readwrite');
                const store = transaction.objectStore(STORE_NAME);
                const index = store.index('cached_at');
                const range = IDBKeyRange.upperBound(cutoff);
                const request = index.openCursor(range);

                let deleted = 0;

                request.onerror = () => reject(new Error('Failed to clear expired'));

                request.onsuccess = () => {
                    const cursor = request.result;
                    if (cursor) {
                        cursor.delete();
                        deleted++;
                        cursor.continue();
                    } else {
                        refreshMetadata();
                        resolve(deleted);
                    }
                };
            });
        } catch (error) {
            console.error('Clear expired failed:', error);
            return 0;
        }
    }, [refreshMetadata]);

    return {
        isLoaded,
        lastUpdated,
        count,
        lookup,
        cacheEntry,
        bulkCache,
        clearCache,
        clearExpired,
    };
}

export default useOfflineRoster;
