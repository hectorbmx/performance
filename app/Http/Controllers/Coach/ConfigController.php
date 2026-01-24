<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;

class ConfigController extends Controller
{
    public function index()
    {
        return view('coach.config.index');
    }
}
