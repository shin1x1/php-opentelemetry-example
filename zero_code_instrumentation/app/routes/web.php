<?php

use Illuminate\Support\Facades\Config;
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

    $stmt = DB::getPdo()->prepare('SELECT * FROM contents WHERE country_code = ?');
    DB::transaction(function () use ($content, $stmt) {
        foreach (['jp', 'en'] as $i => $code) {
            if ($i === 0) {
                $stmt->bindValue(1, $code);
            }
            $stmt->execute();
        }
    });

    DB::getPdo()->query('SELECT * FROM contents WHERE country_code = ' . DB::getPdo()->quote($content->country_code));
    DB::getPdo()->exec('UPDATE contents SET country_code=\'jp\'');

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
//    $mysqli = new mysqli(Config::get('database.connections.mysql.host'), Config::get('database.connections.mysql.username'), Config::get('database.connections.mysql.password'), Config::get('database.connections.mysql.database'));
//    $mysqli->query('SELECT * FROM contents WHERE country_code = \'' . $mysqli->real_escape_string($content->country_code) . '\'');
//    $stmt = $mysqli->prepare('SELECT * FROM contents WHERE country_code = ?');
//    $stmt->execute(['ja']);
//
//    $stmt = $mysqli->stmt_init();
//    $stmt->prepare('SELECT name FROM contents WHERE country_code = ?');
//    $stmt->execute(['ja']);

    $mysqli = mysqli_connect(Config::get('database.connections.mysql.host'), Config::get('database.connections.mysql.username'), Config::get('database.connections.mysql.password'), Config::get('database.connections.mysql.database'));
    mysqli_query($mysqli, 'SELECT * FROM contents WHERE country_code = \'' . $mysqli->real_escape_string($content->country_code) . '\'');

    $stmt = mysqli_prepare($mysqli, 'SELECT * FROM contents WHERE country_code = ?');
    $stmt->execute(['ja']);

    $stmt = $mysqli->stmt_init();
    $stmt->prepare('SELECT name FROM contents WHERE country_code = ?');
    $stmt->execute(['ja']);



    $flag = Http::get('https://restcountries.com/v3.1/name/' . $content->country_code)
        ->throw()
        ->json('0.flag');

    return response()->json([
        'country_code' => $content->country_code,
        'name' => $content->name,
        'flag' => $flag,
    ]);
});
