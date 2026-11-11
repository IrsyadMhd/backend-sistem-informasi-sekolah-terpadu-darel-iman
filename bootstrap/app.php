<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'foundation.readonly' => \App\Http\Middleware\EnsureFoundationReadOnly::class,
        ]);
        $middleware->prependToGroup('api', \App\Http\Middleware\ExtractBearerTokenFromQuery::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\EnsureFoundationReadOnly::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Database\QueryException $e, \Illuminate\Http\Request $request) {
            $msg = $e->getMessage();
            $code = (string) $e->getCode();

            if ($request->is('api/*') || $request->wantsJson()) {
                if (str_contains($msg, 'invalid input syntax for type uuid')) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 'INVALID_UUID_FORMAT',
                        'message' => 'Data tidak ditemukan atau format ID tidak valid.',
                    ], 404);
                }

                // Deteksi kegagalan koneksi database pada query
                $isConnectionError = in_array($code, ['08006', '08001', '08004', '2002', 'HY000'], true)
                    || str_contains($msg, 'could not connect to server')
                    || str_contains($msg, 'Connection refused')
                    || str_contains($msg, 'Connection timed out')
                    || str_contains($msg, 'No such file or directory');

                if ($isConnectionError) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 'DATABASE_CONNECTION_ERROR',
                        'message' => 'Layanan database tidak dapat terhubung. Mohon periksa status database atau hubungi administrator sistem.',
                    ], 503);
                }
            }

            return null;
        });

        $exceptions->render(function (\PDOException $e, \Illuminate\Http\Request $request) {
            if ($e instanceof \Illuminate\Database\QueryException) {
                return null;
            }

            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'DATABASE_CONNECTION_ERROR',
                    'message' => 'Layanan database tidak dapat terhubung. Mohon periksa status database atau hubungi administrator sistem.',
                ], 503);
            }
        });
    })->create();
