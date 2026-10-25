// src/engine.test.ts
import { spawn } from 'node:child_process';
import { EventEmitter } from 'node:events';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { runEngine, type SpawnEngine } from './engine';

const BINARY = 'C:\\Program Files (x86)\\Steam\\steamapps\\common\\Corebound\\corebound-engine.exe';
const TIMEOUT_MS = 10_000;

// La requête et la sortie portent un caractère accentué : un découpage de
// stdout au milieu d'un caractère UTF-8 doit laisser les octets intacts.
const REQUEST = Buffer.from('{"combatSeed":"graine-é"}');
const OUTPUT = Buffer.from('{"combatLog":{},"resolution":"KNOCKOUT","winnerSide":"é"}');

class FakeInput extends EventEmitter {
  end = vi.fn();
}

/** Un processus du moteur que le test pilote : rien ne s'exécute. */
class FakeEngine extends EventEmitter {
  stdin = new FakeInput();
  stdout = new EventEmitter();
  stderr = new EventEmitter();
  kill = vi.fn(() => true);

  exit(code: number | null, stdout: Buffer[] = [], stderr = ''): void {
    stdout.forEach((chunk) => this.stdout.emit('data', chunk));
    if (stderr !== '') {
      this.stderr.emit('data', Buffer.from(stderr));
    }
    this.emit('close', code, code === null ? 'SIGTERM' : null);
  }
}

function fakeSpawn(engine: FakeEngine) {
  return vi.fn<SpawnEngine>(() => engine);
}

afterEach(() => {
  vi.useRealTimers();
});

describe('runEngine', () => {
  // Sans windowsHide, un exécutable console lancé depuis une application
  // fenêtrée ouvre une console Windows à chaque combat.
  it('spawns the binary it is given, without arguments, piped and without a console window', () => {
    const engine = new FakeEngine();
    const spawnEngine = fakeSpawn(engine);

    void runEngine(spawnEngine, BINARY, REQUEST, TIMEOUT_MS);

    expect(spawnEngine).toHaveBeenCalledWith(BINARY, [], { stdio: 'pipe', windowsHide: true });
  });

  // Sans la fermeture de stdin, le moteur attendrait la suite de la requête.
  it('writes the request bytes to stdin and closes it', () => {
    const engine = new FakeEngine();

    void runEngine(fakeSpawn(engine), BINARY, REQUEST, TIMEOUT_MS);

    expect(engine.stdin.end).toHaveBeenCalledTimes(1);
    expect(engine.stdin.end).toHaveBeenCalledWith(REQUEST);
  });

  it('resolves with the stdout bytes, joined across chunks, when the engine exits with 0', async () => {
    const engine = new FakeEngine();
    const outcome = runEngine(fakeSpawn(engine), BINARY, REQUEST, TIMEOUT_MS);

    // Coupé au milieu du « é » final (deux octets en UTF-8).
    const cut = OUTPUT.length - 3;
    engine.exit(0, [OUTPUT.subarray(0, cut), OUTPUT.subarray(cut)]);

    const result = await outcome;
    expect(result.ok).toBe(true);
    expect(result.ok && result.output.equals(OUTPUT)).toBe(true);
  });

  it.each([
    [1, 'INVALID_JSON', 'stdin is not valid JSON'],
    [2, 'INTERNAL_ERROR', 'Unexpected engine failure'],
  ])(
    'reports the code and message of the stderr line when the engine exits with %i',
    async (exitCode, code, message) => {
      const engine = new FakeEngine();
      const outcome = runEngine(fakeSpawn(engine), BINARY, REQUEST, TIMEOUT_MS);

      engine.exit(exitCode, [], `${JSON.stringify({ code, message })}\n`);

      expect(await outcome).toEqual({
        ok: false,
        error: { kind: 'refused', exitCode, code, message },
      });
    },
  );

  // Un code ou une ligne d'erreur hors contrat : le moteur n'a pas parlé
  // comme le dit 04 §4.3, et stderr est rendu tel quel pour le journal.
  it('reports an unexpected failure with the raw stderr when it is not the contract line', async () => {
    const engine = new FakeEngine();
    const outcome = runEngine(fakeSpawn(engine), BINARY, REQUEST, TIMEOUT_MS);

    engine.exit(255, [], 'PHP Fatal error: Allowed memory size exhausted\n');

    expect(await outcome).toEqual({
      ok: false,
      error: {
        kind: 'unexpected',
        exitCode: 255,
        stderr: 'PHP Fatal error: Allowed memory size exhausted\n',
      },
    });
  });

  it('reports an unexpected failure when the engine is killed by a signal', async () => {
    const engine = new FakeEngine();
    const outcome = runEngine(fakeSpawn(engine), BINARY, REQUEST, TIMEOUT_MS);

    engine.exit(null);

    expect(await outcome).toEqual({
      ok: false,
      error: { kind: 'unexpected', exitCode: null, stderr: '' },
    });
  });

  // Binaire absent : Node émet « error », et peut émettre « close » ensuite.
  it('reports that the engine did not start when the spawn fails, whatever follows', async () => {
    const engine = new FakeEngine();
    const outcome = runEngine(fakeSpawn(engine), BINARY, REQUEST, TIMEOUT_MS);

    engine.emit('error', new Error('spawn corebound-engine.exe ENOENT'));
    engine.exit(-4058);

    expect(await outcome).toEqual({
      ok: false,
      error: { kind: 'not-started', message: 'spawn corebound-engine.exe ENOENT' },
    });
  });

  // Node lève sans émettre d'événement quand il refuse ses arguments.
  it('reports that the engine did not start when spawn throws', async () => {
    const spawnEngine = vi.fn<SpawnEngine>(() => {
      throw new Error("The argument 'file' cannot be empty");
    });

    expect(await runEngine(spawnEngine, '', REQUEST, TIMEOUT_MS)).toEqual({
      ok: false,
      error: { kind: 'not-started', message: "The argument 'file' cannot be empty" },
    });
  });

  it('kills the engine and reports a timeout when it runs too long', async () => {
    vi.useFakeTimers();
    const engine = new FakeEngine();
    const outcome = runEngine(fakeSpawn(engine), BINARY, REQUEST, 500);

    vi.advanceTimersByTime(500);
    engine.exit(null);

    expect(engine.kill).toHaveBeenCalledTimes(1);
    expect(await outcome).toEqual({ ok: false, error: { kind: 'timeout', timeoutMs: 500 } });
  });

  it('does not kill an engine that finished in time', async () => {
    vi.useFakeTimers();
    const engine = new FakeEngine();
    const outcome = runEngine(fakeSpawn(engine), BINARY, REQUEST, 500);

    engine.exit(0, [OUTPUT]);
    await outcome;
    vi.advanceTimersByTime(500);

    expect(engine.kill).not.toHaveBeenCalled();
  });

  // Un moteur qui sort avant d'avoir tout lu fait échouer l'écriture (EPIPE).
  // Sans écouteur, Node lèverait l'erreur et ferait tomber le processus principal.
  it('does not throw when writing the request fails, and reports the exit instead', async () => {
    const engine = new FakeEngine();
    const outcome = runEngine(fakeSpawn(engine), BINARY, REQUEST, TIMEOUT_MS);

    expect(() => engine.stdin.emit('error', new Error('write EPIPE'))).not.toThrow();
    engine.exit(1, [], `${JSON.stringify({ code: 'INVALID_JSON', message: 'Syntax error' })}\n`);

    expect(await outcome).toEqual({
      ok: false,
      error: { kind: 'refused', exitCode: 1, code: 'INVALID_JSON', message: 'Syntax error' },
    });
  });
});

// Un vrai processus, sans le binaire : Node lui-même joue le moteur. Le
// lancement réel, les vrais tubes et les vrais codes de sortie sont exercés,
// sur n'importe quel système, y compris en CI où le binaire n'existe pas.
describe('runEngine with a real child process', () => {
  function nodeScript(script: string): SpawnEngine {
    return (_binary, _args, options) => spawn(process.execPath, ['-e', script], options);
  }

  it('sends the request and reads the output through real pipes', async () => {
    // Renvoie la requête reçue, entourée de crochets, en plusieurs écritures.
    const echo = `
      const chunks = [];
      process.stdin.on('data', (c) => chunks.push(c));
      process.stdin.on('end', () => {
        const body = Buffer.concat(chunks);
        process.stdout.write('[');
        process.stdout.write(body.subarray(0, 5));
        process.stdout.write(body.subarray(5));
        process.stdout.write(']');
      });
    `;

    const result = await runEngine(nodeScript(echo), BINARY, REQUEST, TIMEOUT_MS);

    expect(result.ok).toBe(true);
    expect(
      result.ok &&
        result.output.equals(Buffer.concat([Buffer.from('['), REQUEST, Buffer.from(']')])),
    ).toBe(true);
  });

  it('reads the exit code and the stderr line of a real process', async () => {
    const refuse = `
      process.stdin.resume();
      process.stdin.on('end', () => {
        process.stderr.write(JSON.stringify({ code: 'INVALID_REQUEST', message: 'missing combatSeed' }) + '\\n');
        process.exitCode = 1;
      });
    `;

    expect(await runEngine(nodeScript(refuse), BINARY, REQUEST, TIMEOUT_MS)).toEqual({
      ok: false,
      error: {
        kind: 'refused',
        exitCode: 1,
        code: 'INVALID_REQUEST',
        message: 'missing combatSeed',
      },
    });
  });

  it('reports that the engine did not start when the binary does not exist', async () => {
    const result = await runEngine(spawn, 'corebound-engine-introuvable.exe', REQUEST, TIMEOUT_MS);

    expect(result.ok).toBe(false);
    expect(!result.ok && result.error.kind).toBe('not-started');
  });
});
