// src/api/runApi.ts
import { RunNotFoundError, InvalidActionError, ConflictError } from './errors';
import type { CreateRunResponse, RunActionResponse, ResolveRoundResponse } from './types';

async function handleResponse<T>(res: Response): Promise<T> {
  if (res.ok) return res.json() as Promise<T>;

  const body = await res.json().catch(() => null);
  const message = body?.error ?? `Unexpected status ${res.status}`;

  if (res.status === 404) throw new RunNotFoundError(message);
  if (res.status === 400) throw new InvalidActionError(message);
  if (res.status === 409) throw new ConflictError(message);
  throw new Error(message);
}

export const runApi = {
  create: (): Promise<CreateRunResponse> =>
    fetch('/runs', { method: 'POST' }).then((res) => handleResponse(res)),

  show: (runId: string): Promise<RunActionResponse> =>
    fetch(`/runs/${runId}`).then((res) => handleResponse(res)),

  chooseHero: (runId: string, heroId: string): Promise<RunActionResponse> =>
    fetch(`/runs/${runId}/hero/choose`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ heroId }),
    }).then((res) => handleResponse(res)),

  buyItem: (runId: string, slotIndex: number): Promise<RunActionResponse> =>
    fetch(`/runs/${runId}/shop/buy`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ slotIndex }),
    }).then((res) => handleResponse(res)),

  swapItem: (
    runId: string,
    inventoryIndex: number,
    stashIndex: number,
    heroId: string,
  ): Promise<RunActionResponse> =>
    fetch(`/runs/${runId}/inventory/swap`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ inventoryIndex, stashIndex, heroId }),
    }).then((res) => handleResponse(res)),

  resolveRound: (runId: string): Promise<ResolveRoundResponse> =>
    fetch(`/runs/${runId}/round/resolve`, { method: 'POST' }).then((res) => handleResponse(res)),
};
