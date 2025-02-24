<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\StoreGarageRequest;
use App\Http\Requests\UpdateGarageRequest;
use App\Http\Resources\Admin\GarageResource;
use App\Models\Garage;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Storage;

class GaragesApiController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        // abort_if(Gate::denies('garage_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new GarageResource(Garage::with(['user', 'country', 'tags', 'languages', 'specializations', 'services', 'categories'])->get());
    }

    public function store(StoreGarageRequest $request)
    {

        $garage = Garage::create($request->all());

        $garage->tags()->sync($request->input('tags', []));
        $garage->languages()->sync($request->input('languages', []));
        $garage->specializations()->sync($request->input('specializations', []));
        $garage->services()->sync($request->input('services', []));
        $garage->categories()->sync($request->input('categories', []));
        $paths = [];

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('uploads/logos', 'public');
            $paths['logo'] = asset('storage/' . $path);
            $garage->update(['logo' => $path]);
        }

        if ($request->hasFile('storefront_image')) {
            $path = $request->file('storefront_image')->store('uploads/storefronts', 'public');
            $paths['storefront_image'] = asset('storage/' . $path);
            $garage->update(['storefront_image' => $path]);
        }

        if ($request->hasFile('gallery')) {
            $galleryPaths = [];
            foreach ($request->file('gallery') as $file) {
                $path = $file->store('uploads/galleries', 'public');
                $galleryPaths[] = asset('storage/' . $path);
            }
            $garage->update(['gallery' => json_encode($galleryPaths)]);
            $paths['gallery'] = $galleryPaths;
        }


        return (new GarageResource($garage))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Garage $garage)
    {
        abort_if(Gate::denies('garage_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new GarageResource($garage->load(['user', 'country', 'tags', 'languages', 'specializations', 'services', 'categories']));
    }

    public function update(UpdateGarageRequest $request, Garage $garage)
    {
        $garage->update($request->all());
        $garage->tags()->sync($request->input('tags', []));
        $garage->languages()->sync($request->input('languages', []));
        $garage->specializations()->sync($request->input('specializations', []));
        $garage->services()->sync($request->input('services', []));
        $garage->categories()->sync($request->input('categories', []));
        if ($request->hasFile('logo')) {
            if ($garage->logo) {
                \Storage::delete('public/' . $garage->logo);
            }
            $path = $request->file('logo')->store('uploads/logos', 'public');
            $paths['logo'] = asset('storage/' . $path);
            $garage->update(['logo' => $path]);
        }

        if ($request->hasFile('storefront_image')) {
            if ($garage->storefront_image) {
                \Storage::delete('public/' . $garage->storefront_image);
            }
            $path = $request->file('storefront_image')->store('uploads/storefronts', 'public');
            $paths['storefront_image'] = asset('storage/' . $path);
            $garage->update(['storefront_image' => $path]);
        }

        if ($request->hasFile('gallery')) {
            if (!empty($garage->gallery)) {
                foreach (json_decode($garage->gallery, true) as $oldPath) {
                    \Storage::delete('public/' . str_replace(asset('storage/'), '', $oldPath));
                }
            }

            $galleryPaths = [];
            foreach ($request->file('gallery') as $file) {
                $path = $file->store('uploads/galleries', 'public');
                $galleryPaths[] = asset('storage/' . $path);
            }
            $garage->update(['gallery' => json_encode($galleryPaths)]);
            $paths['gallery'] = $galleryPaths;
        }

        $media = $garage->gallery->pluck('file_name')->toArray();
        foreach ($request->input('gallery', []) as $file) {
            if (count($media) === 0 || ! in_array($file, $media)) {
                $garage->addMedia(storage_path('tmp/uploads/' . basename($file)))->toMediaCollection('gallery');
            }
        }

        return (new GarageResource($garage))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Garage $garage)
    {
        abort_if(Gate::denies('garage_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $garage->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
