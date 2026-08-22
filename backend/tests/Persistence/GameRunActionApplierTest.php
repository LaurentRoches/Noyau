<?php

declare(strict_types=1);

namespace App\Tests\Persistence;

use App\Persistence\GameRunActionApplier;
use App\Persistence\GameRunActionType;
use App\Tests\Support\CreatesRealGameRun;
use PHPUnit\Framework\TestCase;

final class GameRunActionApplierTest extends TestCase
{
    use CreatesRealGameRun;

    public function testItAppliesOpenShopAction(): void
    {
        $gameRun = $this->createRealGameRun(seed: 42);
        $applier = new GameRunActionApplier();

        $applier->apply($gameRun, GameRunActionType::OPEN_SHOP, []);

        self::assertNotNull($gameRun->getCurrentShop());
    }

    public function testItAppliesPurchaseAction(): void
    {
        $gameRun = $this->createRealGameRun(seed: 42);
        $applier = new GameRunActionApplier();

        $applier->apply($gameRun, GameRunActionType::OPEN_SHOP, []);
        $shop = $gameRun->getCurrentShop();
        $price = $shop->getOffers()[0]->getPrice();
        $goldBefore = $gameRun->getWallet()->getBalance();

        $applier->apply($gameRun, GameRunActionType::PURCHASE, ['slotIndex' => 0]);

        self::assertTrue($shop->getOffers()[0]->isPurchased());
        self::assertSame($goldBefore - $price, $gameRun->getWallet()->getBalance());
    }
}
