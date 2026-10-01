<?php

namespace App\Modules\Users\Services;

use App\Models\Taxonomy;
use App\Modules\Collections\Models\Collection;
use App\Modules\Globals\Models\GlobalSet;
use App\Modules\Media\Models\AssetContainer;
use App\Modules\Navigation\Models\Navigation;
use App\Modules\Sites\Models\Site;

/**
 * Every permission checkbox a role can grant, structured to mirror
 * Statamic's own Roles permission tree (https://statamic.dev/control-panel/permissions):
 * a "Control Panel" group of general capabilities, one group per Sites,
 * plus one group per CMS area (Collections, Navigation, Globals,
 * Taxonomies, Assets, Users, Forms) that combines a blanket "configure X"
 * permission with checkboxes generated per resource that actually exists
 * — so, e.g., a "blog" collection automatically gets its own view/edit/
 * create/delete/publish/reorder checkboxes without hardcoding it here.
 *
 * Nothing in the app enforces these yet (see PermissionRegistry's caller,
 * User::hasPermission()) — this registry only defines what a role CAN be
 * granted; wiring individual routes/actions to check it is separate work.
 */
class PermissionRegistry
{
    /**
     * @return array<int, array{group: string, permissions: array<int, array{value: string, label: string, description: string|null, children: array}>}>
     */
    public function groups(): array
    {
        return array_filter([
            $this->controlPanelGroup(),
            $this->sitesGroup(),
            $this->collectionsGroup(),
            $this->navigationGroup(),
            $this->globalsGroup(),
            $this->taxonomiesGroup(),
            $this->assetsGroup(),
            $this->usersGroup(),
            $this->formsGroup(),
            $this->activityLogGroup(),
        ], fn (array $group): bool => $group['permissions'] !== []);
    }

    /**
     * @return array<int, string>
     */
    public function values(): array
    {
        return collect($this->groups())
            ->flatMap(fn (array $group): array => $this->flatten($group['permissions']))
            ->pluck('value')
            ->all();
    }

    /**
     * Every value in a group's permission tree, top-level and nested
     * children alike — used both by values() (for validation) and by the
     * role form's "Check all" button (which needs a group's full value
     * list, not just its top-level ones).
     *
     * @param  array<int, array{value: string, label: string, description: string|null, children: array}>  $permissions
     * @return array<int, array{value: string, label: string, description: string|null, children: array}>
     */
    public function flatten(array $permissions): array
    {
        $flat = [];

        foreach ($permissions as $permission) {
            $flat[] = $permission;
            $flat = array_merge($flat, $this->flatten($permission['children']));
        }

        return $flat;
    }

    protected function controlPanelGroup(): array
    {
        return [
            'group' => 'Control Panel',
            'permissions' => [
                $this->permission('access cp', 'Access the Control Panel', "Allows access into the control panel, but doesn't guarantee anything can be done once inside."),
                $this->permission('configure sites', 'Configure Sites', 'Ability to configure sites when multi-site is enabled.'),
                $this->permission('configure fields', 'Configure Fields', 'Ability to edit blueprints, fieldsets, and their fields.'),
                $this->permission('configure form fields', 'Configure Form Fields', 'Ability to edit form blueprints, fieldsets, and their fields.'),
                $this->permission('manage preferences', 'Manage Preferences', 'Ability to customize global and role-specific preferences.'),
            ],
        ];
    }

    protected function sitesGroup(): array
    {
        return [
            'group' => 'Sites',
            'permissions' => Site::orderBy('order')->get()
                ->map(fn (Site $site): array => $this->permission("access {$site->handle} site", "Access {$site->name} ({$site->handle}) site"))
                ->all(),
        ];
    }

    protected function collectionsGroup(): array
    {
        $permissions = [
            $this->permission('configure collections', 'Configure Collections', 'Grants access to all collection related permissions'),
        ];

        foreach (Collection::orderBy('handle')->get() as $collection) {
            $children = [
                $this->permission("edit {$collection->handle} entries", 'Edit entries'),
                $this->permission("create {$collection->handle} entries", 'Create new entries'),
                $this->permission("delete {$collection->handle} entries", 'Delete entries'),
                $this->permission("publish {$collection->handle} entries", 'Manage publish state', 'Ability to change from draft to published and vice versa'),
            ];

            if ($collection->orderable()) {
                $children[] = $this->permission("reorder {$collection->handle} entries", 'Reorder entries', 'Enables drag and drop reordering');
            }

            // Statamic-style "other authors'" permissions, backed by the SEO
            // tab's Meta Author field on Entry (see Entry::ownedBy()) — a
            // recorded Meta Author names the entry's owner, and everyone
            // else needs these variants to edit/delete/publish it. Entries
            // without a Meta Author are shared content open to any holder
            // of the base permission.
            $children[] = $this->permission("edit other authors {$collection->handle} entries", "Edit other authors' entries");
            $children[] = $this->permission("publish other authors {$collection->handle} entries", "Manage publish state of other authors' entries");
            $children[] = $this->permission("delete other authors {$collection->handle} entries", "Delete other authors' entries");

            $permissions[] = $this->permission("view {$collection->handle} entries", "View {$collection->title} entries", null, $children);
        }

        return ['group' => 'Collections', 'permissions' => $permissions];
    }

    protected function navigationGroup(): array
    {
        $permissions = [
            $this->permission('configure navigation', 'Configure Navigation', 'Grants access to all navigation related permissions'),
        ];

        foreach (Navigation::orderBy('handle')->get() as $navigation) {
            $children = [
                $this->permission("edit {$navigation->handle} navigation", 'Edit navigation'),
                $this->permission("delete {$navigation->handle} navigation", 'Delete navigation'),
            ];

            $permissions[] = $this->permission("view {$navigation->handle} navigation", "View {$navigation->title} navigation", null, $children);
        }

        return ['group' => 'Navigation', 'permissions' => $permissions];
    }

    protected function globalsGroup(): array
    {
        $permissions = [
            $this->permission('configure globals', 'Configure Globals', 'Grants access to all global related permissions'),
        ];

        foreach (GlobalSet::orderBy('handle')->get() as $globalSet) {
            $children = [
                $this->permission("edit {$globalSet->handle} globals", 'Edit globals'),
                $this->permission("delete {$globalSet->handle} globals", 'Delete globals'),
            ];

            $permissions[] = $this->permission("view {$globalSet->handle} globals", "View {$globalSet->title} globals", null, $children);
        }

        return ['group' => 'Globals', 'permissions' => $permissions];
    }

    protected function taxonomiesGroup(): array
    {
        $permissions = [
            $this->permission('configure taxonomies', 'Configure Taxonomies', 'Grants access to all taxonomy related permissions'),
        ];

        foreach (Taxonomy::orderBy('handle')->get() as $taxonomy) {
            $children = [
                $this->permission("edit {$taxonomy->handle} terms", 'Edit terms'),
                $this->permission("create {$taxonomy->handle} terms", 'Create new terms'),
                $this->permission("delete {$taxonomy->handle} terms", 'Delete terms'),
            ];

            $permissions[] = $this->permission("view {$taxonomy->handle} terms", "View {$taxonomy->title} terms", null, $children);
        }

        return ['group' => 'Taxonomies', 'permissions' => $permissions];
    }

    protected function assetsGroup(): array
    {
        $permissions = [
            $this->permission('configure assets', 'Configure Asset Containers', 'Grants access to all asset related permissions'),
        ];

        foreach (AssetContainer::orderBy('handle')->get() as $container) {
            $children = [
                $this->permission("upload {$container->handle} assets", 'Upload new assets'),
                $this->permission("edit folders {$container->handle} assets", 'Edit folders'),
                $this->permission("edit {$container->handle} assets", 'Edit assets'),
                $this->permission("move {$container->handle} assets", 'Move assets'),
                $this->permission("rename {$container->handle} assets", 'Rename assets'),
                $this->permission("delete {$container->handle} assets", 'Delete assets'),
            ];

            $permissions[] = $this->permission("view {$container->handle} assets", "View {$container->title} assets", null, $children);
        }

        return ['group' => 'Assets', 'permissions' => $permissions];
    }

    protected function usersGroup(): array
    {
        return [
            'group' => 'Users',
            'permissions' => [
                $this->permission('view users', 'View users'),
                $this->permission('edit users', 'Edit users'),
                $this->permission('create users', 'Create users'),
                $this->permission('delete users', 'Delete users'),
                $this->permission('ban users', 'Ban users', 'Prevents a user from logging in until unbanned.'),
                $this->permission('change passwords', 'Change passwords'),
                $this->permission('send password reset', 'Send password reset link', 'Emails the user a link to reset their own password.'),
                $this->permission('assign user groups', 'Assign groups to users'),
                $this->permission('assign user roles', 'Assign roles to users'),
                $this->permission('edit user groups', 'Edit groups'),
                $this->permission('edit roles', 'Edit roles'),
            ],
        ];
    }

    /**
     * Forms have no per-form submission model yet in this app (see
     * FormsServiceProvider — the module is currently config-only, no Form
     * Eloquent model), so only the blanket permission is offered here,
     * unlike Statamic's per-form "view/delete X submissions" checkboxes.
     */
    protected function formsGroup(): array
    {
        return [
            'group' => 'Forms',
            'permissions' => [
                $this->permission('configure forms', 'Configure forms', 'Grants access to all form related permissions'),
            ],
        ];
    }

    protected function activityLogGroup(): array
    {
        return [
            'group' => 'Activity Log',
            'permissions' => [
                $this->permission('view activity log', 'Access activity log', 'Ability to open the admin activity log page.'),
                $this->permission('view activity log detail', 'View entry detail', 'Ability to view the full before/after change details of a logged entry.'),
                $this->permission('export activity log', 'Export activity log', 'Ability to export activity log entries (per month/year) as a JSON file.'),
                $this->permission('import activity log', 'Import activity log', 'Ability to restore activity log entries back from a previously exported file.'),
            ],
        ];
    }

    /**
     * @param  array<int, array{value: string, label: string, description: string|null, children: array}>  $children
     * @return array{value: string, label: string, description: string|null, children: array}
     */
    protected function permission(string $value, string $label, ?string $description = null, array $children = []): array
    {
        return ['value' => $value, 'label' => $label, 'description' => $description, 'children' => $children];
    }
}
