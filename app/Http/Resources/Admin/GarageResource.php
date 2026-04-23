<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class GarageResource extends JsonResource
{
    public function toArray($request)
    {
        $data = parent::toArray($request);

        // Ensure the resource is an individual model, not a collection
        if ($this->resource instanceof \App\Models\Garage) {
            // Get all logos
            $logos = $this->getMedia('logo')->map(function ($media) {
                return [
                    'id' => $media->id,
                    // 'url' => $media->getUrl(),
                    // 'thumbnail' => $media->getUrl('thumb'),
                    // 'preview' => $media->getUrl('preview'),
                    'url' => asset('images/wiseelik-logo-mascot.png'),
                    'thumbnail' => asset('images/wiseelik-logo-mascot.png'),
                    'preview' => asset('images/wiseelik-logo-mascot.png'),
                ];
            });

            // Get all storefront images
            $storefrontImages = $this->getMedia('storefront_image')->map(function ($media) {
                return [
                    'id' => $media->id,
                    // 'url' => $media->getUrl(),
                    // 'thumbnail' => $media->getUrl('thumb'),
                    // 'preview' => $media->getUrl('preview'),
                    'url' => asset('images/car-hero.png'),
                    'thumbnail' => asset('images/car-hero.png'),
                    'preview' => asset('images/car-hero.png'),
                ];
            });

            // Get all gallery images
            $gallery = $this->getMedia('gallery')->map(function ($media) {
                return [
                    'id' => $media->id,
                    // 'url' => $media->getUrl(),
                    // 'thumbnail' => $media->getUrl('thumb'),
                    // 'preview' => $media->getUrl('preview'),
                    'url' => asset('images/car-hero.png'),
                    'thumbnail' => asset('images/car-hero.png'),
                    'preview' => asset('images/car-hero.png'),
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
