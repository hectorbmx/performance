<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LegalController extends Controller
{
    public function privacy()
    {
        // Si no quieres que se indexe en Google, lo marcamos en headers:
        return response()
            ->view('legal.privacy-policy')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
    public function support()
{
    return response()
        ->view('legal.support')
        ->header('X-Robots-Tag', 'noindex, nofollow');
}
 public function marketing()
    {
        return view('legal.marketing');
    }
}
