// src/main.ts
// Point d'entrée du processus principal Electron.
//
// Câblage seul, sans logique : pas de test unitaire, par convention.
// Il est vérifié par le protocole manuel sur build packagé.
import { app, BrowserWindow } from 'electron';

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

app.whenReady().then(createWindow);

// Cible Windows : fermer la dernière fenêtre termine l'application.
app.on('window-all-closed', () => app.quit());
