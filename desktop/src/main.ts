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
import { app, BrowserWindow, Menu, MenuItem, net, protocol } from 'electron';
import type * as Steamworks from 'steamworks.js';
import { resolveBundlePath } from './bundlePath';
import {
  clearAchievement,
  enableSteamOverlay,
  initSteam,
  unlockAchievement,
  type SteamOverlayModule,
  type SteamStatus,
  type SteamworksModule,
} from './steam';

const SCHEME = 'app';
const ENTRY_URL = `${SCHEME}://bundle/index.html`;

// Spacewar, l'application de test de Valve (`07`, chantier 1b). Passé à
// l'initialisation plutôt qu'écrit dans steam_appid.txt : c'est l'expérience
// que cette brique tranche (`04` §4.5).
const STEAM_APP_ID = 480;

// « Winner », premier succès de Spacewar, nommé dans le guide pas à pas des
// succès de la documentation Steamworks. La 0.4.0 de steamworks.js ne sait pas
// lister les succès : le nom est écrit ici, pas lu.
const TEST_ACHIEVEMENT = 'ACH_WIN_ONE_GAME';

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
//
// Le type réel du module est importé — `import type`, effacé à la compilation,
// donc sans chargement — et le retour n'est pas forcé par un `as` : si
// SteamworksModule décrit une fonction que la version installée n'a pas, le
// typecheck échoue ici. C'est ce qui a manqué quand `names()` y figurait.
function loadSteamworks(): SteamworksModule & SteamOverlayModule {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const steamworks: typeof Steamworks = require('steamworks.js');
  return steamworks;
}

// Menu de test du chantier 1b, ajouté au menu par défaut d'Electron sans le
// remplacer : le retrait de celui-ci n'est pas décidé (`04` §4.5). Les entrées
// restent visibles mais désactivées quand Steam est indisponible.
function addSteamMenu(steam: SteamStatus): void {
  const client = steam.available ? steam.client : null;
  const menu = Menu.getApplicationMenu() ?? new Menu();
  menu.append(
    new MenuItem({
      label: 'Steam',
      submenu: [
        {
          label: 'Déverrouiller un succès de test',
          enabled: client !== null,
          click: () => client && unlockAchievement(client, TEST_ACHIEVEMENT, logLine),
        },
        {
          label: 'Réinitialiser le succès de test',
          enabled: client !== null,
          click: () => client && clearAchievement(client, TEST_ACHIEVEMENT, logLine),
        },
      ],
    }),
  );
  Menu.setApplicationMenu(menu);
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

// Steam est initialisé avant « ready », pour deux raisons. Les options
// Chromium de l'overlay ne valent que posées avant, et on ne les pose que si
// Steam répond : sans lui, rien ne justifie de passer le GPU dans le processus
// principal. Et l'overlay s'accroche au rendu quand celui-ci démarre, ce qui
// suppose Steam déjà initialisé.
const steam = initSteam(loadSteamworks, STEAM_APP_ID, logLine);
if (steam.available) {
  enableSteamOverlay(loadSteamworks, logLine);
}

app.whenReady().then(() => {
  addSteamMenu(steam);
  serveBundle(frontendRoot());
  return createWindow().loadURL(ENTRY_URL);
});

// Cible Windows : fermer la dernière fenêtre termine l'application.
app.on('window-all-closed', () => app.quit());
