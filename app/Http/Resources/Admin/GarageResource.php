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
        $fallbackUrl = "https://wiseelik.com/images/fallback-{$fallbackIndex}.png";

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

            // Get all storefront images
            $storefrontImages = $this->getMedia('storefront_image')->map(function ($media) use ($fallbackUrl) {
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

            // Get all gallery images
            $gallery = $this->getMedia('gallery')->map(function ($media) use ($fallbackUrl) {
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
