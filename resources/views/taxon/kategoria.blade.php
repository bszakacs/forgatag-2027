@extends('programok.lista')
@use('App\Support\Nyelv')
{{--
  EGY KATEGÓRIA LAPJA — a közös programlista-lap (programok/lista: a programkereső sorai, „Vissza a programkeresőhöz”).
  A programok a keresőével azonos szűréssel: ebben a kategóriában, minden nap (elsődleges vagy további kategória).
--}}
@php $nev = Nyelv::valaszt($k->name_hu, $k->name_ro); @endphp
@section('title', $nev)
@section('lista_cim'){{ $nev }}@endsection
