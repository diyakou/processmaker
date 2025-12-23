@extends('layouts.layout')

@section('title')
    {{ __('Data Sources') }}
@endsection

@section('sidebar')
    @include('layouts.sidebar', ['sidebar'=> Menu::get('sidebar_processes')])
@endsection

@section('breadcrumbs')
    @include('shared.breadcrumbs', ['routes' => [
        __('Designer') => route('designer.index'),
        __('Data Sources') => null,
    ]])
@endsection

@section('content')
<div id="data-sources-app">
    <data-source-manager></data-source-manager>
</div>
@endsection

@section('js')
<script>
window.ProcessMaker = window.ProcessMaker || {};

new Vue({
    el: '#data-sources-app',
    components: {
        'data-source-manager': () => import('@/components/DataSourceManager.vue')
    }
});
</script>
@endsection