<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController extends Controller
{
    /**
     * Return a success response.
     */
    protected function success(
        mixed $data = null,
        string $message = 'Success',
        int $code = Response::HTTP_OK
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Return a success response with resource.
     */
    protected function resource(
        JsonResource $resource,
        string $message = 'Success',
        int $code = Response::HTTP_OK
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resource,
        ], $code);
    }

    /**
     * Return a success response with collection.
     */
    protected function collection(
        ResourceCollection $collection,
        string $message = 'Success'
    ): JsonResponse {
        $response = $collection->response()->getData(true);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $response['data'],
            'meta' => $response['meta'] ?? null,
            'links' => $response['links'] ?? null,
        ]);
    }

    /**
     * Return a created response.
     */
    protected function created(
        mixed $data = null,
        string $message = 'Created successfully'
    ): JsonResponse {
        return $this->success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Return an error response.
     */
    protected function error(
        string $message = 'Error',
        int $code = Response::HTTP_BAD_REQUEST,
        array $errors = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a not found response.
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Return an unauthorized response.
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a forbidden response.
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Return a validation error response.
     */
    protected function validationError(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->error($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    /**
     * Return a no content response.
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Return deleted response.
     */
    protected function deleted(string $message = 'Deleted successfully'): JsonResponse
    {
        return $this->success(null, $message);
    }

    /**
     * Resolve the tenant ID for the current request.
     *
     * Regular users carry their own tenant_id; a super admin (tenant_id = null)
     * must supply the tenant context via the X-Tenant-ID header.
     */
    protected function currentTenantId(?Request $request = null): ?string
    {
        $request ??= request();

        return $request->user()?->tenant_id ?? $request->header('X-Tenant-ID');
    }
}
