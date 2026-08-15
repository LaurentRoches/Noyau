<?php

declare(strict_types=1);

namespace App\Tests\Application\Factory;

use App\Application\Factory\ShopFactory;
use App\Domain\Enum\Rarity;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class ShopFactoryTest extends TestCase
{
    private function createRepository(): JsonItemRepository
    {
        $filePath = __DIR__ . '/../../../config/game/items.json';

        return new JsonItemRepository($filePath);
    }

    public function testCreateShopReturnsFourDistinctOffers(): void
    {
        $factory = new ShopFactory($this->createRepository());
        $randomizer = new Randomizer(new PcgOneseq128XslRr64(42));

        $shop = $factory->createShop($randomizer);

        $itemIds = array_map(
            static fn (int $i) => $shop->getOffers()[$i]->getItem()->id,
            range(0, 3),
        );

        self::assertCount(4, array_unique($itemIds));
    }

    public function testCreateShopNeverExceedsOneLegendaryOffer(): void
    {
        $factory = new ShopFactory($this->createRepository());

        for ($seed = 0; $seed < 200; $seed++) {
            $randomizer = new Randomizer(new PcgOneseq128XslRr64($seed));
            $shop = $factory->createShop($randomizer);

            $legendaryCount = count(array_filter(
                $shop->getOffers(),
                static fn ($offer) => $offer->getItem()->rarity === Rarity::LEGENDARY,
            ));

            self::assertLessThanOrEqual(1, $legendaryCount, "Seed {$seed} produced more than 1 legendary offer.");
        }
    }

    public function testCreateShopIsDeterministicForAGivenSeed(): void
    {
        $factory = new ShopFactory($this->createRepository());

        $shopA = $factory->createShop(new Randomizer(new PcgOneseq128XslRr64(1234)));
        $shopB = $factory->createShop(new Randomizer(new PcgOneseq128XslRr64(1234)));

        $idsA = array_map(static fn ($offer) => $offer->getItem()->id, $shopA->getOffers());
        $idsB = array_map(static fn ($offer) => $offer->getItem()->id, $shopB->getOffers());

        self::assertSame($idsA, $idsB);
    }
}
