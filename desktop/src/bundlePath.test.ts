// src/bundlePath.test.ts
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import { resolveBundlePath } from './bundlePath';

// Chemin absolu sur la plateforme qui exécute le test : Windows en local, Linux en CI.
const root = path.resolve('bundle-root');

describe('resolveBundlePath', () => {
  it('refuses to leave the bundle through an encoded slash', () => {
    expect(resolveBundlePath(root, 'app://bundle/..%2f..%2fsecret.txt')).toBeNull();
  });

  it('refuses any backslash, which Windows would read as a separator', () => {
    expect(resolveBundlePath(root, 'app://bundle/..%5csecret.txt')).toBeNull();
    expect(resolveBundlePath(root, 'app://bundle/assets%5cx.png')).toBeNull();
  });

  it('refuses a path that restarts from the filesystem root', () => {
    expect(resolveBundlePath(root, 'app://bundle/%2fetc%2fpasswd')).toBeNull();
  });

  it('refuses a null byte', () => {
    expect(resolveBundlePath(root, 'app://bundle/index.html%00.png')).toBeNull();
  });

  it('refuses malformed percent-encoding instead of throwing', () => {
    expect(resolveBundlePath(root, 'app://bundle/%E0%A4%A')).toBeNull();
  });

  it('serves index.html for the bundle root', () => {
    expect(resolveBundlePath(root, 'app://bundle/')).toBe(path.join(root, 'index.html'));
  });

  it('maps a nested asset path onto the bundle directory', () => {
    expect(resolveBundlePath(root, 'app://bundle/assets/heroes/shadow_bearer.jpg')).toBe(
      path.join(root, 'assets', 'heroes', 'shadow_bearer.jpg'),
    );
  });

  it('decodes percent-encoded characters', () => {
    expect(resolveBundlePath(root, 'app://bundle/assets/a%20b.png')).toBe(
      path.join(root, 'assets', 'a b.png'),
    );
  });

  it('ignores the query string and the fragment', () => {
    expect(resolveBundlePath(root, 'app://bundle/index.html?v=1#top')).toBe(
      path.join(root, 'index.html'),
    );
  });

  // Les segments « .. » en clair ne sont pas une menace : l'analyseur d'URL
  // les résout avant que le chemin ne soit lu. Ce test le fige, pour que la
  // garde ne soit pas un jour « simplifiée » en supposant l'inverse.
  it('lets the URL parser collapse plain dot segments inside the bundle', () => {
    expect(resolveBundlePath(root, 'app://bundle/assets/../index.html')).toBe(
      path.join(root, 'index.html'),
    );
  });
});
