<?php

namespace App\Modules\Structure\Services;

use App\Modules\Structure\Models\Tree;
use InvalidArgumentException;

/**
 * Generic manipulation of nested trees (nodes shaped as
 * ['id' => string, 'children' => array, ...]) persisted in the shared
 * `trees` table. Used by both the Collections and Navigation modules so
 * tree/order logic lives in one place, per this project's module ownership
 * rules.
 *
 * @phpstan-type Node array{id: string, children: array<int, array>}
 */
class TreeService
{
    /**
     * @return array<int, array>
     */
    public function load(string $type, string $handle, ?string $locale = null): array
    {
        return Tree::query()
            ->where('type', $type)
            ->where('handle', $handle)
            ->where('locale', $locale)
            ->first()?->tree ?? [];
    }

    /**
     * @param  array<int, array>  $nodes
     */
    public function save(string $type, string $handle, array $nodes, ?string $locale = null): void
    {
        Tree::updateOrCreate(
            ['type' => $type, 'handle' => $handle, 'locale' => $locale],
            ['tree' => $nodes]
        );
    }

    /**
     * @param  array<int, array>  $nodes
     * @return array<int, array>|null
     */
    public function findNode(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if ($node['id'] === $id) {
                return $node;
            }

            $found = $this->findNode($node['children'] ?? [], $id);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array>  $nodes
     * @param  array<string, mixed>  $node
     * @return array<int, array>
     */
    public function addNode(array $nodes, ?string $parentId, array $node, ?int $maxDepth = null): array
    {
        $node['children'] ??= [];

        if ($parentId === null) {
            $nodes[] = $node;

            return $nodes;
        }

        $parentDepth = $this->depthOf($nodes, $parentId)
            ?? throw new InvalidArgumentException("Parent node not found: {$parentId}");

        if ($maxDepth !== null && $parentDepth + 1 > $maxDepth) {
            throw new InvalidArgumentException('Maximum tree depth reached.');
        }

        return $this->mapNode($nodes, $parentId, function (array $parent) use ($node): array {
            $parent['children'][] = $node;

            return $parent;
        });
    }

    /**
     * @param  array<int, array>  $nodes
     * @param  array<string, mixed>  $attributes
     * @return array<int, array>
     */
    public function updateNode(array $nodes, string $id, array $attributes): array
    {
        return $this->mapNode($nodes, $id, fn (array $node): array => array_merge($node, $attributes));
    }

    /**
     * Removes a node and, recursively, all of its children.
     *
     * @param  array<int, array>  $nodes
     * @return array<int, array>
     */
    public function removeNode(array $nodes, string $id): array
    {
        $remaining = [];

        foreach ($nodes as $node) {
            if ($node['id'] === $id) {
                continue;
            }

            $node['children'] = $this->removeNode($node['children'] ?? [], $id);
            $remaining[] = $node;
        }

        return $remaining;
    }

    /**
     * Moves a node to a new parent (null = root) at the given position among
     * its new siblings, validating that neither the node's new depth nor its
     * own subtree would exceed $maxDepth.
     *
     * @param  array<int, array>  $nodes
     * @return array<int, array>
     */
    public function moveNode(array $nodes, string $id, ?string $newParentId, int $position, ?int $maxDepth = null): array
    {
        [$nodes, $node] = $this->extractNode($nodes, $id);

        if ($node === null) {
            throw new InvalidArgumentException("Node not found: {$id}");
        }

        $targetDepth = 1;

        if ($newParentId !== null) {
            $targetDepth = ($this->depthOf($nodes, $newParentId)
                ?? throw new InvalidArgumentException("Parent node not found: {$newParentId}")) + 1;
        }

        if ($maxDepth !== null && $targetDepth + $this->heightOf($node) - 1 > $maxDepth) {
            throw new InvalidArgumentException('Moving this item here would exceed the maximum tree depth.');
        }

        if ($newParentId === null) {
            array_splice($nodes, $position, 0, [$node]);

            return $nodes;
        }

        return $this->mapNode($nodes, $newParentId, function (array $parent) use ($node, $position): array {
            $children = $parent['children'] ?? [];
            array_splice($children, $position, 0, [$node]);
            $parent['children'] = $children;

            return $parent;
        });
    }

    /**
     * Swaps a node with its previous/next sibling, wherever it is nested.
     * No-op if the node is already at that end of its sibling list.
     *
     * @param  array<int, array>  $nodes
     * @return array<int, array>
     */
    public function swapSibling(array $nodes, string $id, string $direction): array
    {
        $index = $this->indexOf($nodes, $id);

        if ($index !== null) {
            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($target >= 0 && $target < count($nodes)) {
                [$nodes[$index], $nodes[$target]] = [$nodes[$target], $nodes[$index]];
            }

            return $nodes;
        }

        foreach ($nodes as $i => $node) {
            $nodes[$i]['children'] = $this->swapSibling($node['children'] ?? [], $id, $direction);
        }

        return $nodes;
    }

    /**
     * Duplicates a node (and, recursively, its children) with freshly
     * generated ids, inserting the copy right after the original among its
     * siblings.
     *
     * @param  array<int, array>  $nodes
     * @return array<int, array>
     */
    public function duplicateNode(array $nodes, string $id, callable $freshId): array
    {
        $node = $this->findNode($nodes, $id);

        if ($node === null) {
            throw new InvalidArgumentException("Node not found: {$id}");
        }

        return $this->insertAfter($nodes, $id, $this->withFreshIds($node, $freshId));
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    protected function withFreshIds(array $node, callable $freshId): array
    {
        $node['id'] = $freshId();
        $node['children'] = array_map(
            fn (array $child): array => $this->withFreshIds($child, $freshId),
            $node['children'] ?? []
        );

        return $node;
    }

    /**
     * @param  array<int, array>  $nodes
     * @param  array<string, mixed>  $newNode
     * @return array<int, array>
     */
    protected function insertAfter(array $nodes, string $id, array $newNode): array
    {
        $result = [];

        foreach ($nodes as $node) {
            if ($node['id'] !== $id) {
                $node['children'] = $this->insertAfter($node['children'] ?? [], $id, $newNode);
            }

            $result[] = $node;

            if ($node['id'] === $id) {
                $result[] = $newNode;
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array>  $nodes
     */
    protected function indexOf(array $nodes, string $id): ?int
    {
        foreach ($nodes as $i => $node) {
            if ($node['id'] === $id) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array>  $nodes
     */
    protected function depthOf(array $nodes, string $id, int $depth = 1): ?int
    {
        foreach ($nodes as $node) {
            if ($node['id'] === $id) {
                return $depth;
            }

            $found = $this->depthOf($node['children'] ?? [], $id, $depth + 1);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function heightOf(array $node): int
    {
        $children = $node['children'] ?? [];

        if ($children === []) {
            return 1;
        }

        return 1 + max(array_map(fn (array $child): int => $this->heightOf($child), $children));
    }

    /**
     * Removes a node from anywhere in the tree and returns [$remainingTree, $extractedNode].
     *
     * @param  array<int, array>  $nodes
     * @return array{0: array<int, array>, 1: array<string, mixed>|null}
     */
    protected function extractNode(array $nodes, string $id): array
    {
        $extracted = null;
        $remaining = [];

        foreach ($nodes as $node) {
            if ($extracted === null && $node['id'] === $id) {
                $extracted = $node;

                continue;
            }

            [$children, $childExtracted] = $this->extractNode($node['children'] ?? [], $id);
            $node['children'] = $children;

            if ($childExtracted !== null) {
                $extracted = $childExtracted;
            }

            $remaining[] = $node;
        }

        return [$remaining, $extracted];
    }

    /**
     * Applies a callback to the node matching $id, wherever it is nested.
     *
     * @param  array<int, array>  $nodes
     * @return array<int, array>
     */
    protected function mapNode(array $nodes, string $id, callable $callback): array
    {
        foreach ($nodes as $i => $node) {
            if ($node['id'] === $id) {
                $nodes[$i] = $callback($node);

                return $nodes;
            }

            $nodes[$i]['children'] = $this->mapNode($node['children'] ?? [], $id, $callback);
        }

        return $nodes;
    }
}
