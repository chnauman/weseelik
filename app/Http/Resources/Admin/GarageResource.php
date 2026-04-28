<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class GarageResource extends JsonResource
{
    public function toArray($request)
    {
        $data = parent::toArray($request);
        $garageId = (int) ($data['id'] ?? 0);
        $fallbackIndex = ($garageId > 0) ? (($garageId % 10) + 1) : 1;
        $fallbackStorefrontIndex = ($fallbackIndex % 10) + 1;
        $fallbackGalleryBaseIndex = (($fallbackIndex + 1) % 10) + 1;
        $fallbackUrl = "https://wiseelik.com/images/fallback-{$fallbackIndex}.png";
        $fallbackUrlStorefront = "https://wiseelik.com/images/fallback-{$fallbackStorefrontIndex}.png";
        $fallbackUrlGallery = "https://wiseelik.com/images/fallback-{$fallbackGalleryBaseIndex}.png";

        // Ensure the resource is an individual model, not a collection
        if ($this->resource instanceof \App\Models\Garage) {
            // Get all logos
            $logos = $this->getMedia('logo')->map(function ($media) use ($fallbackUrl) {
                $path = method_exists($media, 'getPath') ? $media->getPath() : null;
                $existsOnDisk = $path ? @file_exists($path) : true;
                $resolvedUrl = $existsOnDisk ? $media->getUrl() : $fallbackUrl;
                return [
                    'id' => $media->id,
                    // 'url' => $media->getUrl(),
                    // 'thumbnail' => $media->getUrl('thumb'),
                    // 'preview' => $media->getUrl('preview'),
                    // 'url' => 'https://wiseelik.com/images/logo.png',
                    // 'thumbnail' => 'https://wiseelik.com/images/logo.png',
                    // 'preview' => 'https://wiseelik.com/images/logo.png',
                    'url' => $resolvedUrl,
                    'thumbnail' => $resolvedUrl,
                    'preview' => $resolvedUrl,
                ];
            });
            if ($logos->isEmpty()) {
                $logos = collect([[
                    'id' => null,
                    'url' => $fallbackUrl,
                    'thumbnail' => $fallbackUrl,
                    'preview' => $fallbackUrl,
                ]]);
            }

            // Get all storefront images
            $storefrontImages = $this->getMedia('storefront_image')->map(function ($media) use ($fallbackUrlStorefront) {
                $path = method_exists($media, 'getPath') ? $media->getPath() : null;
                $existsOnDisk = $path ? @file_exists($path) : true;
                $resolvedUrl = $existsOnDisk ? $media->getUrl() : $fallbackUrlStorefront;
                return [
                    'id' => $media->id,
                    // 'url' => $media->getUrl(),
                    // 'thumbnail' => $media->getUrl('thumb'),
                    // 'preview' => $media->getUrl('preview'),
                    // 'url' => 'https://wiseelik.com/images/logo.png',
                    // 'thumbnail' => 'https://wiseelik.com/images/logo.png',
                    // 'preview' => 'https://wiseelik.com/images/logo.png',
                    'url' => $resolvedUrl,
                    'thumbnail' => $resolvedUrl,
                    'preview' => $resolvedUrl,
                ];
            });
            if ($storefrontImages->isEmpty()) {
                $storefrontImages = collect([[
                    'id' => null,
                    'url' => $fallbackUrlStorefront,
                    'thumbnail' => $fallbackUrlStorefront,
                    'preview' => $fallbackUrlStorefront,
                ]]);
            }

            // Get all gallery images
            $gallery = $this->getMedia('gallery')->map(function ($media, $index) use ($fallbackGalleryBaseIndex) {
                $galleryFallbackIndex = (($fallbackGalleryBaseIndex + (int) $index - 1) % 10) + 1;
                $galleryFallbackUrl = "https://wiseelik.com/images/fallback-{$galleryFallbackIndex}.png";
                $path = method_exists($media, 'getPath') ? $media->getPath() : null;
                $existsOnDisk = $path ? @file_exists($path) : true;
                $resolvedUrl = $existsOnDisk ? $media->getUrl() : $galleryFallbackUrl;
                return [
                    'id' => $media->id,
                    // 'url' => $media->getUrl(),
                    // 'thumbnail' => $media->getUrl('thumb'),
                    // 'preview' => $media->getUrl('preview'),
                    // 'url' => 'https://wiseelik.com/images/logo.png',
                    // 'thumbnail' => 'https://wiseelik.com/images/logo.png',
                    // 'preview' => 'https://wiseelik.com/images/logo.png',
                    'url' => $resolvedUrl,
                    'thumbnail' => $resolvedUrl,
                    'preview' => $resolvedUrl,
                ];
            });
            if ($gallery->isEmpty()) {
                $gallery = collect([[
                    'id' => null,
                    'url' => $fallbackUrlGallery,
                    'thumbnail' => $fallbackUrlGallery,
                    'preview' => $fallbackUrlGallery,
                ]]);
            }

            // Replace the single media items with collections
            $data['logos'] = $logos;
            $data['storefront_images'] = $storefrontImages;
            $data['gallery'] = $gallery;

            // Remove the old single media attributes
            unset($data['logo']);
            unset($data['storefront_image']);
        }

        return $data;
    }
}
