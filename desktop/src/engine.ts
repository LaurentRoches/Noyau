// src/engine.ts

/**
 * La part d'un processus enfant dont l'appel du moteur se sert, décrite ici
 * plutôt qu'importée, pour que les tests la remplacent par un faux.
 *
 * Le vrai `spawn` de `node:child_process` s'y conforme : un test lui passe
 * `spawn` tel quel, sans `as`, et le typecheck échouerait si cette
 * description lui demandait ce qu'il n'a pas (`06` §4.3).
 */
export interface EngineProcess {
  stdin: {
    end(request: Buffer): unknown;
    on(event: 'error', listener: (error: Error) => void): unknown;
  };
  stdout: { on(event: 'data', listener: (chunk: Buffer) => void): unknown };
  stderr: { on(event: 'data', listener: (chunk: Buffer) => void): unknown };
  on(event: 'error', listener: (error: Error) => void): unknown;
  on(event: 'close', listener: (code: number | null) => void): unknown;
  kill(): boolean;
}

export type SpawnEngine = (
  binary: string,
  args: readonly string[],
  options: { stdio: 'pipe'; windowsHide: boolean },
) => EngineProcess;

/**
 * Pourquoi l'appel a échoué. `refused` est l'échec prévu par le contrat
 * (`04` §4.3) : code de sortie 1 ou 2, et une ligne JSON `{code, message}`
 * sur stderr. Tout le reste est `unexpected`, avec stderr brut.
 */
export type EngineError =
  | { kind: 'refused'; exitCode: number; code: string; message: string }
  | { kind: 'unexpected'; exitCode: number | null; stderr: string }
  | { kind: 'not-started'; message: string }
  | { kind: 'timeout'; timeoutMs: number };

/** Les octets de stdout tels quels : le décodage appartient à l'appelant. */
export type EngineOutcome = { ok: true; output: Buffer } | { ok: false; error: EngineError };

function messageOf(error: unknown): string {
  return error instanceof Error ? error.message : String(error);
}

/** La ligne d'échec du contrat, ou `null` si stderr n'en est pas une. */
function contractError(stderr: string): { code: string; message: string } | null {
  try {
    const line: unknown = JSON.parse(stderr);
    if (typeof line !== 'object' || line === null) {
      return null;
    }
    const { code, message } = line as Record<string, unknown>;
    return typeof code === 'string' && typeof message === 'string' ? { code, message } : null;
  } catch {
    return null;
  }
}

function outcomeOf(exitCode: number | null, stdout: Buffer, stderr: string): EngineOutcome {
  if (exitCode === 0) {
    return { ok: true, output: stdout };
  }
  if (exitCode === 1 || exitCode === 2) {
    const refusal = contractError(stderr);
    if (refusal !== null) {
      return { ok: false, error: { kind: 'refused', exitCode, ...refusal } };
    }
  }
  return { ok: false, error: { kind: 'unexpected', exitCode, stderr } };
}

/**
 * Lance le moteur embarqué sur une requête et rend son résultat, sans jamais
 * rejeter : un binaire absent, un refus ou un délai dépassé sont des issues,
 * comme Steam indisponible pour `initSteam`.
 *
 * La requête est transmise telle quelle, en octets, puis stdin est fermé :
 * sans cette fermeture, le moteur attendrait la suite. stdout est accumulé en
 * octets et rendu sans décodage, pour qu'un caractère UTF-8 coupé entre deux
 * morceaux reste intact et que la sortie se compare octet pour octet.
 *
 * Pas de shell, et des arguments en tableau : le dossier d'installation de
 * Steam contient des espaces. `windowsHide` évite qu'un exécutable console,
 * lancé depuis une application fenêtrée, ouvre une console à chaque combat.
 */
export function runEngine(
  spawnEngine: SpawnEngine,
  binary: string,
  request: Buffer,
  timeoutMs: number,
): Promise<EngineOutcome> {
  return new Promise((resolve) => {
    let engine: EngineProcess;
    try {
      engine = spawnEngine(binary, [], { stdio: 'pipe', windowsHide: true });
    } catch (error) {
      // Node lève lui-même, sans événement, sur des arguments qu'il refuse.
      resolve({ ok: false, error: { kind: 'not-started', message: messageOf(error) } });
      return;
    }

    // Le premier événement décisif l'emporte : délai, échec du lancement ou
    // fin du processus. Les suivants ne changent plus rien.
    let settled = false;
    function settle(outcome: EngineOutcome): void {
      if (!settled) {
        settled = true;
        clearTimeout(timer);
        resolve(outcome);
      }
    }

    const timer = setTimeout(() => {
      engine.kill();
      settle({ ok: false, error: { kind: 'timeout', timeoutMs } });
    }, timeoutMs);

    const stdout: Buffer[] = [];
    const stderr: Buffer[] = [];
    engine.stdout.on('data', (chunk) => stdout.push(chunk));
    engine.stderr.on('data', (chunk) => stderr.push(chunk));

    // Node émet « error » quand le lancement échoue, et peut émettre
    // « close » ensuite : le premier des deux décide.
    engine.on('error', (error) => {
      settle({ ok: false, error: { kind: 'not-started', message: messageOf(error) } });
    });
    engine.on('close', (code) => {
      settle(outcomeOf(code, Buffer.concat(stdout), Buffer.concat(stderr).toString('utf8')));
    });

    // Un moteur qui sort avant d'avoir tout lu fait échouer l'écriture
    // (EPIPE). Sans écouteur, Node lèverait l'erreur et ferait tomber le
    // processus principal ; le code de sortie dit déjà ce qui s'est passé.
    engine.stdin.on('error', () => {
      // Ignorée à dessein : « close » suit et porte le code de sortie.
    });
    engine.stdin.end(request);
  });
}
