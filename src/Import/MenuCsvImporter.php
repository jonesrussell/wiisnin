<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\MenuItem;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Taxonomy\Term;

/**
 * Imports a vendor menu from CSV into MenuItem entities. Columns:
 *   category, item, description, price_cents, available
 *
 * NOTE (see WAASEYAA-FRICTION.md): the framework's `structured-import` package
 * is GFM 2-column (prompt→value, single entity) oriented and does not fit a
 * multi-row CSV of many menu items, so this is a thin purpose-built importer
 * that reads CSV rows directly and resolves category names to menu_category
 * taxonomy terms. It's the additive onboarding path; app:seed stays the default.
 */
final class MenuCsvImporter
{
    public function __construct(
        private readonly EntityRepositoryInterface $menuItems,
        private readonly EntityRepositoryInterface $terms,
    ) {}

    /**
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function importFile(string $path, int $vendorId): array
    {
        $csv = is_file($path) ? (string) file_get_contents($path) : '';
        if ($csv === '') {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ["cannot read CSV: {$path}"]];
        }
        return $this->import($csv, $vendorId);
    }

    /**
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function import(string $csv, int $vendorId): array
    {
        $lines = array_values(array_filter(array_map('rtrim', explode("\n", trim($csv))), static fn (string $l): bool => $l !== ''));
        if ($lines === []) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['empty CSV']];
        }

        $header = array_map(static fn (string $h): string => strtolower(trim($h)), str_getcsv(array_shift($lines), ',', '"', ''));
        $idx = array_flip($header);
        foreach (['category', 'item', 'price_cents'] as $required) {
            if (!isset($idx[$required])) {
                return ['imported' => 0, 'skipped' => 0, 'errors' => ["missing required column: {$required}"]];
            }
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        foreach ($lines as $lineNo => $line) {
            $row = str_getcsv($line, ',', '"', '');
            $item = trim((string) ($row[$idx['item']] ?? ''));
            if ($item === '') {
                $skipped++;
                continue;
            }
            $category = trim((string) ($row[$idx['category']] ?? ''));
            $availRaw = strtolower(trim((string) ($row[$idx['available']] ?? '1')));
            $available = !in_array($availRaw, ['0', 'false', 'no', 'n', ''], true);

            $this->menuItems->save(new MenuItem([
                'vendor_id' => $vendorId,
                'category_tid' => $category !== '' ? $this->ensureTerm('menu_category', $category) : null,
                'name' => $item,
                'description' => trim((string) ($row[$idx['description']] ?? '')),
                'price_cents' => (int) ($row[$idx['price_cents']] ?? 0),
                'available' => $available ? 1 : 0,
            ]));
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function ensureTerm(string $vid, string $name): int
    {
        $existing = $this->terms->findBy(['vid' => $vid, 'name' => $name], null, 1);
        if ($existing !== []) {
            return (int) $existing[0]->id();
        }
        $term = new Term(['vid' => $vid, 'name' => $name, 'status' => true]);
        $this->terms->save($term);
        return (int) $term->id();
    }
}
