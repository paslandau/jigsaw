@extends('_layouts.master')

@section('body')

<h1>Hello world!</h1>
<?php xdebug_break(); ?>
    {{print_r($jigsaw->getMeta(),true)}}
@endsection
