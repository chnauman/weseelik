<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class GarageResource extends JsonResource
{
    public function toArray($request)
    {
        $data = parent::toArray($request);
        
        // Get all logos
        $logos = $this->getMedia('logo')->map(function ($media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'thumbnail' => $media->getUrl('thumb'),
                'preview' => $media->getUrl('preview'),
            ];
        });

        // Get all storefront images
        $storefrontImages = $this->getMedia('storefront_image')->map(function ($media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'thumbnail' => $media->getUrl('thumb'),
                'preview' => $media->getUrl('preview'),
            ];
        });

        // Get all gallery images
        $gallery = $this->getMedia('gallery')->map(function ($media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'thumbnail' => $media->getUrl('thumb'),
                'preview' => $media->getUrl('preview'),
            ];
        });

        // Replace the single media items with collections
        $data['logos'] = $logos;
        $data['storefront_images'] = $storefrontImages;
        $data['gallery'] = $gallery;

        // Remove the old single media attributes
        unset($data['logo']);
        unset($data['storefront_image']);

        return $data;
    }
}
