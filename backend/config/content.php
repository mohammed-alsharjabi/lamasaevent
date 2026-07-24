<?php

use App\Models\Area;
use App\Models\Article;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;

return [
    'resources' => [
        'articles' => [
            'model' => Article::class,
            'label' => 'المقالات',
            'singular' => 'مقال',
            'fields' => ['title', 'slug', 'topic', 'excerpt', 'content_blocks'],
            'seo' => true,
        ],
        'services' => [
            'model' => Service::class,
            'label' => 'الخدمات',
            'singular' => 'خدمة',
            'fields' => [
                'title', 'slug', 'service_category_id', 'excerpt',
                'content_blocks', 'sort_order',
            ],
            'seo' => true,
        ],
        'service-categories' => [
            'model' => ServiceCategory::class,
            'label' => 'تصنيفات الخدمات',
            'singular' => 'تصنيف',
            'fields' => ['title', 'slug', 'summary', 'content_blocks', 'sort_order'],
            'seo' => true,
        ],
        'areas' => [
            'model' => Area::class,
            'label' => 'المناطق',
            'singular' => 'منطقة',
            'fields' => ['title', 'slug', 'summary', 'content_blocks', 'sort_order'],
            'seo' => true,
        ],
        'pages' => [
            'model' => Page::class,
            'label' => 'الصفحات',
            'singular' => 'صفحة',
            'fields' => ['title', 'path', 'type', 'summary', 'content_blocks'],
            'seo' => true,
        ],
        'galleries' => [
            'model' => Gallery::class,
            'label' => 'معارض الصور',
            'singular' => 'معرض',
            'fields' => ['title', 'slug', 'description'],
            'seo' => false,
        ],
    ],
];
