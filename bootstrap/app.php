<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckSystemExpired;

ini_set('memory_limit', '1024M'); // ← TAMBAHKAN DI SINI

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'check.expired' => CheckSystemExpired::class,
            
        ]);

        // Endpoint auto print kiosk tidak pakai CSRF (diamankan pakai AUTO_PRINT_TOKEN),
        // karena halaman kiosk dibuka berhari-hari dan session/CSRF pasti expired.
        $middleware->validateCsrfTokens(except: [
            'kanbantmmins/auto-print/*',
        ]);
        
        $middleware->redirectGuestsTo('/login');
        
        $middleware->redirectUsersTo(function ($request) {
            $user = $request->user();
            
            if ($user->role === 'lp') {
                return route('shippings.checkingLp');
            }
            
            if ($user->role === 'scanner') {
                return route('preparations.scan');
            }
            
            return route('preparations.index');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();