@extends('errors.layout')

@section('code', '419')
@section('title', \App\Support\Bilingual::t('Your session has expired', 'Votre session a expiré'))
@section('message', \App\Support\Bilingual::t('Go back, reload the page and try again.', 'Revenez en arrière, rechargez la page et réessayez.'))
