@extends('_layouts.master')

@section('body')

<h1>Hello world!</h1>Foo
<?php xdebug_break(); ?>
    {{$jigsaw->test}}
@endsection
