@extends('errors.layout')

@section('code', '403')
@section('title', \App\Support\Bilingual::t('You can\'t open this page', 'Vous ne pouvez pas ouvrir cette page'))
@section('message', \App\Support\Bilingual::t('It may be private, or your account may not have access.', 'Elle est peut-être privée, ou votre compte n\'y a pas accès.'))
