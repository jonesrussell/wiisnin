<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\MenuItem;
use App\Entity\Order;
use App\Entity\Vendor;
use App\Access\VendorStaffDirectory;
use App\Notification\Channel\MercureChannel;
use App\Notification\Channel\SmsChannel;
use App\Seed\WiisninSeeder;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Mail\MailerInterface;
use Waaseyaa\Mercure\MercurePublisher;
use Waaseyaa\Notification\Channel\MailChannel;
use Waaseyaa\Notification\NotificationDispatcher;
use Waaseyaa\Queue\QueueInterface;

/**
 * Wiisnin commerce wiring: the new-order NotificationDispatcher (Mercure + mail
 * + SMS-stub channels) and CLI commands for self-check and seeding.
 */
final class CommerceServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void
    {
        // The kernel auto-discovers #[PolicyAttribute] access policies and
        // instantiates them via the container, so their VendorStaffDirectory
        // dependency must be bound before boot. (See WAASEYAA-FRICTION.md.)
        $this->singleton(VendorStaffDirectory::class, function (): VendorStaffDirectory {
            $etm = $this->resolve(EntityTypeManager::class);
            \assert($etm instanceof EntityTypeManager);

            return new VendorStaffDirectory(
                $etm->getRepository('vendor'),
                $etm->getRepository('group_membership'),
            );
        });

        // A dispatcher that notifies vendors over Mercure (real-time) and mail,
        // plus an inert SMS channel so adding 'sms' to a notification is safe.
        // The notification package's own dispatcher only wires mail + database;
        // we build ours with the Mercure channel this app adds.
        $this->singleton(NotificationDispatcher::class, function (): NotificationDispatcher {
            $channels = [];

            try {
                $mailer = $this->resolve(MailerInterface::class);
                if ($mailer instanceof MailerInterface) {
                    $channels['mail'] = new MailChannel($mailer);
                }
            } catch (\Throwable) {
                // Mail not configured; skip the channel.
            }

            try {
                $publisher = $this->resolve(MercurePublisher::class);
                if ($publisher instanceof MercurePublisher) {
                    $channels['mercure'] = new MercureChannel($publisher);
                }
            } catch (\Throwable) {
                // Mercure not configured; skip the channel.
            }

            // SMS is a stub this session (no sender) — inert but present.
            $channels['sms'] = new SmsChannel(null);

            $queue = $this->resolve(QueueInterface::class);
            \assert($queue instanceof QueueInterface);

            return new NotificationDispatcher($queue, $channels);
        });
    }

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'app:seed',
            description: 'Seed the pilot vendor (Meedjims Foodland, Sagamok), its menu, and the community + menu-category taxonomy. Idempotent. Prices are placeholders.',
            handler: function (CliIO $io): int {
                $etm = $this->resolve(EntityTypeManager::class);
                if (!$etm instanceof EntityTypeManager) {
                    $io->error('Seeding requires a booted kernel (EntityTypeManager).');
                    return 1;
                }

                $seeder = new WiisninSeeder(
                    $etm->getRepository('taxonomy_term'),
                    $etm->getRepository('vendor'),
                    $etm->getRepository('menu_item'),
                    $etm->getRepository('group'),
                    $etm->getRepository('group_membership'),
                );

                return $seeder->run($io);
            },
        );

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

                $vendor = new Vendor([
                    'name' => 'Selfcheck Diner',
                    'slug' => 'selfcheck-diner',
                    'is_open' => 1,
                    'contact_email' => 'selfcheck@example.test',
                ]);
                $vendors->save($vendor);
                $vendorId = (int) $vendor->id();

                foreach (['Scone' => 300, 'Indian taco' => 1200] as $name => $cents) {
                    $items->save(new MenuItem([
                        'vendor_id' => $vendorId,
                        'name' => $name,
                        'price_cents' => $cents,
                        'available' => 1,
                    ]));
                }

                $found = $items->findBy(['vendor_id' => $vendorId]);

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
