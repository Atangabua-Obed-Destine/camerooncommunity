@extends('errors.layout')

@section('code', '500')
@section('title', \App\Support\Bilingual::t('Something went wrong on our side', 'Un problème est survenu de notre côté'))
@section('message', \App\Support\Bilingual::t('It isn\'t you. We\'ve been told, and you can try again in a moment.', 'Ce n\'est pas vous. Nous sommes prévenus ; réessayez dans un instant.'))
