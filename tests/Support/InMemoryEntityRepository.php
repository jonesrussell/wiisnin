<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Minimal in-memory EntityRepository for unit tests.
 *
 * Implements the parts of the contract the order flow uses (find, findMany,
 * findBy, save, delete, exists, count); revision/translation methods are not
 * implemented. findBy matches criteria against the entity's get() values, the
 * same way the real json_extract query does for FieldStorage::Data fields.
 */
final class InMemoryEntityRepository implements EntityRepositoryInterface
{
    /** @var array<int, EntityInterface> */
    private array $store = [];

    private int $nextId = 1;

    public function find(string $id, ?string $langcode = null, bool $fallback = false): ?EntityInterface
    {
        return $this->store[(int) $id] ?? null;
    }

    public function findMany(array $ids, ?string $langcode = null, bool $fallback = false): array
    {
        $out = [];
        foreach ($ids as $id) {
            $entity = $this->store[(int) $id] ?? null;
            if ($entity !== null) {
                $out[] = $entity;
            }
        }
        return $out;
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null): array
    {
        $out = [];
        foreach ($this->store as $entity) {
            $match = true;
            foreach ($criteria as $field => $value) {
                /** @phpstan-ignore-next-line dynamic get() on EntityInterface */
                if ($entity->get($field) != $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $out[] = $entity;
            }
            if ($limit !== null && count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    public function save(EntityInterface $entity, bool $validate = true): int
    {
        $id = $entity->id();
        if ($id === null || $id === '' || (int) $id === 0) {
            $newId = $this->nextId++;
            /** @phpstan-ignore-next-line dynamic set() on EntityInterface */
            $entity->set('id', $newId);
            $this->store[$newId] = $entity;
            return 1; // SAVED_NEW
        }
        $this->store[(int) $id] = $entity;
        return 2; // SAVED_UPDATED
    }

    public function delete(EntityInterface $entity): void
    {
        unset($this->store[(int) $entity->id()]);
    }

    public function exists(string $id): bool
    {
        return isset($this->store[(int) $id]);
    }

    public function count(array $criteria = []): int
    {
        return $criteria === [] ? count($this->store) : count($this->findBy($criteria));
    }

    public function loadRevision(string $entityId, int $revisionId): ?EntityInterface
    {
        throw new \RuntimeException('Not implemented in the in-memory test double.');
    }

    public function rollback(string $entityId, int $targetRevisionId): EntityInterface
    {
        throw new \RuntimeException('Not implemented in the in-memory test double.');
    }

    public function listRevisions(string $entityId): array
    {
        throw new \RuntimeException('Not implemented in the in-memory test double.');
    }

    public function setCurrentRevision(string $entityId, int $revisionId): EntityInterface
    {
        throw new \RuntimeException('Not implemented in the in-memory test double.');
    }

    public function saveMany(array $entities, bool $validate = true): array
    {
        return array_map(fn (EntityInterface $e): int => $this->save($e, $validate), $entities);
    }

    public function deleteMany(array $entities): int
    {
        foreach ($entities as $entity) {
            $this->delete($entity);
        }
        return count($entities);
    }

    public function findTranslations(EntityInterface $entity): array
    {
        return [];
    }
}
