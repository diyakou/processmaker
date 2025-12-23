<?php

namespace App\Http\Controllers;

use App\Models\DataSource;
use Illuminate\Http\Request;
use ProcessMaker\Http\Controllers\Controller;

class DataSourceWebController extends Controller
{
    public function index()
    {
        return view('data-sources.index');
    }

    public function edit(DataSource $dataSource)
    {
        return view('data-sources.edit', compact('dataSource'));
    }
}