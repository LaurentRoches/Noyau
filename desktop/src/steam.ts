// src/steam.ts

/**
 * La part de `steamworks.js` dont la coquille se sert, décrite ici plutôt
 * qu'importée : ce module est testé, et charger le vrai module natif dans un
 * test exigerait Steam (`06` §4.3). L'interface grandira avec les briques
 * suivantes (achievement, overlay), pas avant.
 */
export interface SteamworksModule {
  init(appId: number): { localplayer: { getName(): string } };
}

export type SteamStatus =
  { available: true; playerName: string } | { available: false; reason: string };

/**
 * Initialise Steam sans jamais lever.
 *
 * `load` est appelé ici et non par l'appelant, parce que le chargement peut
 * échouer à lui seul : un module natif absent, ou une DLL introuvable, font
 * échouer le `require` avant toute initialisation. Les deux échecs, et celui
 * d'un client Steam fermé, rendent le même statut : la fenêtre du jeu s'ouvre
 * quand même, Steam est simplement indisponible.
 */
export function initSteam(
  load: () => SteamworksModule,
  appId: number,
  log: (message: string) => void,
): SteamStatus {
  try {
    const client = load().init(appId);
    const playerName = client.localplayer.getName();
    log(`Steam disponible, AppID ${appId}, joueur ${playerName}`);
    return { available: true, playerName };
  } catch (error) {
    const reason = error instanceof Error ? error.message : String(error);
    log(`Steam indisponible : ${reason}`);
    return { available: false, reason };
  }
}
