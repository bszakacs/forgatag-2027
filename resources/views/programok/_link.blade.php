@use('App\Support\Nyelv')
@use('App\Support\ProgramSzoveg')
{{--
  A program címe linkként. href: a program LAPJA (keresőoptimalizálás, JS nélkül is működik). A keresőben ($szures
  van) htmx-szel a FELUGRÓ ABLAKOT tölti be (2026): a címsorba a megosztható `?program=<slug>` kerül; az `idopont` azt
  mondja meg, melyik időpont sávja látszik az ablak képén.
--}}
@php $szures ??= null; @endphp
<a class="pk-cardlink" href="{{ Nyelv::ut('/program/' . $p->slug) }}"
   @if ($szures)
     hx-get="{{ $szures->mas(['program' => $p->slug, 'napszakok' => $szures->napszakok])->url() }}&amp;idopont={{ $sor->idopont->id }}"
     hx-target="#pk-modal" hx-select="#pk-modal" hx-swap="outerHTML" hx-push-url="true"
   @endif
>{{ ProgramSzoveg::cim($p) }}</a>
