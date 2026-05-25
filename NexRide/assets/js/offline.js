// NexRide - Offline-First Support
// IndexedDB for local data storage + Service Worker registration
(function() {
    'use strict';

    const DB_NAME = 'nexride_offline';
    const DB_VERSION = 1;
    const STORES = ['billets', 'voyages', 'bordereaux', 'clients', 'sync_queue'];
    let db = null;

    // ===== IndexedDB =====
    function openDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            request.onupgradeneeded = (e) => {
                const database = e.target.result;
                STORES.forEach(store => {
                    if (!database.objectStoreNames.contains(store)) {
                        const os = database.createObjectStore(store, { keyPath: 'id' });
                        os.createIndex('offline_id', 'offline_id', { unique: false });
                        os.createIndex('synced', 'synced', { unique: false });
                    }
                });
            };
            request.onsuccess = (e) => { db = e.target.result; resolve(db); };
            request.onerror = (e) => reject(e.target.error);
        });
    }

    function saveLocal(store, data) {
        return new Promise((resolve, reject) => {
            const tx = db.transaction(store, 'readwrite');
            tx.objectStore(store).put(data);
            tx.oncomplete = () => resolve(data);
            tx.onerror = (e) => reject(e.target.error);
        });
    }

    function getLocal(store, id) {
        return new Promise((resolve, reject) => {
            const tx = db.transaction(store, 'readonly');
            const request = tx.objectStore(store).get(id);
            request.onsuccess = () => resolve(request.result);
            request.onerror = (e) => reject(e.target.error);
        });
    }

    function getAllLocal(store) {
        return new Promise((resolve, reject) => {
            const tx = db.transaction(store, 'readonly');
            const request = tx.objectStore(store).getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = (e) => reject(e.target.error);
        });
    }

    function deleteLocal(store, id) {
        return new Promise((resolve, reject) => {
            const tx = db.transaction(store, 'readwrite');
            tx.objectStore(store).delete(id);
            tx.oncomplete = () => resolve();
            tx.onerror = (e) => reject(e.target.error);
        });
    }

    // ===== Sync Queue =====
    function addToSyncQueue(table, action, data) {
        const offlineId = 'offline_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        data.offline_id = offlineId;
        data.synced = 0;

        saveLocal('sync_queue', {
            id: offlineId,
            table_name: table,
            action: action,
            data: data,
            created_at: new Date().toISOString(),
            attempts: 0
        });

        return offlineId;
    }

    async function processSyncQueue() {
        if (!navigator.onLine) return;

        const queue = await getAllLocal('sync_queue');
        const pending = queue.filter(item => item.attempts < 5);

        for (const item of pending) {
            try {
                const response = await fetch(BASE_URL + '/api/sync', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: item.action,
                        table: item.table_name,
                        offline_id: item.id,
                        data: item.data
                    })
                });

                if (response.ok) {
                    await deleteLocal('sync_queue', item.id);
                    // Update local data to mark as synced
                    if (item.table_name && item.data) {
                        const localData = item.data;
                        localData.synced = 1;
                        await saveLocal(item.table_name, localData);
                    }
                    console.log('Synced:', item.id);
                } else {
                    item.attempts++;
                    await saveLocal('sync_queue', item);
                }
            } catch (error) {
                console.error('Sync error:', error);
                item.attempts++;
                await saveLocal('sync_queue', item);
            }
        }
    }

    // ===== Online/Offline Handlers =====
    function setupOfflineHandlers() {
        window.addEventListener('online', () => {
            console.log('Back online - syncing...');
            processSyncQueue();
        });

        window.addEventListener('offline', () => {
            console.log('Gone offline - data will be stored locally');
        });

        // Auto-sync every 30 seconds when online
        setInterval(() => {
            if (navigator.onLine) {
                processSyncQueue();
            }
        }, 30000);
    }

    // ===== Service Worker Registration =====
    function registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register(BASE_URL + '/public/sw.js')
                .then(reg => console.log('SW registered:', reg.scope))
                .catch(err => console.error('SW registration failed:', err));
        }
    }

    // ===== Initialize =====
    async function init() {
        try {
            await openDB();
            registerServiceWorker();
            setupOfflineHandlers();
            console.log('NexRide Offline initialized');
        } catch (error) {
            console.error('Offline init error:', error);
        }
    }

    // Expose for use in other scripts
    window.NexRideOffline = {
        init,
        saveLocal,
        getLocal,
        getAllLocal,
        deleteLocal,
        addToSyncQueue,
        processSyncQueue
    };

    document.addEventListener('DOMContentLoaded', init);
})();