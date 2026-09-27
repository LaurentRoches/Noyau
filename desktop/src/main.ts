// src/main.ts
// Point d'entrée du processus principal Electron.
//
// Câblage seul : pas de test unitaire, par convention (`06` §4.3). La seule
// logique qu'il porte, la traduction d'une URL en fichier, vit dans
// bundlePath.ts, qui est testé. Ce fichier est vérifié par le protocole
// manuel sur build packagé.
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { app, BrowserWindow, net, protocol } from 'electron';
import { resolveBundlePath } from './bundlePath';

const SCHEME = 'app';
const ENTRY_URL = `${SCHEME}://bundle/index.html`;

// Doit précéder l'événement « ready ».
// standard : les chemins absolus du build Vite (/assets/...) se résolvent
//            contre app://bundle, comme sous http.
// secure : contexte sécurisé, comme https.
// supportFetchAPI : fetch() passe par le gestionnaire ci-dessous.
// stream : lecture progressive, pour la vidéo des Vestiges.
protocol.registerSchemesAsPrivileged([
  {
    scheme: SCHEME,
    privileges: { standard: true, secure: true, supportFetchAPI: true, stream: true },
  },
]);

// Build du frontend : copié dans les ressources par electron-builder une fois
// packagé, lu directement dans frontend/dist en développement.
function frontendRoot(): string {
  return app.isPackaged
    ? path.join(process.resourcesPath, 'frontend')
    : path.join(__dirname, '..', '..', 'frontend', 'dist');
}

function serveBundle(root: string): void {
  protocol.handle(SCHEME, (request) => {
    const file = resolveBundlePath(root, request.url);
    if (file === null) {
      return new Response(null, { status: 404 });
    }
    return net.fetch(pathToFileURL(file).toString());
  });
}

function createWindow(): BrowserWindow {
  return new BrowserWindow({
    width: 1280,
    height: 800,
    title: 'Corebound',
    webPreferences: {
      // Ce sont les valeurs par défaut d'Electron, écrites quand même : ce sont
      // des décisions du chantier 1b, et une décision implicite se défait sans bruit.
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
    },
  });
}

app.whenReady().then(() => {
  serveBundle(frontendRoot());
  return createWindow().loadURL(ENTRY_URL);
});

// Cible Windows : fermer la dernière fenêtre termine l'application.
app.on('window-all-closed', () => app.quit());
