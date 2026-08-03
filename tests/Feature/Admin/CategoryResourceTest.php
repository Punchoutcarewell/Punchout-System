<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Modules\Admin\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Modules\Catalog\Models\Category;
use Livewire\Livewire;

it('lists categories', function () {
    actingAsAdmin();
    createTestCategory('Wound Care');

    Livewire::test(ListCategories::class)
        ->assertCanSeeTableRecords(Category::all());
});

it('creates a category with a parent', function () {
    actingAsAdmin();
    $parent = createTestCategory('Wound Care');

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Dressings',
            'slug' => 'dressings',
            'parent_id' => $parent->id,
            'position' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $category = Category::query()->where('slug', 'dressings')->firstOrFail();
    expect($category->parent_id)->toBe($parent->id);
});

it('rejects a duplicate slug', function () {
    actingAsAdmin();
    createTestCategory('Wound Care');

    Livewire::test(CreateCategory::class)
        ->fillForm(['name' => 'Wound Care Again', 'slug' => 'wound-care', 'position' => 0])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});
