@extends('errors.layout')

@section('code', '405')
@section('icon', 'alert')
@section('title', __('This address does not open in a browser'))
@section('message', __('It is used by apps and servers, for example to send with the API. Go back to the home page to find your projects.'))
