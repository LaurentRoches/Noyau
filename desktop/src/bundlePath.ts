// src/bundlePath.ts
import path from 'node:path';

/**
 * Traduit une URL du protocole `app://` en fichier du build du frontend.
 *
 * Rend `null` pour toute URL qui sortirait de `root` ou qui ne se lit pas
 * sans ambiguïté. Le gestionnaire du protocole en fait un refus : aucun chemin
 * construit à partir d'une URL ne doit atteindre le disque sans être passé ici.
 *
 * Aucune dépendance à Electron, délibérément : ce module est testé, et
 * importer `electron` dans un test téléchargerait son binaire (`06` §10).
 */
export function resolveBundlePath(root: string, requestUrl: string): string | null {
  let decoded: string;
  try {
    // L'analyseur d'URL résout les « .. » en clair ; il laisse en revanche
    // `%2f` et `%5c` intacts, que le décodage transforme en séparateurs.
    // C'est pourquoi la garde porte sur le chemin décodé, pas sur l'URL.
    decoded = decodeURIComponent(new URL(requestUrl).pathname);
  } catch {
    return null;
  }

  // Un chemin d'URL légitime ne contient jamais de barre oblique inverse, et
  // Windows la lirait comme un séparateur : on la refuse partout, pour que la
  // règle soit la même sur toutes les plateformes. L'octet nul tronquerait le
  // nom de fichier au niveau du système.
  if (decoded.includes('\\') || decoded.includes('\0')) {
    return null;
  }

  const relative = decoded === '/' ? 'index.html' : decoded.slice(1);
  const resolved = path.resolve(root, relative);
  const fromRoot = path.relative(root, resolved);

  const outside =
    fromRoot === '' ||
    fromRoot === '..' ||
    fromRoot.startsWith(`..${path.sep}`) ||
    path.isAbsolute(fromRoot);

  return outside ? null : resolved;
}
