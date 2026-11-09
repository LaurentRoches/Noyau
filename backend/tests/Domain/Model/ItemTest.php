<?php

declare(strict_types=1);

namespace App\Tests\Domain\Model;

use App\Domain\Enum\ActionType;
use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Enum\Target;
use App\Domain\Enum\Trigger;
use App\Domain\Model\Action;
use App\Domain\Model\Effect;
use App\Domain\Model\Item;
use PHPUnit\Framework\TestCase;

/**
 * Un objet ne porte qu'un déclencheur (D-23, `02` §7.7) : tous ses effets
 * partagent le même. La règle vit dans le constructeur (option A, session 024),
 * pour qu'aucun chemin d'entrée — chargement du catalogue ou relecture d'une
 * archive — ne puisse la contourner.
 */
final class ItemTest extends TestCase
{
    /**
     * Le message nomme l'objet et les déclencheurs dans l'ordre déclaré : ce
     * sont les deux informations qui rendent une ligne de catalogue réparable.
     */
    public function testAnItemWhoseEffectsCarryTwoDifferentTriggersIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item "twin_trigger" carries more than one trigger (EVERY_N_TICKS, ON_COMBAT_START)');

        new Item(
            id: 'twin_trigger',
            name: 'Twin Trigger',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 20,
            effects: [
                self::createEffect(Trigger::EVERY_N_TICKS),
                self::createEffect(Trigger::ON_COMBAT_START),
            ],
        );
    }

    /**
     * L'autre moitié de la règle : un objet peut toujours avoir plusieurs
     * effets, exécutés au même moment dans l'ordre déclaré (`02` §7.2, §7.7).
     * Aucun objet du catalogue n'en a plus d'un aujourd'hui, si bien que rien
     * d'autre ne garderait cette borne contre une règle trop stricte.
     */
    public function testAnItemMayCarrySeveralEffectsUnderTheSameTrigger(): void
    {
        $effects = [
            self::createEffect(Trigger::EVERY_N_TICKS),
            self::createEffect(Trigger::EVERY_N_TICKS),
        ];

        $item = new Item(
            id: 'twin_effect',
            name: 'Twin Effect',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 20,
            effects: $effects,
        );

        self::assertSame($effects, $item->effects);
    }

    private static function createEffect(Trigger $trigger): Effect
    {
        return new Effect(
            trigger: $trigger,
            actions: [new Action(type: ActionType::DEAL_DAMAGE, value: 5, target: Target::ENEMY)],
        );
    }
}
