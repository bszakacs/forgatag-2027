@extends('bekuldes.layout')

@section('title', 'Saját beküldéseim')

@section('hero')
  <div class="eyebrow">{{ \App\Support\Festival::spanLabel() }} · Marosvásárhely</div>
  <p class="kicker">Programgazdák</p>
  <h1>Saját beküldéseim</h1>
  <p>A jóváhagyásig a programjaidat javíthatod. Jóváhagyás után már csak a szervezők módosíthatják.</p>
@endsection

@section('content')
  <div class="navrow">
    <a class="btn btn-ghost btn-sm" href="{{ route('bekuldes.kezdo') }}">{{ $nyitva ? 'Új program beküldése' : 'Programbeküldés' }}</a>
  </div>

  <div class="card">
    @if ($programok->isEmpty())
      <p class="help" style="margin:0">Még nem küldtél be programot.</p>
    @else
      <ul class="plist">
        @foreach ($programok as $p)
          @php
            $nyitott = $p->isSubmitterEditable();
            $vissza = $nyitott ? $p->openReturn() : null;
          @endphp
          <li>
            <div class="pmain">
              <div class="ptitle">{{ $p->title }}</div>
              <div class="pmeta">
                {{ $p->category?->name_hu ?? '—' }}
                @if ($p->occurrences->isNotEmpty())
                  · {{ $p->occurrences->map(fn ($o) => \App\Support\Festival::dayLabel(\Carbon\CarbonImmutable::parse($o->date)))->implode(', ') }}
                  · {{ substr($p->occurrences->first()->start_time, 0, 5) }}
                @endif
              </div>
              @if ($vissza)
                <div class="pmeta pvissza"><strong>A szervezők javítást kérnek:</strong> {{ $vissza->note }}</div>
              @endif
            </div>
            <span @class(['badge', 'open' => $nyitott, 'done' => ! $nyitott])>{{ $vissza ? 'Javítást kérnek' : ($nyitott ? 'Jóváhagyásra vár' : $p->status->label()) }}</span>
            @if ($nyitott && $nyitva)
              <a class="btn btn-ghost btn-sm" href="{{ route('bekuldes.szerkesztes', $p) }}">Javítás</a>
            @endif
          </li>
        @endforeach
      </ul>
    @endif
  </div>
  @if (! $nyitva)
    <p class="foot">{{ \App\Support\Submission::closedMessage() }}</p>
  @endif
@endsection
