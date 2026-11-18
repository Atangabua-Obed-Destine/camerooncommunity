@extends('errors.layout')

@section('code', '503')
@section('title', \App\Support\Bilingual::t('We\'ll be right back', 'Nous revenons tout de suite'))
@section('message', \App\Support\Bilingual::t('Cameroon Network is being updated. It only takes a few minutes.', 'Cameroon Network est en cours de mise à jour. Cela ne prend que quelques minutes.'))
