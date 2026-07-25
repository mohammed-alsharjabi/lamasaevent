<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = $this->permissions();

        foreach ($permissions as $group => $items) {
            foreach ($items as $slug => $name) {
                Permission::updateOrCreate(
                    ['slug' => "{$group}.{$slug}"],
                    ['name' => $name, 'group' => $group],
                );
            }
        }

        $roles = [
            'super-admin' => ['name' => 'مدير النظام', 'permissions' => ['*']],
            'content-manager' => [
                'name' => 'مدير المحتوى',
                'permissions' => [
                    'articles.*', 'article-categories.*', 'services.*',
                    'service-categories.*', 'areas.*', 'pages.*', 'galleries.*',
                    'media.*', 'menus.*', 'redirects.*', 'settings.view',
                    'settings.update', 'sitemap.*', 'audit.view',
                ],
            ],
            'editor' => [
                'name' => 'محرر',
                'permissions' => [
                    'articles.view', 'articles.create', 'articles.update',
                    'articles.seo', 'article-categories.view',
                    'services.view', 'services.create', 'services.update',
                    'services.seo', 'service-categories.view', 'areas.view',
                    'pages.view', 'pages.create', 'pages.update', 'pages.seo',
                    'galleries.view', 'galleries.create', 'galleries.update',
                    'media.view', 'media.create', 'media.update',
                ],
            ],
        ];

        foreach ($roles as $slug => $definition) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $definition['name'], 'is_system' => true],
            );
            $role->permissions()->sync($this->permissionIdsFor($definition['permissions']));
        }

        if (! app()->environment('local')) {
            $this->command?->info('Local administrator was not seeded outside APP_ENV=local.');

            return;
        }

        $email = env('LOCAL_ADMIN_EMAIL', 'admin@lams-event.local');
        $password = env('LOCAL_ADMIN_PASSWORD');

        if (blank($password)) {
            $this->command?->warn('Set LOCAL_ADMIN_PASSWORD to create the local administrator.');

            return;
        }

        $user = User::updateOrCreate(['email' => $email], [
            'name' => env('LOCAL_ADMIN_NAME', 'مدير لمسة المحلي'),
            'password' => $password,
            'is_admin' => true,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->roles()->sync([
            Role::where('slug', 'super-admin')->sole()->id,
        ]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function permissions(): array
    {
        $content = [
            'view' => 'عرض',
            'create' => 'إنشاء',
            'update' => 'تعديل',
            'delete' => 'حذف',
            'restore' => 'استعادة',
            'force-delete' => 'حذف نهائي',
            'publish' => 'نشر',
            'unpublish' => 'إلغاء النشر',
            'seo' => 'إدارة SEO',
        ];

        $basic = [
            'view' => 'عرض',
            'create' => 'إنشاء',
            'update' => 'تعديل',
            'delete' => 'حذف',
        ];

        return [
            'articles' => $content,
            'article-categories' => $basic,
            'services' => $content,
            'service-categories' => $basic,
            'areas' => $content,
            'pages' => $content,
            'galleries' => $content,
            'media' => $basic,
            'menus' => $basic,
            'redirects' => $basic,
            'settings' => ['view' => 'عرض الإعدادات', 'update' => 'تعديل الإعدادات'],
            'sitemap' => ['view' => 'عرض خريطة الموقع', 'update' => 'تعديل خريطة الموقع'],
            'users' => $basic,
            'audit' => ['view' => 'عرض سجل التدقيق'],
            'publish-jobs' => ['view' => 'عرض عمليات النشر', 'run' => 'تشغيل النشر'],
        ];
    }

    /**
     * @param  list<string>  $patterns
     * @return list<int>
     */
    private function permissionIdsFor(array $patterns): array
    {
        $query = Permission::query();

        $query->where(function ($query) use ($patterns): void {
            foreach ($patterns as $pattern) {
                $pattern === '*'
                    ? $query->orWhereNotNull('id')
                    : $query->orWhere('slug', 'like', str_replace('*', '%', $pattern));
            }
        });

        return $query->pluck('id')->all();
    }
}
