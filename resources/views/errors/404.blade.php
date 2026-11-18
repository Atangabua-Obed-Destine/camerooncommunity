@extends('errors.layout')

@section('code', '404')
@section('title', \App\Support\Bilingual::t('We couldn\'t find that page', 'Page introuvable'))
@section('message', \App\Support\Bilingual::t('The link may be old, or the page may have been removed.', 'Le lien est peut-être ancien, ou la page a été supprimée.'))
