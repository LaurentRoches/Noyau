<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Application\GameRun;
use App\Domain\Model\Hero;

final class RunStatePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(GameRun $gameRun): array
    {
        $shop = $gameRun->getCurrentShop();

        return [
            'round' => $gameRun->getCurrentRound(),
            'victories' => $gameRun->getVictories(),
            'defeats' => $gameRun->getDefeats(),
            'isOver' => $gameRun->isOver(),
            'hasWon' => $gameRun->hasWon(),
            'wallet' => WalletPresenter::toArray($gameRun->getWallet()),
            'shop' => $shop !== null ? ShopPresenter::toArray($shop) : null,
            'inventory' => InventoryPresenter::toArray($gameRun->getInventory()),
            'stash' => StashPresenter::toArray($gameRun->getStash()),
            'roster' => array_map(
                static fn (Hero $hero): array => HeroPresenter::toArray($hero),
                $gameRun->getRoster(),
            ),
        ];
    }
}
