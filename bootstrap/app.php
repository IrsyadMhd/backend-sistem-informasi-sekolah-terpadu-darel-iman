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

            \Illuminate\Support\Facades\Log::error('Database QueryException: ' . $msg, [
                'code' => $code,
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
            ]);

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

                // Global Sanitasi: Cegah kebocoran SQLSTATE, nama tabel/kolom ke client
                if (!config('app.debug')) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 'DATABASE_QUERY_ERROR',
                        'message' => 'Terjadi kendala pemrosesan data pada sistem. Silakan coba kembali atau hubungi administrator.',
                    ], 500);
                }
            }

            return null;
        });

        $exceptions->render(function (\PDOException $e, \Illuminate\Http\Request $request) {
            if ($e instanceof \Illuminate\Database\QueryException) {
                return null;
            }

            \Illuminate\Support\Facades\Log::error('PDOException: ' . $e->getMessage());

            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'DATABASE_CONNECTION_ERROR',
                    'message' => 'Layanan database tidak dapat terhubung. Mohon periksa status database atau hubungi administrator sistem.',
                ], 503);
            }
        });

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                // Biarkan exception bawaan HTTP (401, 403, 404, 422, 429) ditangani secara normal
                if ($e instanceof \Illuminate\Validation\ValidationException ||
                    $e instanceof \Illuminate\Auth\AuthenticationException ||
                    $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    return null;
                }

                \Illuminate\Support\Facades\Log::error('Unhandled API Server Exception: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                if (!config('app.debug')) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 'INTERNAL_SERVER_ERROR',
                        'message' => 'Terjadi kendala pemrosesan data pada sistem. Silakan coba kembali atau hubungi administrator.',
                    ], 500);
                }
            }
            return null;
        });
    })->create();
