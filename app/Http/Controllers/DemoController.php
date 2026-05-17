<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class DemoController extends Controller
{
    public function index()
    {
        return view('demo');
    }

    public function action(Request $request)
    {
        $action = $request->input('action');
        $output = '';

        if ($action === 'stress_safe') {
            Artisan::call('app:stress safe 50');
            $output = Artisan::output();
        } elseif ($action === 'stress_unsafe') {
            Artisan::call('app:stress unsafe 50');
            $output = Artisan::output();
        } elseif ($action === 'benchmark') {
            $response = Http::get(url('/api/benchmark/summary'));
            $output = $response->body();
        }

        return view('demo', ['output' => $output]);
    }
}
