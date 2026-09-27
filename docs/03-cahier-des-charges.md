# 03 — Cahier des charges

**Autorité sur :** le périmètre par jalon, les exigences, les critères d'acceptation.
**Révision :** 1.0 — 2 septembre 2026.

**Règle fondamentale :** aucune mécanique classée CIBLE ou OUVERTE dans le GDD ne passe en développement sans une décision explicite qui la fait basculer en ENGAGÉ **et** l'inscrit dans un jalon de ce document.

---

## 1. Jalons

| Jalon | Contenu | Prix | Horizon indicatif |
|---|---|---|---|
| **J0 — Socle technique** | Moteur hors ligne + packaging Steam prouvés | — | Mois 1–2 |
| **J1 — Démo** | Une run courte et complète, jouable en < 20 min | Gratuit | Mois 6–7 |
| **J2 — Early Access** | Boucle cible complète, périmètre réduit | 9,99 € | Mois 8–9 |
| **J3 — EA mi-parcours** | Montée en contenu, systèmes cibles validés | 12,99 € | Mois 14–16 |
| **J4 — Version 1.0** | Contenu cible complet | 14,99 € | Mois 20–24 |
| **J5 — Epic Games Store** | Portage commercial, aucun ajout fonctionnel | 14,99 € | J4 + 3 à 6 mois |

**Principe de séquencement.** On ne repousse jamais un jalon pour y ajouter du contenu : on sort le jalon et on ajoute le contenu au suivant. Le risque numéro un du projet est de ne jamais sortir, pas de sortir trop léger.

---

## 2. Périmètre par jalon

### 2.1 J0 — Socle technique

**Objectif unique : lever les deux risques techniques structurants avant toute production de contenu ou d'assets.**

| Exigence | Critère d'acceptation |
|---|---|
| **EX-J0-01** — Moteur exécutable hors ligne | `echo '{...}' \| corebound-engine.exe` produit un `CombatLog` **strictement identique** à celui du serveur pour la même seed |
| **EX-J0-02** — Achievement Steam fonctionnel | Un achievement de test se déverrouille depuis le client packagé |
| **EX-J0-03** — Format de snapshot versionné | Tout snapshot porte un champ `engineVersion`. **Non rattrapable a posteriori** |
| **EX-J0-04** — Page Steam publiée | En ligne, sans date de sortie, avec capsule et description |
| **EX-J0-05** — Audit de licences | Dépendances Electron, npm et extensions PHP compilées vérifiées contre les exigences du SDK Steamworks |
| **EX-J0-06** — Prompts d'assets figés | Un prompt de base versionné par catégorie (Vestige, héros, objet), documenté dans le guide de DA, avant toute production de volume |

**Aucun travail d'illustration ni de contenu ne démarre avant que EX-J0-01 et EX-J0-02 soient verts.**

### 2.2 J1 — Démo

| Élément | Valeur |
|---|---|
| Vestiges | 1 |
| Héros au catalogue | 8 |
| Objets | ~50 |
| Manches jouables | 3 à 4, avec fin anticipée |
| Boucle | Marchand → PvE → Marchand → PvP |
| PvP | Contre adversaires d'archive uniquement |
| Durée cible | **Moins de 20 minutes** pour une session complète |

**Contrainte critique.** La démo doit accrocher **avant le premier combat PvP**. Un serveur lent ou une base vide au premier contact se paie immédiatement en abandons, et plus tard en remboursements.

**Diffusion :** démo Steam **et** build web sur itch.io.

### 2.3 J2 — Early Access

| Élément | Valeur | Actuel |
|---|---|---|
| Vestiges | **3** | 1 |
| Héros | **16** | 10 |
| Compétences de héros | **16** | 10 |
| Objets | **90–110** | 30 |
| Statuts | 5–6 | 4 |
| Passifs de plateau | 10–15 | 0 |
| Monstres | 12–18, répartis sur 3 difficultés | 1 scripté |
| Marchands spécialisés | 3 types | 1 générique |
| Boucle complète Marchand → PvE → Marchand → PvP | Oui | Non |
| Affinités à effet mécanique | **2**, en prototype | 0 |
| Échange libre héros ↔ héros | Oui | Non |
| Mode hors ligne | Fonctionnel et testable | Non |
| Localisation | FR + EN | FR |

**Décision D-06 ouverte** sur le volume final d'objets. La fourchette 90–110 pour J2 est indépendante de cette décision.

**Contrainte bloquante sur le roster.** Ne pas dépasser **20 héros** tant que le système d'affinité n'a pas d'effet mécanique. Au-delà, les compétences dupliquées produisent des héros strictement identiques en jeu (voir `02` §2.3.1).

### 2.4 J3 — Early Access mi-parcours

| Élément | Valeur |
|---|---|
| Vestiges | 5 |
| Héros | 26 |
| Objets | ~140 |
| Affinités à effet mécanique | 4 à 5, système validé ou repensé |
| Fusion d'objets | Prototype 3 rangs, sur un sous-ensemble d'objets |
| Localisation | FR + EN + ZH-Hans |

**Porte de sortie.** Si le prototype d'affinité de J2 démontre que l'affinité n'est qu'un multiplicateur de puissance sans effet sur les builds, **le système est repensé à J3, pas étendu.** Ne jamais généraliser un système non validé.

### 2.5 J4 — Version 1.0

| Élément | Valeur |
|---|---|
| Vestiges | **7** |
| Héros | **40**, tous distinguables en une ligne |
| Compétences de héros | 40 |
| Objets | **160–200** (D-06) |
| Statuts | 8–10 |
| Passifs de plateau | 30–40 |
| Monstres | 30+ |
| Affinités | Système complet à 4 relations |
| Fusion d'objets | Généralisée |
| Double phase marchand | Complète, avec marchands spécialisés et rachat |
| PvP | Mûr, corpus alimenté par toute la durée de l'EA |
| Mode hors ligne / corpus | Fonctionnel et **documenté publiquement** |
| Durée avant répétition ressentie | **25–30 heures** |
| Localisation | FR + EN + ZH-Hans minimum |

### 2.6 J5 — Epic Games Store

Aucun ajout fonctionnel. Portage commercial uniquement : second build, seconde page produit, seconde file de support.

**Contrainte :** ne jamais brancher le backend sur Epic Online Services, même gratuit. Cela créerait une dépendance externe sur un système qui doit rester intégralement archivable pour le mode hors ligne.

---

## 3. Exigences non fonctionnelles

### 3.1 Déterminisme

| Réf. | Exigence |
|---|---|
| **NF-01** | Pour un triplet (plateau joueur, plateau adverse, seed) identique, le `CombatLog` produit est **identique octet pour octet**, quelle que soit la plateforme d'exécution |
| **NF-02** | Aucune source d'aléa hors `\Random\Randomizer` seedé n'est autorisée dans la couche Domaine |
| **NF-03** | Le client rejoue le `CombatLog` sans jamais recalculer le combat |
| **NF-04** | Le déterminisme client/serveur est vérifié par un test automatisé sur un jeu de seeds fixes, exécuté en CI |

### 3.2 Persistance et intégrité

| Réf. | Exigence |
|---|---|
| **NF-05** | L'état d'une run est reconstruit en rejouant un journal d'actions ordonné sur une seed fixe, jamais par désérialisation d'instantané |
| **NF-06** | Une action n'est journalisée **qu'après validation réussie**. Une action invalide journalisée corrompt définitivement l'historique de rejeu |
| **NF-07** | Tout snapshot PvP porte un `engineVersion` permettant de déterminer s'il est interprétable par le moteur courant |
| **NF-08** | Les sauvegardes locales s'écrivent dans le répertoire de données applicatives de l'OS, **jamais** dans le dossier d'installation Steam |

### 3.3 Mode hors ligne

| Réf. | Exigence |
|---|---|
| **NF-09** | Le client embarque un moteur de simulation capable de résoudre un combat sans accès réseau |
| **NF-10** | Un bascule manuelle en mode hors ligne est disponible et testable **dès J2**, pas seulement au moment du sunset |
| **NF-11** | Le corpus de snapshots d'archive est livré comme ressource embarquée dans le build, jamais comme téléchargement externe |
| **NF-12** | La cible de corpus final est d'au moins 5 000 snapshots, répartis par manche et par palier de puissance |

### 3.4 Qualité

| Réf. | Exigence |
|---|---|
| **NF-13** | Toute logique métier backend est couverte par PHPUnit ; toute logique métier frontend (client API, store, composables) par Vitest |
| **NF-14** | PHPStan niveau 6 sans erreur, PHP CS Fixer, ESLint, Prettier et `vue-tsc` verts avant tout merge |
| **NF-15** | Les portes qualité sont bloquantes sur toute PR vers `dev` |
| **NF-16** | Les trous de couverture connus sont documentés en commentaire dans le fichier de test concerné, pas dans un document séparé |
| **NF-17** | Le catalogue de héros respecte l'invariant d'unicité : au plus un héros par couple (compétence, affinité). Vérifié par test automatisé sur `heroes.json` |

### 3.5 Performance

| Réf. | Exigence |
|---|---|
| **NF-18** | Un combat complet (jusqu'à 500 ticks, ≤ 6 entités) se résout en moins de 50 ms côté serveur |
| **NF-19** | Le client démarre en moins de 5 secondes sur une configuration de milieu de gamme |
| **NF-20** | L'API répond en moins de 300 ms au 95ᵉ centile, hors latence réseau |

### 3.6 Disponibilité

| Réf. | Exigence |
|---|---|
| **NF-21** | Une indisponibilité du backend ne doit jamais corrompre une run en cours : le client doit pouvoir reprendre là où il s'est arrêté |
| **NF-22** | Sauvegardes de la base quotidiennes, hors site, restauration testée au moins une fois avant J2 |

---

## 4. Exigences de conformité et de distribution

| Réf. | Exigence |
|---|---|
| **C-01** | Formulaire fiscal **W-8BEN** complété avant toute mise en vente. Son absence coûte 30 % de retenue à la source supplémentaire |
| **C-02** | Aucune dépendance sous licence copyleft dans le binaire distribué — le SDK Steamworks y est incompatible |
| **C-03** | Certificat de signature de code valide, pour éviter l'avertissement Windows SmartScreen |
| **C-04** | Politique de confidentialité publiée, exigée par Valve pour tout jeu collectant des données de compte |
| **C-05** | Les snapshots stockés ne contiennent aucune donnée personnelle identifiante ; le pseudonyme affiché doit être dissociable ou remplaçable dans le corpus final |
| **C-06** | Hébergement en Union européenne |
| **C-07** | Le caractère « connexion permanente requise » est déclaré explicitement sur la page produit, accompagné de la mention du mode hors ligne |
| **C-08** | Aucun nom, pouvoir ou personnage repris d'une œuvre tierce |

---

## 5. Critères de sortie par jalon

Un jalon n'est franchi que si **tous** ses critères sont verts. Aucun n'est négociable au motif du calendrier.

**J0** — EX-J0-01 à EX-J0-06 verts.

**J1** — Une run complète jouable de bout en bout en moins de 20 minutes, sans blocage, sur un poste vierge. Démo Steam et build web en ligne. **2 000 wishlists** avant toute inscription à un festival.

**J2** — Périmètre §2.3 complet. NF-01 à NF-17 respectées. Migration vers PostgreSQL effectuée **avant** le lancement, jamais pendant. Monitoring et sauvegardes automatiques opérationnels. **8 000 wishlists** visées.

**J3** — Périmètre §2.4 complet. Décision prise et documentée sur le système d'affinité (validé ou repensé).

**J4** — Périmètre §2.5 complet. Mode hors ligne testé de bout en bout par un tiers. Toutes les exigences NF et C vertes.

**J5** — Build Epic stable, aucune régression sur la version Steam.

---

## 6. Ce qui reste hors périmètre, jusqu'à décision explicite

- Portage mobile
- Portage console
- Mode multijoueur synchrone
- Classement compétitif avec saisons
- Fonctions sociales (guildes, chat, échanges entre joueurs)
- Éditeur de contenu ou support du modding
- Contenu narratif ramifié
- Toute forme de micro-transaction
