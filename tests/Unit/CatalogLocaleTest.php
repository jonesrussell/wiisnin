<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Catalog\Catalog;
use App\Entity\Vendor;
use App\Tests\Support\InMemoryEntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Per-entity i18n seam: when the interface language is Nishnaabemwin ('oj') and a
 * *_oj field is filled, the localized value is returned; otherwise it falls back
 * to English. (Real oj content is left blank pending community translation.)
 */
final class CatalogLocaleTest extends TestCase
{
    private function catalog(InMemoryEntityRepository $vendors): Catalog
    {
        return new Catalog($vendors, new InMemoryEntityRepository(), new InMemoryEntityRepository());
    }

    #[Test]
    public function oj_name_is_used_when_present_else_english_fallback(): void
    {
        $vendors = new InMemoryEntityRepository();
        $translated = new Vendor(['name' => 'Corn Soup Kitchen', 'name_oj' => 'Mandaamin-naboob', 'slug' => 'csk', 'is_partner' => 1]);
        $untranslated = new Vendor(['name' => 'Back Home Bistro', 'slug' => 'bhb']);
        $vendors->save($translated);
        $vendors->save($untranslated);

        $en = $this->catalog($vendors)->vendorsNear(null, null, null, null, 'en');
        $oj = $this->catalog($vendors)->vendorsNear(null, null, null, null, 'oj');

        $enNames = array_column($en, 'name');
        $ojNames = array_column($oj, 'name');

        $this->assertContains('Corn Soup Kitchen', $enNames);
        $this->assertContains('Mandaamin-naboob', $ojNames);          // translated → oj
        $this->assertContains('Back Home Bistro', $ojNames);          // no oj → English fallback
        $this->assertNotContains('Corn Soup Kitchen', $ojNames);
    }
}
