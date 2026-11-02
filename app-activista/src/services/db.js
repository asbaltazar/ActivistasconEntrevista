import Dexie from 'dexie';

export const db = new Dexie('RctActivistaOfflineDB');

// Estados de la cola (RF-A5): borrador -> pendiente -> enviando -> sincronizado | error
db.version(1).stores({
  talentsQueue: 'uuid, status, clave_elector, curp, telefono, seccion, updatedAt',
  catalogs: 'key',
});

/**
 * Solicita persistencia de almacenamiento para evitar que el navegador desaloje IndexedDB (RF-A5).
 */
export async function requestPersistentStorage() {
  if (typeof navigator !== 'undefined' && navigator.storage && navigator.storage.persist) {
    try {
      return await navigator.storage.persist();
    } catch {
      return false;
    }
  }
  return false;
}
