<?php

declare(strict_types=1);

namespace App\Tests\Determinism;

use App\Domain\Engine\CombatLogSerializer;
use App\Domain\Engine\EngineVersion;
use App\Domain\Engine\Simulator;
use App\Domain\Snapshot\BoardHydrator;
use PHPUnit\Framework\TestCase;

/**
 * Le rejeu octet pour octet d'un combat de référence (NF-01, D-16, `04` §6).
 *
 * **L'ancre de non-régression du moteur.** Quatre fichiers figés dans
 * `fixtures/reference-combat/` — deux enveloppes archivées, le journal canonique
 * qu'elles ont produit, et la provenance de la capture. Le test les relit, rebâtit
 * les deux plateaux et refait le combat. Si un seul octet du journal bouge, le
 * moteur a changé.
 *
 * **Ce qui rougit ce fichier, et ce qu'il faut en faire.** Soit `EngineVersion` a
 * été relevée délibérément, et il faut régénérer la fixture avec
 * `php capture-reference-combat.php --seed=46 --round=6` ; soit elle ne l'a pas
 * été, et c'est le moteur qu'il faut regarder — jamais le test. Même doctrine que
 * les 2 129 octets de `BoardSnapshotTest` et que le bouclier de référence de
 * `SimulatorTest`.
 *
 * **La fixture sort d'une vraie run**, graine 46 manche 6 : roster constitué par
 * les offres, objets achetés en boutique, adversaire scripté, décoration par
 * compétence, or au lancement. Rien n'y est monté à la main.
 *
 * **C'est un combat miroir**, et c'est ce qui en fait une bonne ancre. Le
 * catalogue ne contient qu'un Vestige : les deux camps portent donc le **même
 * identifiant**, et le journal ne les distingue que par `targetSide`. Toute
 * attribution qui repasserait par l'identifiant plutôt que par l'identité d'objet
 * confondrait les deux côtés, et ce fichier le verrait (D-19, `04` §3.6).
 *
 * **Ce qu'elle ne couvre pas.** Six des dix types d'événement. `ENRAGE_DAMAGE_DEALT`
 * exige 450 ticks, `RESOLUTION_TIEBREAK` l'absence de KO, et les pulsations de
 * `REGEN` et `WARD` deux objets légendaires rarement achetés tôt. Un balayage de
 * soixante-dix combats PvE — six graines, douze manches — n'a produit que des KO,
 * le plus long à 192 ticks : la limite est structurelle, pas une affaire de
 * chance. Ces quatre chemins restent gardés par `SimulatorTest`, qui fige leurs
 * charges utiles exactes ; ce qui leur manque est le passage par l'archive.
 */
final class ReferenceCombatReplayTest extends TestCase
{
    /**
     * Le saut de ligne final n'appartient pas à la charge utile.
     *
     * Les trois fichiers de données portent la chaîne canonique **exacte** que le
     * système produit. Le script de capture leur ajoute un saut de ligne final
     * parce qu'un fichier qui n'en a pas est un piège connu de ce dépôt
     * (`06` §4.4) ; il est retiré ici, explicitement, plutôt que toléré par un
     * `trim` qui avalerait aussi une vraie altération.
     */
    private function fixture(string $name): string
    {
        $path = __DIR__ . '/fixtures/reference-combat/' . $name;
        $contents = file_get_contents($path);

        self::assertIsString($contents, sprintf('Fixture introuvable : %s', $path));

        return rtrim($contents, "\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(): array
    {
        /** @var array<string, mixed> $meta */
        $meta = json_decode($this->fixture('meta.json'), true, 512, JSON_THROW_ON_ERROR);

        return $meta;
    }

    /**
     * **Le test qui porte le chantier.**
     *
     * Une enveloppe archivée, relue sans catalogue ni base, rebâtie en plateau, et
     * resimulée avec la graine de combat enregistrée, doit rendre exactement le
     * journal qu'elle a produit la première fois.
     *
     * Les assertions descendent du grossier au fin : résolution, vainqueur, ticks,
     * nombre d'événements, puis la chaîne entière. Le diff de PHPUnit sur dix
     * kilo-octets est illisible — les quatre premières nomment la panne avant
     * qu'on y arrive.
     */
    public function testTheReferenceCombatReplaysByteForByte(): void
    {
        $meta = $this->meta();
        $expected = $this->fixture('combat-log.json');

        $result = (new Simulator())->run(
            BoardHydrator::fromCanonicalJson($this->fixture('board-a.json')),
            BoardHydrator::fromCanonicalJson($this->fixture('board-b.json')),
            (string) $meta['combatSeed'],
        );

        self::assertSame($meta['resolution'], $result->resolution->value);
        self::assertSame($meta['winnerSide'], $result->sideOf($result->winner)->value);
        self::assertSame($meta['totalTicks'], $result->totalTicks);
        self::assertSame(
            count(json_decode($expected, true, 512, JSON_THROW_ON_ERROR)['events']),
            $result->log->count(),
        );
        self::assertSame($expected, CombatLogSerializer::serialize($result->log));
    }

    /**
     * L'ordre dans lequel on présente les deux archives n'a aucune importance.
     *
     * C'est la garantie du commit précédent, vérifiée ici **à travers l'archive**
     * et non plus sur des plateaux montés à la main : l'attribution des côtés vient
     * de la comparaison des photographies, et l'ordre des effets d'un objet vient
     * de l'objet. Ni l'un ni l'autre ne dépend de l'ordre des arguments.
     *
     * Sans cette propriété, un corpus PvP serait inexploitable : rien ne dit qu'un
     * appariement présentera les deux plateaux dans l'ordre où ils ont été
     * archivés.
     */
    public function testTheReplayDoesNotDependOnTheOrderTheArchivedBoardsAreRead(): void
    {
        $meta = $this->meta();

        $reversed = (new Simulator())->run(
            BoardHydrator::fromCanonicalJson($this->fixture('board-b.json')),
            BoardHydrator::fromCanonicalJson($this->fixture('board-a.json')),
            (string) $meta['combatSeed'],
        );

        self::assertSame($meta['winnerSide'], $reversed->sideOf($reversed->winner)->value);
        self::assertSame($this->fixture('combat-log.json'), CombatLogSerializer::serialize($reversed->log));
    }

    /**
     * L'alarme lisible, avant le diff de dix kilo-octets.
     *
     * Relever `EngineVersion` sans régénérer la fixture ferait rougir le test
     * principal par une comparaison de chaînes géantes, ce qui ne dit pas ce qui
     * s'est passé. Celui-ci le dit en une ligne.
     *
     * Il ne défend pas contre une dérive du moteur — c'est le rôle du précédent —
     * mais contre une régénération oubliée, qui est l'erreur probable.
     */
    public function testTheFixtureWasCapturedUnderTheCurrentEngineVersion(): void
    {
        self::assertSame(
            EngineVersion::CURRENT,
            $this->meta()['engineVersion'],
            'EngineVersion a bougé : régénérer la fixture avec capture-reference-combat.php.',
        );
    }

    /**
     * La propriété qui fait la valeur de cette fixture, épinglée pour qu'une
     * régénération future ne la perde pas en silence.
     *
     * Les deux camps portent le même identifiant de Vestige — le catalogue n'en a
     * qu'un —, et le journal ne les sépare que par `targetSide`. Une attribution
     * des côtés indexée sur l'identifiant confondrait les deux, et le journal
     * deviendrait faux pour l'un des deux joueurs (D-19).
     */
    public function testTheReferenceCombatIsAMirrorTheSideLabelsMustSeparate(): void
    {
        $boardA = BoardHydrator::fromCanonicalJson($this->fixture('board-a.json'));
        $boardB = BoardHydrator::fromCanonicalJson($this->fixture('board-b.json'));

        self::assertSame(
            $boardA->getVestige()->getId(),
            $boardB->getVestige()->getId(),
            'Cette fixture vaut par son miroir : régénérée sur un catalogue à plusieurs Vestiges, elle le perdrait.',
        );

        $log = $this->fixture('combat-log.json');
        self::assertStringContainsString('"targetSide":"A"', $log);
        self::assertStringContainsString('"targetSide":"B"', $log);
    }

    /**
     * Les archives portent une version de contenu, et le rejeu ne la regarde pas.
     *
     * C'est la première borne de D-16 : un fantôme archivé avant un rééquilibrage
     * rejoue avec **ses** chiffres. Le test principal l'a déjà démontré en
     * rejouant sans jamais ouvrir `config/game` ; celui-ci vérifie qu'il y avait
     * bien quelque chose à ignorer, faute de quoi la démonstration serait vide.
     */
    public function testTheArchivedEnvelopesCarryAContentVersionTheReplayIgnores(): void
    {
        self::assertStringContainsString('"contentVersion":', $this->fixture('board-a.json'));
        self::assertStringContainsString('"contentVersion":', $this->fixture('board-b.json'));
        self::assertNotSame('', (string) $this->meta()['contentVersion']);
    }
}
