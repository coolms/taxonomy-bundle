<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Bundle\Tests\Field;

use CoolMS\Taxonomy\Bundle\Field\TaxonomyFieldWidgetProvider;
use CoolMS\Taxonomy\Entity\TaxonomyTree;
use CoolMS\Taxonomy\Entity\TaxonomyTreeInterface;
use CoolMS\Taxonomy\Repository\TaxonomyTreeRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The `taxonomy` field widget is per-field: the tree it scopes to is read from
 * the field's `widget: { tree: <code> }` config, falling back to the platform's
 * primary `categories` tree.
 */
#[CoversClass(TaxonomyFieldWidgetProvider::class)]
final class TaxonomyFieldWidgetProviderTest extends TestCase
{
    #[Test]
    public function advertisesTheTaxonomyFieldType(): void
    {
        self::assertSame('taxonomy', $this->makeProvider([])->fieldType());
    }

    #[Test]
    public function defaultsToTheCategoriesTreeWhenNoFieldOverride(): void
    {
        $categories = new TaxonomyTree('Categories', 'categories');
        $widget = $this->makeProvider(['categories' => $categories])->widget();

        self::assertSame('taxonomy', $widget['kind']);
        self::assertSame('categories', $widget['tree']);
        self::assertSame($categories->id->toRfc4122(), $widget['treeId']);
        self::assertTrue($widget['multiple']);
    }

    #[Test]
    public function scopesToThePerFieldTreeDeclaredInWidgetConfig(): void
    {
        $regions = new TaxonomyTree('Regions', 'regions');
        $widget = $this->makeProvider(['regions' => $regions])
            ->widget(['widget' => ['tree' => 'regions']]);

        self::assertSame('regions', $widget['tree']);
        self::assertSame($regions->id->toRfc4122(), $widget['treeId']);
    }

    #[Test]
    public function fallsBackToDefaultWhenWidgetTreeIsBlankOrNotAString(): void
    {
        $categories = new TaxonomyTree('Categories', 'categories');
        $provider = $this->makeProvider(['categories' => $categories]);

        self::assertSame('categories', $provider->widget(['widget' => ['tree' => '']])['tree']);
        self::assertSame('categories', $provider->widget(['widget' => ['tree' => 123]])['tree']);
        self::assertSame('categories', $provider->widget(['widget' => 'not-an-array'])['tree']);
    }

    #[Test]
    public function treeIdIsNullWhenTheTargetTreeIsNotSeeded(): void
    {
        // Picker still renders (FE lists/creates once the tree exists); treeId
        // stays null until the tree is seeded.
        $widget = $this->makeProvider([])->widget(['widget' => ['tree' => 'regions']]);

        self::assertSame('regions', $widget['tree']);
        self::assertNull($widget['treeId']);
    }

    /**
     * @param array<string, TaxonomyTreeInterface> $treesByCode
     */
    private function makeProvider(array $treesByCode): TaxonomyFieldWidgetProvider
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')
            ->willReturnCallback(static fn (string $name): string => '/api/v1/' . $name);

        $treeRepository = $this->createStub(TaxonomyTreeRepositoryInterface::class);
        $treeRepository->method('findByCode')
            ->willReturnCallback(static fn (string $code): ?TaxonomyTreeInterface => $treesByCode[$code] ?? null);

        return new TaxonomyFieldWidgetProvider($urlGenerator, $treeRepository);
    }
}
