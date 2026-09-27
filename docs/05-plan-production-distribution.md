# 05 — Plan de production et de distribution

**Autorité sur :** la roadmap, le budget, le prix, les plateformes, le marketing.
**Révision :** 1.0 — 2 septembre 2026.

**Source :** synthèse de l'étude de marché du 2 septembre 2026 (`corebound-etude-marche-v2.md`), qui conserve les données chiffrées, les sources et leur qualification.

---

## 1. Contexte de marché — l'essentiel

| Donnée | Valeur | Date de vérification |
|---|---|---|
| Sorties Steam en 2025 | 20 282 | 02/09/2026 |
| Jeux atteignant 1 000 avis | 608, soit 2,99 % | 02/09/2026 |
| Roguelike deckbuilders sortis en 2025 | 212 | 02/09/2026 |
| Dont ≥ 1 000 avis | **5,1 %**, contre 6,71 % en 2024 | 02/09/2026 |
| Conversion médiane wishlist → vente, semaine 1 | ~0,11× le solde au lancement | 02/09/2026 |
| Gain médian au Steam Next Fest (février 2026) | ~200 wishlists ; top 5 % ~7 000 | 02/09/2026 |

**Lecture.** Le sous-genre reste presque deux fois plus performant que la moyenne Steam, mais son taux de réussite **baisse pendant que le marché global s'améliore**. C'est la définition opérationnelle de la saturation.

**Repères concurrentiels.** Backpack Battles : 14,99 $, ~19 900 avis, ~9,5 M$ bruts estimés, pic à 36 038 joueurs simultanés retombé à ~981 — et cela reste un succès financier majeur. **Dans ce genre, l'argent se fait à la vente et sur la longue traîne des soldes, pas sur la rétention.**

---

## 2. Roadmap 12 mois

### Mois 1–2 — Lever les risques techniques et poser la mesure

- Prototype de moteur hors ligne (`corebound-engine`, `static-php-cli` + `phpmicro`).
- Prototype Electron + `steamworks.js` : un achievement de test qui se déverrouille, overlay vérifié.
- **Ajouter `engineVersion` au format de snapshot** avant le premier commit PvP. Seul point irrattrapable.
- **Publier la page Steam**, même sans date. Trois variantes de description courte, mesurées au taux de wishlist.
- Audit de licences.

### Mois 3–5 — Contenu du palier EA et direction artistique

- 1 → **3 Vestiges**, 10 → **16 héros**, 30 → **~100 objets** (rythme ~5 objets/semaine).
- Effet mécanique réel donné aux affinités, en prototype à 2 affinités.
- Échange libre héros ↔ héros.
- Boucle Marchand → PvE → Marchand → PvP implémentée de bout en bout.
- **Pipeline d'assets IA lancé** (~10 à 12 assets/mois), avec prompts de base figés et versionnés par catégorie.
- Objectif wishlists : **2 000**.

### Mois 6–7 — Démo, festivals, corpus initial

- Démo Steam : une run complète en moins de 20 minutes, accrochant **avant** le premier combat PvP.
- Build web sur itch.io.
- Deck Builder Fest **et** Steam Next Fest — uniquement au-delà de 2 000 wishlists.
- 30 à 50 créateurs de contenu de niche contactés.
- Discord ouvert au moment de la démo, pas avant.
- Objectif wishlists : **8 000**.

### Mois 8–9 — Early Access

- Lancement à **9,99 €**, promo -10 % la première semaine.
- Migration PostgreSQL faite **avant**.
- Monitoring et sauvegardes opérationnels, restauration testée.
- PvP asynchrone actif dès l'EA — c'est lui qui construit le corpus.
- Cible : ~800 à 1 000 ventes en semaine 1.

### Mois 10–12 — Itération et mode hors ligne

- Une mise à jour majeure toutes les 6 semaines. Chaque palier relance l'algorithme Steam.
- Montée vers 5 Vestiges / 26 héros / ~140 objets, prix à 12,99 €.
- **Livrer le mode hors ligne comme fonctionnalité visible**, pas comme plan de secours caché : devlog dédié, post Reddit, mention en tête de la description Steam.
- Outils de déterminisme exposés au joueur : partage de seed, replay partageable, comparateur de builds.
- Localisation chinois simplifié.

---

## 3. Prix

| Phase | Prix |
|---|---|
| Démo Steam et web | Gratuit |
| **Early Access — lancement** | **9,99 €** |
| Promo de lancement EA, 7 jours | -10 % → 8,99 € |
| EA mi-parcours | 12,99 € |
| **Version 1.0** | **14,99 €** |
| Promo de lancement 1.0, 7 jours | -10 % → 13,49 € |
| Soldes à 6 mois | -20 % → 11,99 € |
| Soldes à 12 mois | -33 % → 9,99 € |
| Soldes à 18–24 mois | -50 % → 7,49 € (plancher) |
| DLC substantiel, après 12 mois | 4,99 € |

**Modèle de calcul.** Prix affiché TTC, TVA UE ~20 %, commission Steam 30 %, prix moyen encaissé sur la durée de vie ≈ 65 % du prix affiché. Soit un **net développeur ≈ prix affiché × 0,379**, c'est-à-dire **~5,68 € par copie à 14,99 €**.

**Justification de 14,99 €.** Parité de contenu avec Backpack Battles, positionnement sous The Bazaar (19,99 $), trois ans de marge de promotion préservés, et réversibilité — on peut descendre à 12,99 €, on ne peut pas remonter. La promesse de survie hors ligne justifie objectivement un premium et doit être affichée comme telle.

**Règles.** Ne jamais dépasser -50 % avant 18 à 24 mois. Ne jamais baisser le prix de base. Annoncer les hausses de prix EA à l'avance — cela crée un effet d'urgence favorable et rend la hausse légitime.

**Le cas 9,99 € en 1.0.** Il faudrait +50 % d'unités pour compenser la perte de marge, alors que l'élasticité observée sur ce segment donne plutôt +20 à 40 %. Deux cas le justifieraient malgré tout : un contenu final en dessous de la cible, ou une préférence assumée pour la base de joueurs plutôt que le revenu — ce qui a une valeur *fonctionnelle* réelle ici, puisque chaque joueur enrichit le corpus PvP. **Ce qu'il ne faut pas faire : choisir 9,99 € par prudence.** Ce n'est pas le prix qui fait vendre un jeu inconnu.

---

## 4. Budget

**Pipeline d'assets — décision D-07, tranchée le 02/09/2026.**

> **Génération IA, production étalée dans le temps. Recommission par un illustrateur professionnel si et seulement si le jeu génère les revenus nécessaires.**

Cette décision retire le principal risque financier du projet. Le budget cash de référence devient la colonne **ultra-lean**, et le seuil de rentabilité passe de ~3 240 copies à **~265 copies**.

**Ce que la décision implique concrètement :**

- **Étaler la production.** Environ 227 assets sur les jalons J1 à J4, soit ~10 à 12 assets par mois. C'est soutenable en parallèle du développement, ce qu'une commande groupée ne serait pas.
- **Fixer la cohérence avant le volume.** Le risque du pipeline IA n'est pas le coût, c'est la dérive stylistique : 227 assets produits sur 18 mois avec des prompts qui évoluent donneront un catalogue hétérogène. Mitigation : figer un prompt de base par catégorie (Vestige, héros, objet) et le versionner comme du code, dans `corebound-art-style-guide-fr.md`.
- **Rappel d'outillage.** Joindre une image de référence fait traiter celle-ci comme une image à éditer, pas comme une inspiration compositionnelle. Les générations originales se font en texte seul.
- **Préparer la recommission.** Concevoir dès maintenant les assets comme remplaçables : nommage stable, formats fixes (héros 3:4 ou 4:5, objets et Vestige 1:1), aucun effet visuel gravé dans l'image que le CSS devrait reproduire. Une recommission ultérieure doit être un remplacement de fichiers, pas une refonte de l'interface.
- **Seuil de déclenchement de la recommission, proposé :** au-delà de ~8 000 copies vendues, soit ~45 000 € de revenu avant impôts. En dessous, la trésorerie ne le justifie pas.

### 4.1 Budgets comparés

| Poste | **Retenu (ultra-lean)** | Raisonnable | Ambitieux |
|---|---|---|---|
| Steam Direct | 90 € | 90 € | 90 € |
| Epic (différé) | 90 € | 90 € | 90 € |
| **Assets visuels (~227)** | **0 €** (IA, étalée) | 9 000 € | 28 000 € |
| Audio / musique | 0 € (pipeline existant) | 900 € | 4 000 € |
| Certificat de signature de code | 300 € | 300 € | 400 € |
| Localisation | 0 € (machine + relecture) | 1 500 € | 4 500 € |
| Trailer | 0 € | 900 € | 2 500 € |
| Marketing / publicité | 0 € | 2 000 € | 8 000 € |
| Infrastructure (24 mois) | 250 € | 700 € | 2 400 € |
| Logiciels / outils | 150 € | 500 € | 1 500 € |
| Imprévus (15 %) | 130 € | 2 400 € | 7 700 € |
| **TOTAL** | **~1 010 €** | ~18 400 € | ~59 200 € |
| **Seuil de rentabilité** | **~180 copies** | ~3 240 | ~10 420 |

**Deux postes à ne pas rogner malgré le budget ultra-lean.** Le certificat de signature de code (300 €) évite l'avertissement Windows SmartScreen au premier lancement, qui génère des avis négatifs immédiats. Et l'infrastructure, qui n'est pas compressible.

**Le seuil qu'il faut aussi connaître.** À 40 €/h prudents sur ~2 700 heures (passées et restantes), le coût réel du projet est de ~108 000 €, soit ~19 000 copies. Ce chiffre n'est pas un argument pour arrêter : c'est le rappel que **Corebound est un projet personnel qui peut rapporter de l'argent, pas un investissement dont on attend un retour.** Le budget cash réel est désormais d'environ 1 000 €.

---

## 5. Scénarios financiers

Prix 1.0 à 14,99 €, net développeur ~5,68 €/copie.

| | Pessimiste | Réaliste | Optimiste |
|---|---|---|---|
| Wishlists au lancement | ~2 500 | 10 000–15 000 | 35 000+ |
| Copies sur 24 mois | 1 000 | **6 000** | 35 000 |
| CA brut | ~9 750 € | ~58 460 € | ~341 000 € |
| Frais Steam | ~2 440 € | ~14 620 € | ~85 300 € |
| Serveur (2 ans) | ~500 € | ~900 € | ~3 500 € |
| Marketing | ~300 € | ~3 500 € | ~12 000 € |
| **Revenu avant impôts** | **~5 700 €** | **~34 500 €** | **~198 000 €** |

**Aucun calcul de bénéfice net après impôts** : le statut juridique et le régime fiscal ne sont pas connus, et ils changent l'ordre de grandeur du résultat.

Le scénario optimiste correspond à ~35 % de la performance estimée de Backpack Battles : atteignable, improbable — pas plus de 5 % des jeux du sous-genre y parviennent. **La médiane du sous-genre reste plus proche du scénario pessimiste que du réaliste.**

---

## 6. Plateformes

### 6.1 Steam — canal principal

| Poste | Valeur |
|---|---|
| Steam Direct | 100 $ par produit, récupérable dès 1 000 $ de revenu brut ajusté |
| Commission | 30 % (25 % au-delà de 10 M$, 20 % au-delà de 50 M$) |
| Cloud, achievements, Steamworks, bande passante, patchs | Gratuits |
| Certification | Aucune. Revue technique de 1 à 5 jours |
| Délai | 30 jours entre paiement et éligibilité ; 4 à 6 semaines au total |
| Trésorerie | Rétention ~30 jours, paiement avant le 30 du mois suivant |

**Remboursements :** moins de 2 h de jeu **et** moins de 14 jours. Pour un roguelike, c'est la contrainte structurante : la première run doit convaincre en moins de 20 minutes.

**Fiscalité :** formulaire **W-8BEN** obligatoire. Son absence coûte 30 % de retenue à la source supplémentaire. Erreur administrative la plus chère et la plus fréquente.

### 6.2 Epic Games Store — canal secondaire, à J4 + 3 à 6 mois

| Poste | Valeur |
|---|---|
| Frais de dépôt | 100 $ par produit |
| Commission | 88/12 — mais **0 % sur le premier 1 000 000 $ net par produit et par an**, remis à zéro chaque 1er janvier |
| Epic Online Services | Gratuit |

À 14,99 € TTC (~12,49 € net), une copie rapporte **~8,74 € sur Steam** contre **~12,49 € sur Epic** — soit +43 % de marge unitaire, pour toute la durée de vie prévisible du jeu.

**Mais cette marge s'applique à un volume qu'Epic ne génère pas :** ni tags exploitables, ni recommandation algorithmique comparable, ni Next Fest, ni festivals de genre. Sur un scénario réaliste de 6 000 copies Steam, Epic ajouterait 300 à 600 copies, soit 3 700 à 7 500 € à marge quasi pleine.

**Règles.** Jamais d'exclusivité. Jamais de lancement simultané. Jamais de backend branché sur EOS. Une présence sur l'EGS est en revanche la condition pour être éligible au programme de jeux gratuits — 662 millions de récupérations en 2025 sur une centaine de titres. C'est une loterie, pas une stratégie.

### 6.3 itch.io — canal de découverte, pas de vente

Démo web gratuite. La stack Vue/PHP rend une build navigateur quasi naturelle, ce que les concurrents Unity ne peuvent pas offrir sans friction. Ne rapporte rien directement ; c'est un avantage de découvrabilité sous-exploité.

---

## 7. Marketing

### 7.1 Repères wishlists

| Donnée | Valeur |
|---|---|
| Paliers de lancement (Zukowski, révisés juin 2026) | Bronze 5 000 · Silver 8 000 · Gold 50 000 · Diamond 90 000 |
| Conversion médiane semaine 1 | ~0,11× |
| Conversion pour les jeux à plus de 10 $ | ~10 % |
| Conversion cumulée sur la durée de vie | 20–40 % |
| Seuil sous lequel Next Fest n'apporte quasi rien | **moins de 2 000 wishlists préexistantes** |

Un lancement EA à 10 000 wishlists donne ~1 000–1 100 ventes en semaine 1. À 3 000 wishlists, ~330. **L'écart entre un lancement qui décolle et un lancement qui meurt se joue entièrement là.**

### 7.2 Actions, par ordre de rendement

1. **Publier la page Steam maintenant.** Instrument de mesure gratuit : elle dit si le hook fonctionne, longtemps avant que le jeu soit prêt.
2. **Tester le hook.** Trois variantes mesurées au taux de wishlist. Candidats :
   - *« Vous n'êtes pas le héros. Vous êtes ce qui le possède. »* — le meilleur pour la capsule.
   - *« Un jeu en ligne qui continuera de fonctionner quand le serveur fermera. »* — le meilleur pour Reddit et le premier devlog.
   - *« Chaque combat est un log déterministe que vous pouvez rejouer, partager et disséquer. »* — pour le public théorycrafteur.
3. **Créateurs de niche.** 30 à 50 chaînes roguelike/deckbuilder à 5 k–50 k abonnés. Meilleure conversion que les gros streamers généralistes, et elles répondent aux e-mails.
4. **Festivals.** Deck Builder Fest et Steam Next Fest, au-delà de 2 000 wishlists uniquement. On n'y participe qu'une fois : ne pas le brûler trop tôt.
5. **Contenu vidéo natif.** Le déterminisme fournit du format court gratuit : « ce build a gagné en 40 ticks, voici pourquoi », replays partageables, comparaisons à seed identique. Personne dans le genre ne peut faire ça.
6. **Discord**, à l'ouverture de la démo. Un Discord vide fait plus de mal qu'un Discord absent.
7. **Publicité payante** : pas avant 5 000 wishlists organiques.

### 7.3 Calendrier de lancement

Éviter décembre (Winter Sale) et octobre (encombrement maximal).

---

## 8. Ce qu'il ne faut pas faire

- Repousser un jalon pour y ajouter du contenu.
- Viser 7 Vestiges / 40 héros pour le lancement en Early Access.
- Produire les ~227 assets avant que le packaging Steam soit prouvé.
- Lancer sans démo.
- Acheter de la publicité avant 5 000 wishlists organiques.
- Présenter les adversaires d'archive comme de vrais joueurs.
- Revendiquer publiquement une filiation avec The Bazaar.
- Sortir sur Epic en même temps que sur Steam.
