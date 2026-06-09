<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\MenuItem;
use App\Import\MenuCsvImporter;
use App\Tests\Support\InMemoryEntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The CSV menu import path: rows become MenuItem entities for a vendor.
 */
final class MenuCsvImporterTest extends TestCase
{
    #[Test]
    public function it_imports_csv_rows_into_menu_items(): void
    {
        $menuItems = new InMemoryEntityRepository();
        $terms = new InMemoryEntityRepository();
        $importer = new MenuCsvImporter($menuItems, $terms);

        $csv = <<<CSV
        category,item,description,price_cents,available
        Native cuisine,Scone,Fresh fried bannock,400,1
        Grill,Poutine,Gravy & curds,1000,1
        Daily specials,Corn soup,House favourite,800,0
        CSV;

        $result = $importer->import($csv, vendorId: 7);

        $this->assertSame(3, $result['imported']);
        $this->assertSame([], $result['errors']);

        $items = $menuItems->findBy(['vendor_id' => 7]);
        $this->assertCount(3, $items);

        $byName = [];
        foreach ($items as $item) {
            \assert($item instanceof MenuItem);
            $byName[$item->getName()] = $item;
        }
        $this->assertSame(400, $byName['Scone']->getPriceCents());
        $this->assertTrue($byName['Poutine']->isAvailable());
        $this->assertFalse($byName['Corn soup']->isAvailable());
    }

    #[Test]
    public function it_reports_a_missing_required_column(): void
    {
        $importer = new MenuCsvImporter(new InMemoryEntityRepository(), new InMemoryEntityRepository());
        $result = $importer->import("item,price_cents\nScone,400", vendorId: 1);
        $this->assertSame(0, $result['imported']);
        $this->assertNotSame([], $result['errors']);
    }
}
