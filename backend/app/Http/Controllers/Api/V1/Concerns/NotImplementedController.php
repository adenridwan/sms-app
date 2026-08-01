<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * Base untuk controller STUB modul yang belum dibuat. Endpoint tetap terdaftar
 * (surface API & `route:list` utuh), namun aksi apa pun mengembalikan 501
 * "belum diimplementasi", bukan 500 fatal karena class-nya tidak ada.
 *
 * Laravel mengecek method_exists() sebelum me-reflect method, lalu memanggil
 * callAction() → $this->{$method}() → memicu __call di sini. Jadi semua aksi
 * (index/store/show/update/destroy/dll) tertangani tanpa perlu didefinisikan.
 */
abstract class NotImplementedController extends ApiController
{
    public function __call($method, $parameters): JsonResponse
    {
        return $this->error('Fitur ini belum diimplementasi.', 501);
    }
}
