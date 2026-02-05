<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NodeApiService
{
    public static function getStats()
    {
        $url = env('NODE_JS_API_URL') . '/admin/stats';

        try {
            // Kita ambil data dari Node.js
            $response = Http::get($url);
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getTransactions()
    {
        $url = env('NODE_JS_API_URL') . '/admin/transactions';
        try {
            $response = Http::get($url);
            return $response->successful() ? $response->json() : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function getProducts($page = 1, $perPage = 10, $categoryId = null, $status = null)
    {
        $params = [
            'page' => $page,
            'limit' => $perPage,
        ];

        if ($categoryId) $params['category_id'] = $categoryId;
        if ($status) $params['status'] = $status; // Tambahkan status ke parameter HTTP

        $response = Http::get(env('NODE_JS_API_URL') . '/admin/products', $params);

        return $response->json() ?? ['products' => [], 'total' => 0];
    }

    // Tambahkan ini untuk mengambil daftar kategori buat dropdown filter
    public static function getCategories()
    {
        // Pastikan kamu punya endpoint /admin/categories di Node.js
        $response = Http::get(env('NODE_JS_API_URL') . '/admin/categories');
        return $response->json()['data'] ?? [];
    }

    public static function getCategoriesPage()
    {
        $url = env('NODE_JS_API_URL') . '/admin/categories';

        // Gunakan try-catch untuk melihat jika ada error koneksi
        try {
            $response = Http::get($url);

            if ($response->successful()) {
                return $response->json(); // Harus memanggil ->json()
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("API Error: " . $e->getMessage());
        }

        return ['data' => []];
    }

    public static function updateCategory($id, $data)
    {
        return Http::patch(env('NODE_JS_API_URL') . "/admin/categories/{$id}", $data);
    }

    public static function deleteProduct($id)
    {
        return Http::delete(env('NODE_JS_API_URL') . "/admin/products/{$id}");
    }

    public static function syncProducts()
    {
        // Mengacu pada endpoint /admin/sync-products sesuai image_ce2f4d.png
        return Http::post(env('NODE_JS_API_URL') . '/admin/sync-products');
    }
}