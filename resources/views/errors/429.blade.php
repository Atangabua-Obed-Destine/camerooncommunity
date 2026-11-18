@extends('errors.layout')

@section('code', '429')
@section('title', \App\Support\Bilingual::t('Too many attempts', 'Trop de tentatives'))
@section('message', \App\Support\Bilingual::t('Please wait a minute, then try again.', 'Patientez une minute, puis réessayez.'))
