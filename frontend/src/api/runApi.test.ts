// src/api/runApi.test.ts
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { runApi } from './runApi';
import { RunNotFoundError, InvalidActionError, ConflictError } from './errors';

describe('runApi', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn());
  });

  it('creates a run and returns run_id and state', async () => {
    const mockResponse = { run_id: 'abc123', state: { round: 1 } };
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      json: () => Promise.resolve(mockResponse),
    } as Response);

    const result = await runApi.create();

    expect(result).toEqual(mockResponse);
    expect(fetch).toHaveBeenCalledWith('/runs', { method: 'POST' });
  });

  it('throws RunNotFoundError on 404', async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: false,
      status: 404,
      json: () => Promise.resolve({ error: 'No run found for id "x".' }),
    } as Response);

    await expect(runApi.show('x')).rejects.toThrow(RunNotFoundError);
  });

  it('throws InvalidActionError on 400', async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: false,
      status: 400,
      json: () => Promise.resolve({ error: 'Action requires an integer "slotIndex" payload key.' }),
    } as Response);

    await expect(runApi.buyItem('abc123', 99)).rejects.toThrow(InvalidActionError);
  });

  it('throws ConflictError on 409', async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: false,
      status: 409,
      json: () => Promise.resolve({ error: 'Cannot purchase: no shop is currently open.' }),
    } as Response);

    await expect(runApi.buyItem('abc123', 0)).rejects.toThrow(ConflictError);
  });
});