<?php

namespace App\Modules\Navigation\Services;

use App\Modules\Collections\Models\Entry;
use App\Modules\Navigation\Models\Navigation;
use App\Modules\Structure\Services\TreeService;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Navigation-specific tree operations (item shape, page-link URL
 * resolution, the 2-level depth limit) on top of the generic Structure
 * TreeService, which owns the actual tree storage/manipulation.
 */
class NavigationTreeService
{
    const MAX_DEPTH = 2;

    const TYPES = ['page', 'internal', 'external'];

    public function __construct(protected TreeService $trees) {}

    /**
     * @return array<int, array>
     */
    public function load(Navigation $navigation, ?string $locale = null): array
    {
        return $this->trees->load('navigation', $navigation->handle, $locale);
    }

    /**
     * @param  array<int, array>  $nodes
     */
    public function findItem(array $nodes, string $id): ?array
    {
        return $this->trees->findNode($nodes, $id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, array>
     */
    public function addItem(Navigation $navigation, ?string $parentId, array $attributes): array
    {
        $tree = $this->withAddedItem($this->load($navigation), $parentId, $attributes);

        $this->save($navigation, $tree);

        return $tree;
    }

    /**
     * @return array<int, array>
     */
    public function removeItem(Navigation $navigation, string $id): array
    {
        $tree = $this->withRemovedItem($this->load($navigation), $id);

        $this->save($navigation, $tree);

        return $tree;
    }

    /**
     * @return array<int, array>
     */
    public function moveItem(Navigation $navigation, string $id, ?string $newParentId, int $position): array
    {
        $tree = $this->withMovedItem($this->load($navigation), $id, $newParentId, $position);

        $this->save($navigation, $tree);

        return $tree;
    }

    /**
     * Pure (non-persisting) equivalents of the above, for a component that
     * batches several edits in memory before persisting them together via
     * save() — e.g. an explicit "Save" action instead of an autosave.
     *
     * @param  array<int, array>  $tree
     * @param  array<string, mixed>  $attributes
     * @return array<int, array>
     */
    public function withAddedItem(array $tree, ?string $parentId, array $attributes): array
    {
        $node = array_merge($this->normalize($attributes), [
            'id' => (string) Str::ulid(),
            'children' => [],
        ]);

        return $this->trees->addNode($tree, $parentId, $node, self::MAX_DEPTH);
    }

    /**
     * @param  array<int, array>  $tree
     * @return array<int, array>
     */
    public function withRemovedItem(array $tree, string $id): array
    {
        return $this->trees->removeNode($tree, $id);
    }

    /**
     * @param  array<int, array>  $tree
     * @return array<int, array>
     */
    public function withMovedItem(array $tree, string $id, ?string $newParentId, int $position): array
    {
        return $this->trees->moveNode($tree, $id, $newParentId, $position, self::MAX_DEPTH);
    }

    /**
     * @param  array<int, array>  $tree
     * @return array<int, array>
     */
    public function withDuplicatedItem(array $tree, string $id): array
    {
        return $this->trees->duplicateNode($tree, $id, fn (): string => (string) Str::ulid());
    }

    /**
     * Detaches an item's children and re-inserts them as root-level
     * siblings, clearing the item's own children array. Used when moving a
     * parent under another item would otherwise exceed the max depth.
     *
     * @param  array<int, array>  $tree
     * @return array<int, array>
     */
    public function withPromotedChildren(array $tree, string $id): array
    {
        $item = $this->findItem($tree, $id);

        if ($item === null || $item['children'] === []) {
            return $tree;
        }

        $children = $item['children'];
        $tree = $this->trees->updateNode($tree, $id, ['children' => []]);

        return [...$tree, ...$children];
    }

    /**
     * @param  array<int, array>  $tree
     * @return array<int, array>
     */
    public function withClearedChildren(array $tree, string $id): array
    {
        return $this->trees->updateNode($tree, $id, ['children' => []]);
    }

    /**
     * Resolves the effective URL for a tree item. For `page` items this is
     * derived live from the referenced Entry's slug, so a slug change is
     * reflected automatically without touching the stored nav tree.
     *
     * @param  array<string, mixed>  $item
     */
    public function resolveUrl(array $item): ?string
    {
        if ($item['type'] !== 'page') {
            return $item['url'] ?? null;
        }

        $slug = $item['page_id'] ? Entry::find($item['page_id'])?->slug : null;

        return $slug ? '/'.ltrim($slug, '/') : null;
    }

    /**
     * Items normally carry only blueprint-driven `data` (see
     * NavigationTreeEditor). The legacy type/title/url/target shape is kept
     * for navigations with no blueprint fields — only reachable for
     * protected navigations like sidebar-admin.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function normalize(array $attributes): array
    {
        if (! array_key_exists('type', $attributes)) {
            return ['data' => $attributes['data'] ?? []];
        }

        if (! in_array($attributes['type'], self::TYPES, true)) {
            throw new InvalidArgumentException('Invalid navigation item type.');
        }

        return [
            'type' => $attributes['type'],
            'title' => $attributes['title'],
            'target' => $attributes['target'] ?? '_self',
            'url' => $attributes['type'] !== 'page' ? ($attributes['url'] ?? null) : null,
            'page_id' => $attributes['type'] === 'page' ? ($attributes['page_id'] ?? null) : null,
            // Only meaningful for the protected "sidebar-admin" navigation
            // (see sidebar.blade.php) — the role handles (see
            // App\Modules\Users\Models\Role) allowed to see this link.
            // Empty means nobody except a Super Admin sees it. Ignored by
            // every other navigation.
            'roles' => $attributes['roles'] ?? [],
            'data' => $attributes['data'] ?? [],
        ];
    }

    /**
     * Persists a tree as-is. Used both internally by the mutation methods
     * above and directly by the Livewire component to commit inline field
     * edits it has already applied to its local tree copy.
     *
     * @param  array<int, array>  $tree
     */
    public function save(Navigation $navigation, array $tree, ?string $locale = null): void
    {
        $this->trees->save('navigation', $navigation->handle, $tree, $locale);
    }
}
