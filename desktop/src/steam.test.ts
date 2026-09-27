// src/steam.test.ts
import { describe, expect, it, vi } from 'vitest';
import { initSteam, type SteamworksModule } from './steam';

const SPACEWAR = 480;

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
    const init = vi.fn(() => ({ localplayer: { getName: () => 'Kagelestis' } }));

    initSteam(() => ({ init }), 4000, vi.fn());

    expect(init).toHaveBeenCalledWith(4000);
  });

  it('reports Steam available with the player name when initialisation succeeds', () => {
    const log = vi.fn();
    const steamworks: SteamworksModule = {
      init: () => ({ localplayer: { getName: () => 'Kagelestis' } }),
    };

    const status = initSteam(() => steamworks, SPACEWAR, log);

    expect(status).toEqual({ available: true, playerName: 'Kagelestis' });
    expect(log).toHaveBeenCalledWith('Steam disponible, AppID 480, joueur Kagelestis');
  });
});
