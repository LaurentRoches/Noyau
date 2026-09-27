// src/steam.ts

/**
 * La part d'un client `steamworks.js` dont la coquille se sert, décrite ici
 * plutôt qu'importée : ce module est testé, et charger le vrai module natif
 * dans un test exigerait Steam (`06` §4.3).
 *
 * Décrite d'après le `client.d.ts` de la version **installée**, 0.4.0, et non
 * d'après la branche principale du dépôt : celle-ci expose `achievement.names()`,
 * que la 0.4.0 n'a pas. L'avoir lue sur la mauvaise version a coûté une
 * brique qui échouait à l'exécution avec des tests verts.
 */
export interface SteamClient {
  localplayer: { getName(): string };
  achievement: {
    activate(name: string): boolean;
    clear(name: string): boolean;
    isActivated(name: string): boolean;
  };
}

export interface SteamworksModule {
  init(appId: number): SteamClient;
}

export interface SteamOverlayModule {
  electronEnableSteamOverlay(): void;
}

export type SteamStatus =
  | { available: true; playerName: string; client: SteamClient }
  | { available: false; reason: string };

type Log = (message: string) => void;

function messageOf(error: unknown): string {
  return error instanceof Error ? error.message : String(error);
}

/**
 * Initialise Steam sans jamais lever.
 *
 * `load` est appelé ici et non par l'appelant, parce que le chargement peut
 * échouer à lui seul : un module natif absent, ou une DLL introuvable, font
 * échouer le `require` avant toute initialisation. Les deux échecs, et celui
 * d'un client Steam fermé, rendent le même statut : la fenêtre du jeu s'ouvre
 * quand même, Steam est simplement indisponible.
 */
export function initSteam(load: () => SteamworksModule, appId: number, log: Log): SteamStatus {
  try {
    const client = load().init(appId);
    const playerName = client.localplayer.getName();
    log(`Steam disponible, AppID ${appId}, joueur ${playerName}`);
    return { available: true, playerName, client };
  } catch (error) {
    const reason = messageOf(error);
    log(`Steam indisponible : ${reason}`);
    return { available: false, reason };
  }
}

/**
 * Déverrouille un succès et relit son état.
 *
 * `activate()` enregistre le succès puis appelle `store_stats()` lui-même.
 * La relecture par `isActivated()` distingue un succès accepté par l'API
 * d'un succès que Steam tient réellement pour débloqué.
 */
export function unlockAchievement(client: SteamClient, name: string, log: Log): void {
  try {
    if (!client.achievement.activate(name)) {
      log(`Succès ${name} : Steam a refusé le déverrouillage`);
      return;
    }
    const confirmed = client.achievement.isActivated(name) ? 'oui' : 'non';
    log(`Succès ${name} déverrouillé, confirmé par Steam : ${confirmed}`);
  } catch (error) {
    log(`Succès ${name} : échec Steam, ${messageOf(error)}`);
  }
}

/** Réinitialise un succès, pour rejouer le déverrouillage. */
export function clearAchievement(client: SteamClient, name: string, log: Log): void {
  try {
    log(
      client.achievement.clear(name)
        ? `Succès ${name} réinitialisé`
        : `Succès ${name} : Steam a refusé la réinitialisation`,
    );
  } catch (error) {
    log(`Succès ${name} : échec Steam, ${messageOf(error)}`);
  }
}

/**
 * Prépare Electron pour que l'overlay Steam s'y dessine et y réponde.
 *
 * `electronEnableSteamOverlay()` passe le GPU dans le processus principal
 * (`in-process-gpu`), désactive la composition directe, et force un redessin
 * de chaque fenêtre soixante fois par seconde. Sans lui, sur le build packagé
 * du 27/09/2026, la notification de succès s'affichait mais Shift+Tab
 * n'ouvrait rien. Laquelle des trois mesures compte n'a pas été isolée.
 *
 * Les options Chromium ne valent que si elles précèdent l'événement « ready » :
 * l'appelant doit l'invoquer avant.
 */
export function enableSteamOverlay(load: () => SteamOverlayModule, log: Log): void {
  try {
    load().electronEnableSteamOverlay();
    log('Overlay Steam préparé pour Electron');
  } catch (error) {
    log(`Overlay Steam non préparé : ${messageOf(error)}`);
  }
}
