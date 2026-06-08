<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\MenuItem;
use App\Entity\Order;
use App\Entity\Vendor;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

/**
 * Wiisnin commerce wiring: CLI commands for self-check and seeding.
 *
 * (Notification dispatcher + access handler wiring are added alongside as the
 * order flow lands.)
 */
final class CommerceServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void {}

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'app:selfcheck',
            description: 'Round-trip the commerce entities through storage to verify schema, blob-field findBy, and the reserved-word "order" table.',
            handler: function (CliIO $io): int {
                $etm = $this->resolve(EntityTypeManager::class);
                if (!$etm instanceof EntityTypeManager) {
                    $io->error('Self-check requires a booted kernel (EntityTypeManager).');
                    return 1;
                }

                $vendors = $etm->getRepository('vendor');
                $items = $etm->getRepository('menu_item');
                $orders = $etm->getRepository('order');

                // 1. Save a vendor.
                $vendor = new Vendor([
                    'name' => 'Selfcheck Diner',
                    'slug' => 'selfcheck-diner',
                    'is_open' => 1,
                    'contact_email' => 'selfcheck@example.test',
                ]);
                $vendors->save($vendor);
                $vendorId = (int) $vendor->id();
                $io->writeln("vendor saved id={$vendorId}");

                // 2. Save two menu items for that vendor.
                foreach (['Scone' => 300, 'Indian taco' => 1200] as $name => $cents) {
                    $item = new MenuItem([
                        'vendor_id' => $vendorId,
                        'name' => $name,
                        'price_cents' => $cents,
                        'available' => 1,
                    ]);
                    $items->save($item);
                }

                // 3. findBy on a _data (blob) field.
                $found = $items->findBy(['vendor_id' => $vendorId]);
                $io->writeln('findBy(vendor_id) returned ' . count($found) . ' item(s)');

                // 4. Reserved-word table "order": save + reload.
                $order = new Order([
                    'reference' => 'WSN-SELFCHECK',
                    'customer_uid' => 1,
                    'vendor_id' => $vendorId,
                    'status' => 'placed',
                    'fulfilment' => 'pickup',
                    'contact_phone' => '705-000-0000',
                    'payment_method' => 'cash',
                    'total_cents' => 1500,
                ]);
                $orders->save($order);
                $orderId = (int) $order->id();
                $reloaded = $orders->find((string) $orderId);
                $status = $reloaded?->get('status');
                $io->writeln("order saved id={$orderId}, reloaded status=" . var_export($status, true));

                // 5. Cleanup so the check stays idempotent.
                foreach ($found as $item) {
                    $items->delete($item);
                }
                if ($reloaded !== null) {
                    $orders->delete($reloaded);
                }
                $vendors->delete($vendor);

                $ok = count($found) === 2 && $status === 'placed';
                $io->writeln($ok ? 'SELFCHECK OK' : 'SELFCHECK FAILED');

                return $ok ? 0 : 1;
            },
        );
    }
}
