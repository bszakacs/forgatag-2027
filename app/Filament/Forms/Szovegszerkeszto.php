<?php

namespace App\Filament\Forms;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\Textarea;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * A saját tartalom (hír, aloldal-blokk) szövegszerkesztője — EGY helyen, hogy minden szövegmező ugyanúgy nézzen ki.
 *
 * Valódi WYSIWYG (a Filament RichEditor, TipTap): úgy látszik, ahogy kint megjelenik; a tárolt érték HTML.
 * Saját „HTML” gomb: ablakban mutatja és szerkeszthetővé teszi a forrást; visszaíráskor a szerkesztő újra
 * feldolgozza, ezért csak az marad meg, amit a szerkesztő ismer (ismeretlen elem, szkript kiesik).
 * ⚠ A publikus sablon ennek ellenére SZŰRVE jelenítse meg (a kérés a böngészőből jön, megkerülhető).
 *
 * Kép NEM tölthető fel a szövegbe (`fileAttachments(false)`): a képek a médiatárban élnek, a lapon kép-blokkal
 * (a szövegbe ágyazott feltöltés a médiatáron kívülre kerülne, jogállás-adatok nélkül).
 */
class Szovegszerkeszto
{
    public const HTML_GOMB = 'htmlForras';

    public static function make(string $mezo): RichEditor
    {
        return RichEditor::make($mezo)
            ->fileAttachments(false)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'link'],
                ['h2', 'h3'],
                ['bulletList', 'orderedList', 'blockquote'],
                ['undo', 'redo'],
                [self::HTML_GOMB],
            ])
            ->tools([
                RichEditorTool::make(self::HTML_GOMB)
                    ->label('HTML')
                    ->icon(Heroicon::CodeBracket)
                    // A szerkesztő PILLANATNYI tartalma (a mentetlen módosítással együtt).
                    ->action(arguments: '{ html: $getEditor().getHTML() }'),
            ])
            ->registerActions([
                Action::make(self::HTML_GOMB)
                    ->modalHeading('HTML-forrás')
                    ->modalDescription('Itt a szöveg HTML-kódja látszik, és át is írható. Az „Alkalmazás” visszaírja a szerkesztőbe; a szerkesztő csak az általa ismert elemeket tartja meg.')
                    ->modalWidth(Width::FourExtraLarge)
                    ->modalSubmitActionLabel('Alkalmazás')
                    ->fillForm(fn (array $arguments): array => ['html' => self::tagol((string) ($arguments['html'] ?? ''))])
                    ->schema([
                        Textarea::make('html')->hiddenLabel()->rows(18)
                            ->extraInputAttributes(['style' => 'font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.8125rem;line-height:1.5', 'spellcheck' => 'false']),
                    ])
                    ->action(function (array $data, RichEditor $component): void {
                        $component->runCommands([EditorCommand::make('setContent', arguments: [(string) ($data['html'] ?? '')])]);
                    }),
            ]);
    }

    /**
     * Olvashatóbb forrás: minden blokkelem új sorba (a szerkesztő egy sorban adja vissza), és az alapértelmezett
     * igazítás (`style="text-align: start;"`, minden bekezdésen) kimarad — visszaíráskor a szerkesztő úgyis ezt veszi.
     */
    private static function tagol(string $html): string
    {
        $html = str_replace(' style="text-align: start;"', '', $html);

        return trim(preg_replace('#(</(?:p|h[1-6]|li|ul|ol|blockquote)>)(?!\n)#', "$1\n", $html));
    }
}
