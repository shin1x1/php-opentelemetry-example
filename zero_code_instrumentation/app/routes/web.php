<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/contents/{code}', function (string $code) {
    $content = DB::table('contents')->where('country_code', $code)->first();
    if ($content === null) {
        return response()->json(['error' => 'Not Found'], 404);
    }

    DB::transaction(function () use ($content) {
        DB::getPdo()->prepare('SELECT * FROM contents WHERE country_code = ?')->fetchAll();
    });

    DB::getPdo()->prepare('SELECT * FROM contents WHERE country_code = ?')->fetch();
    DB::getPdo()->prepare('SELECT * FROM contents WHERE country_code = ?')->fetchAll();
    DB::getPdo()->prepare('SELECT * FROM contents WHERE country_code = ?')->fetchColumn();

    DB::getPdo()->query('SELECT * FROM contents WHERE country_code = ' . DB::getPdo()->quote($content->country_code));
    DB::getPdo()->exec('UPDATE contents SET country_code=\'jp\'');

    $flag = Http::get('https://restcountries.com/v3.1/name/' . $content->country_code)
        ->throw()
        ->json('0.flag');

    return response()->json([
        'country_code' => $content->country_code,
        'name' => $content->name,
        'flag' => $flag,
    ]);
});
