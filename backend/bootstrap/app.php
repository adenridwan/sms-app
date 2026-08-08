<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Web middleware
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // API middleware
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Middleware aliases
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'tenant' => \App\Http\Middleware\EnsureTenantMiddleware::class,
            'password.current' => \App\Http\Middleware\EnsurePasswordIsCurrent::class,
        ]);

        // Middleware priority
        $middleware->priority([
            \App\Http\Middleware\EnsureTenantMiddleware::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle database not initialized (missing essential tables like sessions, users)
        // This happens when switching to a new empty database
        $exceptions->render(function (QueryException $e, Request $request) {
            // SQLSTATE[42P01] = undefined_table (PostgreSQL)
            // SQLSTATE[42S02] = Base table or view not found (MySQL)
            $sqlState = $e->errorInfo[0] ?? '';
            $message = $e->getMessage();

            $isUndefinedTable = in_array($sqlState, ['42P01', '42S02'], true);
            $isEssentialTable = preg_match('/relation "?(sessions|users|personal_access_tokens)"? does not exist/i', $message)
                || preg_match('/table .*(sessions|users|personal_access_tokens).* doesn\'t exist/i', $message);

            if ($isUndefinedTable && $isEssentialTable) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Database belum diinisialisasi. Hubungi administrator.',
                        'error_code' => 'DATABASE_NOT_INITIALIZED',
                    ], 503);
                }

                return response()->view('errors.database-not-initialized', [], 503);
            }

            return null; // Let other handlers process it
        });

        // Route model binding yang gagal (mis. baris sudah terhapus) melempar
        // NotFoundHttpException dengan pesan bawaan Laravel yang membocorkan
        // FQCN model ("No query results for model [App\...\Classroom] <uuid>")
        // sampai ke toast pengguna. Balas dengan pesan yang ramah untuk API.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getPrevious() instanceof ModelNotFoundException
                    ? 'Data tidak ditemukan atau sudah dihapus.'
                    : 'Alamat yang diminta tidak ditemukan.',
            ], 404);
        });

        // Render elegant Inertia error pages for web requests
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            // Don't override custom database-not-initialized page
            if ($exception instanceof QueryException) {
                $sqlState = $exception->errorInfo[0] ?? '';
                if (in_array($sqlState, ['42P01', '42S02'], true)) {
                    return $response;
                }
            }

            if (
                ! $request->expectsJson()
                && ! $request->is('api/*')
                && in_array($status, [401, 403, 404, 419, 429, 500, 503], true)
            ) {
                if ($status === 419) {
                    return back()->with([
                        'message' => 'Sesi Anda telah berakhir, silakan coba lagi.',
                    ]);
                }

                // Keep the debug page for server errors during local development
                if (in_array($status, [500, 503], true) && config('app.debug')) {
                    return $response;
                }

                return \Inertia\Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })
    ->create();
