// src/main.ts
// Point d'entrée du processus principal Electron.
//
// Câblage seul : pas de test unitaire, par convention (`06` §4.3). La logique
// vit dans des modules testés — la traduction d'une URL en fichier dans
// bundlePath.ts, l'initialisation de Steam dans steam.ts. Ce fichier est
// vérifié par le protocole manuel sur build packagé.
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { app, BrowserWindow, net, protocol } from 'electron';
import { resolveBundlePath } from './bundlePath';
import { initSteam, type SteamworksModule } from './steam';

const SCHEME = 'app';
const ENTRY_URL = `${SCHEME}://bundle/index.html`;

// Spacewar, l'application de test de Valve (`07`, chantier 1b). Passé à
// l'initialisation plutôt qu'écrit dans steam_appid.txt : c'est l'expérience
// que cette brique tranche (`04` §4.5).
const STEAM_APP_ID = 480;

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

// Journal dans le répertoire de journaux de l'OS (%APPDATA%\Corebound\logs
// sous Windows), jamais à côté de l'exécutable (`06` §8). C'est la trace du
// protocole manuel : un build packagé n'a pas de terminal.
function logLine(message: string): void {
  const line = `${new Date().toISOString()} ${message}`;
  console.log(line);
  try {
    const dir = app.getPath('logs');
    fs.mkdirSync(dir, { recursive: true });
    fs.appendFileSync(path.join(dir, 'corebound.log'), `${line}\n`);
  } catch {
    // Le journal ne doit jamais empêcher le jeu de démarrer.
  }
}

// Chargé ici, à la demande, et non en tête de fichier : un module natif
// introuvable ferait sinon planter la coquille avant la première fenêtre.
function loadSteamworks(): SteamworksModule {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  return require('steamworks.js') as SteamworksModule;
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
  initSteam(loadSteamworks, STEAM_APP_ID, logLine);
  serveBundle(frontendRoot());
  return createWindow().loadURL(ENTRY_URL);
});

// Cible Windows : fermer la dernière fenêtre termine l'application.
app.on('window-all-closed', () => app.quit());
