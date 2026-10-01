<?php

namespace App\Modules\Collections\Services;

use App\Modules\Structure\Services\TreeService;

/**
 * Collection-specific tree operations (entries nested under a parent entry,
 * for "Structured" collections — see Collection::structured()) on top of the
 * generic Structure TreeService, which owns the actual tree storage. The
 * tree is per-site, mirroring how entries themselves are per-site.
 */
class CollectionTreeService
{
    public function __construct(protected TreeService $trees) {}

    /**
     * @return array<int, array>
     */
    public function load(string $collectionHandle, string $site): array
    {
        return $this->trees->load('collection', $collectionHandle, $site);
    }

    /**
     * The id of the given entry's parent, or null if it has none (including
     * when the entry isn't tracked in the tree yet).
     */
    public function parentIdOf(string $collectionHandle, string $site, string $entryId): ?string
    {
        return $this->findParentId($this->load($collectionHandle, $site), $entryId);
    }

    /**
     * Sets (or, with a null $parentId, clears) an entry's parent, adding it
     * to the tree first if it isn't tracked yet. Appends it as the last
     * child of its new parent. If the parent itself isn't tracked yet
     * either — e.g. it was created before this collection became
     * "Structured" — it's registered as a root first, since an untracked
     * entry is assumed top-level. Enforces the collection's max depth —
     * throws InvalidArgumentException if the move would exceed it (this
     * also rejects making an entry its own descendant, since extracting its
     * subtree removes the would-be parent from the tree first).
     */
    public function setParent(string $collectionHandle, string $site, string $entryId, ?string $parentId, ?int $maxDepth): void
    {
        $tree = $this->load($collectionHandle, $site);

        if ($parentId !== null && $this->trees->findNode($tree, $parentId) === null) {
            $tree = $this->trees->addNode($tree, null, ['id' => $parentId, 'children' => []]);
        }

        $tree = $this->trees->findNode($tree, $entryId)
            ? $this->trees->moveNode($tree, $entryId, $parentId, PHP_INT_MAX, $maxDepth)
            : $this->trees->addNode($tree, $parentId, ['id' => $entryId, 'children' => []], $maxDepth);

        $this->trees->save('collection', $collectionHandle, $tree, $site);
    }

    /**
     * Removes an entry (and, per TreeService::removeNode, its descendants'
     * tree placement) when it's deleted. A no-op if it was never tracked.
     */
    public function removeEntry(string $collectionHandle, string $site, string $entryId): void
    {
        $tree = $this->load($collectionHandle, $site);

        if ($this->trees->findNode($tree, $entryId) === null) {
            return;
        }

        $this->trees->save('collection', $collectionHandle, $this->trees->removeNode($tree, $entryId), $site);
    }

    /**
     * @param  array<int, array>  $nodes
     */
    protected function findParentId(array $nodes, string $id, ?string $parentId = null): ?string
    {
        foreach ($nodes as $node) {
            if ($node['id'] === $id) {
                return $parentId;
            }

            $found = $this->findParentId($node['children'] ?? [], $id, $node['id']);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
