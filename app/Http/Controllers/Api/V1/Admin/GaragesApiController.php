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

class GaragesApiController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('garage_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new GarageResource(Garage::with(['user', 'country', 'tags', 'languages', 'specializations', 'services'])->get());
    }

    public function store(StoreGarageRequest $request)
    {
        $garage = Garage::create($request->all());
        $garage->tags()->sync($request->input('tags', []));
        $garage->languages()->sync($request->input('languages', []));
        $garage->specializations()->sync($request->input('specializations', []));
        $garage->services()->sync($request->input('services', []));
        if ($request->input('logo', false)) {
            $garage->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
        }

        if ($request->input('storefront_image', false)) {
            $garage->addMedia(storage_path('tmp/uploads/' . basename($request->input('storefront_image'))))->toMediaCollection('storefront_image');
        }

        foreach ($request->input('gallery', []) as $file) {
            $garage->addMedia(storage_path('tmp/uploads/' . basename($file)))->toMediaCollection('gallery');
        }

        return (new GarageResource($garage))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Garage $garage)
    {
        abort_if(Gate::denies('garage_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new GarageResource($garage->load(['user', 'country', 'tags', 'languages', 'specializations', 'services']));
    }

    public function update(UpdateGarageRequest $request, Garage $garage)
    {
        $garage->update($request->all());
        $garage->tags()->sync($request->input('tags', []));
        $garage->languages()->sync($request->input('languages', []));
        $garage->specializations()->sync($request->input('specializations', []));
        $garage->services()->sync($request->input('services', []));
        if ($request->input('logo', false)) {
            if (! $garage->logo || $request->input('logo') !== $garage->logo->file_name) {
                if ($garage->logo) {
                    $garage->logo->delete();
                }
                $garage->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
            }
        } elseif ($garage->logo) {
            $garage->logo->delete();
        }

        if ($request->input('storefront_image', false)) {
            if (! $garage->storefront_image || $request->input('storefront_image') !== $garage->storefront_image->file_name) {
                if ($garage->storefront_image) {
                    $garage->storefront_image->delete();
                }
                $garage->addMedia(storage_path('tmp/uploads/' . basename($request->input('storefront_image'))))->toMediaCollection('storefront_image');
            }
        } elseif ($garage->storefront_image) {
            $garage->storefront_image->delete();
        }

        if (count($garage->gallery) > 0) {
            foreach ($garage->gallery as $media) {
                if (! in_array($media->file_name, $request->input('gallery', []))) {
                    $media->delete();
                }
            }
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
