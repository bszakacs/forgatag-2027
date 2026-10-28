<?php

namespace App\Models;

use App\Enums\MenuItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Menüpont a fában (parent_id; legfeljebb App\Support\MenuTree::MAX_DEPTH szint). Nyelvenként elrejthető
 * (show_hu / show_ro). A fa átrendezése EGY helyen: App\Support\MenuTree.
 */
class MenuItem extends Model
{
    protected $fillable = ['menu_id', 'parent_id', 'sort', 'label_hu', 'label_ro', 'type', 'page_id', 'url',
        'new_tab', 'show_hu', 'show_ro'];

    protected $attributes = ['type' => 'link', 'show_hu' => true, 'show_ro' => true, 'new_tab' => false];

    protected function casts(): array
    {
        return [
            'type' => MenuItemType::class,
            'new_tab' => 'boolean',
            'show_hu' => 'boolean',
            'show_ro' => 'boolean',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** Hová visz (a szerkesztő ezt mutatja; a publikus fejléc is ezt használja majd). */
    public function target(): ?string
    {
        return match ($this->type) {
            MenuItemType::Page => $this->page?->path(),
            MenuItemType::Link => $this->url,
            MenuItemType::Group => null,
        };
    }
}
