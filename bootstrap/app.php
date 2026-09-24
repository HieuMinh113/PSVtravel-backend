<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // App chạy sau nginx (mạng Docker nội bộ). Nếu không khai báo, $request->ip()
        // sẽ trả về IP của nginx cho MỌI khách → mọi rate-limit và log IP tính chung
        // theo 1 IP. Tin proxy ở dải nội bộ để lấy đúng IP thật của khách qua
        // X-Forwarded-For (chỉ nginx nội bộ mới tới được app nên an toàn, không spoof
        // được từ ngoài).
        $middleware->trustProxies(
            at: ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
