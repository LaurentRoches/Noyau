# Framework d'équilibrage des objets
*Projet : Roguelike Deckbuilder / Auto-Battler PvP Asynchrone*
*Inspirations : The Bazaar, Slay the Spire, Backpack Battles*

---

# Philosophie générale

L'objectif n'est **pas d'équilibrer chaque objet**, mais **d'équilibrer un système**.

Chaque objet possède un **budget de puissance**.

Les objets peuvent être :

- universellement bons
- spécialisés
- dépendants de synergies

Un objet faible seul mais excellent dans un build spécifique est souvent plus intéressant qu'un objet fort dans toutes les situations.

---

# 1. Définir une monnaie de puissance

Toutes les statistiques du jeu doivent être converties dans une même unité.

Exemple (valeurs fictives) :

| Effet | Valeur |
|--------|--------:|
| 10 dégâts | 10 |
| 10 PV | 8 |
| 10 Armure | 10 |
| 1 Poison | 5 |
| 1 Burn | 4 |
| 1 Critique | 12 |
| 1 Mana | 40 |
| 1 seconde de Haste | 20 |
| Pioche 1 carte | 35 |

Ces valeurs seront ajustées pendant les playtests.

Le but est uniquement de disposer d'une base cohérente.

---

# 2. Budget par rareté

Exemple :

| Rareté | Budget |
|---------|--------:|
| Commun | 100 |
| Rare | 140 |
| Épique | 190 |
| Légendaire | 250 |

Chaque nouvel objet doit essayer de respecter ce budget.

---

# 3. Décomposer la puissance

Chaque objet peut être considéré comme :

Puissance Totale =
Statistiques
+ Effets
+ Déclencheurs
+ Synergies

Exemple :

+30 dégâts

↓

30 points

---

Exemple :

Après chaque attaque,
gagnez 5 armure.

↓

Armure moyenne gagnée pendant un combat

↓

Valeur estimée

---

# 4. Les multiplicateurs

Les effets ne valent pas tous leur valeur brute.

Exemple :

| Déclencheur | Multiplicateur |
|--------------|--------------:|
| Toujours actif | ×1.0 |
| Début du combat | ×0.9 |
| Après une attaque | ×1.3 |
| Lors d'un critique | ×0.8 |
| Sous 30% PV | ×0.6 |
| Une seule fois | ×0.5 |

---

# 5. Les conditions

Les conditions réduisent la valeur d'un effet.

Exemple :

| Condition | Multiplicateur |
|------------|--------------:|
| Aucune | ×1.0 |
| Arme adjacente | ×0.9 |
| Objet Food | ×0.8 |
| Build Poison | ×0.7 |
| Build Burn | ×0.7 |
| Nécessite un Critique | ×0.75 |

Plus une condition est difficile à satisfaire,
plus l'effet peut être puissant.

---

# 6. Les catégories de statistiques

## Statistiques primaires

- Dégâts
- PV
- Armure
- Mana
- Vitesse

Faciles à équilibrer.

---

## Statistiques multiplicatives

- Critique
- Dégâts critiques
- Vol de vie
- Multiplicateurs de dégâts

Attention :
elles deviennent plus fortes avec chaque nouvel objet.

---

## Statistiques de tempo

- Mana initial
- Réduction de cooldown
- Haste
- Action supplémentaire

Souvent les plus puissantes.

---

## Utilitaires

- Pioche
- Économie
- Génération de ressources
- Réduction du coût des cartes

Leur valeur dépend fortement du reste du jeu.

---

# 7. Les synergies

Ne jamais équilibrer un objet uniquement en isolation.

Exemple :

Anneau du Vampire

+8 Vol de Vie

↓

Moyen seul.

Excellent avec :

- attaque rapide
- multi-hit
- critique
- poison

Le coût de l'objet peut donc être inférieur à sa puissance maximale.

---

# 8. Inspiration : The Bazaar

The Bazaar semble équilibrer les objets via plusieurs leviers.

Pas uniquement leurs statistiques.

Principalement :

- cooldown
- taille
- rareté
- tags
- déclencheurs
- conditions
- synergies

Exemple :

Objet A

Haste 1 seconde

Cooldown 4 sec

Combat moyen : 20 sec

→ 5 activations

Si

1 Haste = 20 points

Alors :

5 × 20 = 100 points.

---

Même objet

Cooldown = 8 sec

↓

2,5 activations

↓

≈50 points

Le texte reste identique.

La puissance est divisée par deux.

---

# 9. Construire un langage de design

Créer peu de briques de base.

Exemple :

## Statistiques

- dégâts
- PV
- armure
- critique
- mana
- vitesse
- burn
- poison
- haste
- vol de vie

---

## Déclencheurs

- début du combat
- début du tour
- après une attaque
- après avoir subi des dégâts
- lors d'un critique
- lors d'une élimination
- utilisation d'une compétence
- toutes les X secondes
- sous X% PV
- à la mort

---

## Effets

- infliger des dégâts
- gagner de l'armure
- soigner
- gagner du mana
- piocher
- appliquer un statut
- invoquer
- réduire un cooldown
- copier un effet
- déclencher un autre objet

Quelques dizaines de briques suffisent à produire plusieurs centaines d'objets.

---

# 10. Tableur d'équilibrage

Créer un Google Sheets / Excel.

## Feuille : Valeur des statistiques

| Stat | Valeur |
|------|--------:|
| 10 dégâts | 10 |
| 10 armure | 10 |
| 1 poison | 5 |
| 1 burn | 4 |
| ... | ... |

---

## Feuille : Déclencheurs

| Trigger | Multiplicateur |
|----------|--------------:|
| Début combat | 0.9 |
| Toujours actif | 1 |
| Critique | 0.8 |

---

## Feuille : Conditions

| Condition | Multiplicateur |
|-----------|--------------:|
| Aucune | 1 |
| Food | 0.8 |
| Arme | 0.9 |

---

## Feuille : Objets

| Nom | Budget cible | Budget actuel | Écart |
|------|-------------:|--------------:|-------:|
| Épée rouillée | 100 | 97 | -3 |
| Bouclier lourd | 100 | 102 | +2 |

Cela permet de repérer immédiatement un objet beaucoup trop fort.

---

# 11. Vision long terme

L'objectif final n'est pas :

> "Créer des objets équilibrés."

Mais :

> "Créer un système capable d'équilibrer automatiquement de nouveaux objets."

Idéalement, l'éditeur d'objet devrait afficher automatiquement :

Budget cible : 120

Budget calculé : 116

Écart : -4

Cela permet de concevoir rapidement des centaines d'objets cohérents avant même les playtests.

Les tests serviront ensuite à ajuster les coefficients du système plutôt qu'à rééquilibrer chaque objet individuellement.

---

# Principes clés

✔ Équilibrer le système, pas les objets.

✔ Définir une monnaie de puissance.

✔ Donner un budget à chaque rareté.

✔ Les effets conditionnels peuvent être plus puissants.

✔ Les synergies justifient des objets faibles en apparence.

✔ Le cooldown est un levier majeur d'équilibrage.

✔ Les statistiques multiplicatives coûtent plus cher.

✔ Les statistiques de tempo sont souvent sous-estimées.

✔ Construire un langage de design avant de créer des centaines d'objets.

✔ Utiliser un tableur pour suivre automatiquement le budget des objets.
