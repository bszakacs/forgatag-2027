<?php

namespace App\Filament\Resources\ProgramItems\Pages;

use App\Enums\ProgramStatus;
use App\Enums\TranslationStatus;
use App\Filament\Resources\ProgramItems\ProgramItemResource;
use App\Models\ProgramItem;
use App\Models\User;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProgramItems extends ListRecords
{
    protected static string $resource = ProgramItemResource::class;

    /**
     * A fülek a jóváhagyási sor nézetei; a státuszlisták egy forrásból (ProgramStatus).
     *
     * KÖTELEZŐ ELLENSÚLY (CLAUDE.md 5.): az ELAVULT fordítás saját, piros számlálós fület kap — a közzétett programé is
     * (az a „Teendő” fülön nem lenne rajta, pedig a román oldalán épp az ilyen sor hordozhat téves időpontot vagy árat).
     */
    public function getTabs(): array
    {
        return [
            'teendo' => Tab::make('Teendő')
                ->badge(fn () => ProgramItem::whereIn('status', ProgramStatus::queueValues())->count() ?: null)
                ->badgeColor(ProgramStatus::PendingReview->color())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ProgramStatus::queueValues())),
            'elavult' => Tab::make('⚠ Elavult fordítás')
                ->badge(fn () => ProgramItem::query()->withOutdatedTranslation()->count() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->withOutdatedTranslation()),
            'forditas' => Tab::make('Átnézendő fordítás')
                ->badge(fn () => self::atnezendo(ProgramItem::query())->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => self::atnezendo($query)),
            'kint' => Tab::make('Közzétéve')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ProgramStatus::publicValues())),
            'mind' => Tab::make('Mind'),
        ];
    }

    /** Van még át nem nézett gépi fordítása (a fordító munkasora). */
    private static function atnezendo(Builder $query): Builder
    {
        return $query->whereIn('status', ProgramStatus::translatableValues())
            ->whereHas('translations', fn (Builder $t) => $t->where('status', TranslationStatus::MachineTranslated->value));
    }

    /** A fordító a saját munkasorán nyit (elöl az elavult); mindenki más a jóváhagyási soron. */
    public function getDefaultActiveTab(): string|int|null
    {
        $u = auth('web')->user();
        if ($u instanceof User && $u->role->canTranslate() && ! $u->role->canReviewPrograms()) {
            return ProgramItem::query()->withOutdatedTranslation()->exists() ? 'elavult' : 'forditas';
        }

        return 'teendo';
    }
}
