// src/steam.test.ts
import { describe, expect, it, vi } from 'vitest';
import {
  clearAchievement,
  initSteam,
  unlockAchievement,
  type SteamClient,
  type SteamworksModule,
} from './steam';

const SPACEWAR = 480;

function fakeClient(accepts = true): SteamClient {
  return {
    localplayer: { getName: () => 'Kagelestis' },
    achievement: {
      activate: vi.fn(() => accepts),
      clear: vi.fn(() => accepts),
      isActivated: vi.fn(() => accepts),
    },
  };
}

describe('initSteam', () => {
  it('reports Steam unavailable and logs why when the client is not running', () => {
    const log = vi.fn();
    const steamworks: SteamworksModule = {
      init: () => {
        throw new Error('Steam is not running');
      },
    };

    const status = initSteam(() => steamworks, SPACEWAR, log);

    expect(status).toEqual({ available: false, reason: 'Steam is not running' });
    expect(log).toHaveBeenCalledWith('Steam indisponible : Steam is not running');
  });

  // Un module natif absent ou une DLL introuvable font échouer le require
  // lui-même, avant toute initialisation. L'échec doit rester contrôlé.
  it('reports Steam unavailable when the native module cannot be loaded', () => {
    const log = vi.fn();

    const status = initSteam(
      () => {
        throw new Error('Cannot find module steamworks.js');
      },
      SPACEWAR,
      log,
    );

    expect(status).toEqual({ available: false, reason: 'Cannot find module steamworks.js' });
    expect(log).toHaveBeenCalledWith('Steam indisponible : Cannot find module steamworks.js');
  });

  // Un AppID autre que 480, pour qu'une valeur écrite en dur fasse échouer ce test.
  it('passes the AppID it is given to the initialisation', () => {
    const init = vi.fn(() => fakeClient());

    initSteam(() => ({ init }), 4000, vi.fn());

    expect(init).toHaveBeenCalledWith(4000);
  });

  it('reports Steam available with the player name and the client when initialisation succeeds', () => {
    const log = vi.fn();
    const client = fakeClient();

    const status = initSteam(() => ({ init: () => client }), SPACEWAR, log);

    expect(status).toEqual({ available: true, playerName: 'Kagelestis', client });
    expect(log).toHaveBeenCalledWith('Steam disponible, AppID 480, joueur Kagelestis');
  });
});

// Un nom qui n'est pas celui de Spacewar, pour qu'un nom écrit en dur dans
// le module fasse échouer ces tests.
const ACHIEVEMENT = 'ACH_TEST';

describe('unlockAchievement', () => {
  it('activates the achievement it is given and logs that Steam confirms it', () => {
    const log = vi.fn();
    const client = fakeClient();

    unlockAchievement(client, ACHIEVEMENT, log);

    expect(client.achievement.activate).toHaveBeenCalledWith(ACHIEVEMENT);
    expect(log).toHaveBeenCalledWith('Succès ACH_TEST déverrouillé, confirmé par Steam : oui');
  });

  it('logs a refusal when Steam does not store the achievement', () => {
    const log = vi.fn();

    unlockAchievement(fakeClient(false), ACHIEVEMENT, log);

    expect(log).toHaveBeenCalledWith('Succès ACH_TEST : Steam a refusé le déverrouillage');
  });

  it('logs instead of throwing when the Steam call fails', () => {
    const log = vi.fn();
    const client = fakeClient();
    client.achievement.activate = () => {
      throw new Error('Steam API failure');
    };

    expect(() => unlockAchievement(client, ACHIEVEMENT, log)).not.toThrow();
    expect(log).toHaveBeenCalledWith('Succès ACH_TEST : échec Steam, Steam API failure');
  });
});

describe('clearAchievement', () => {
  it('clears the achievement it is given so the unlock can be replayed', () => {
    const log = vi.fn();
    const client = fakeClient();

    clearAchievement(client, ACHIEVEMENT, log);

    expect(client.achievement.clear).toHaveBeenCalledWith(ACHIEVEMENT);
    expect(log).toHaveBeenCalledWith('Succès ACH_TEST réinitialisé');
  });

  it('logs a refusal when Steam does not clear the achievement', () => {
    const log = vi.fn();

    clearAchievement(fakeClient(false), ACHIEVEMENT, log);

    expect(log).toHaveBeenCalledWith('Succès ACH_TEST : Steam a refusé la réinitialisation');
  });
});
