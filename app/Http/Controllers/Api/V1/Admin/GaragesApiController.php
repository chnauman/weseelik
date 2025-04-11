<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\StoreGarageRequest;
use App\Http\Requests\UpdateGarageRequest;
use App\Http\Resources\Admin\GarageResource;
use App\Models\Garage;
use App\Models\Contact;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GaragesApiController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        //abort_if(Gate::denies('garage_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new GarageResource(Garage::with(['user', 'country', 'tags', 'languages', 'specializations', 'services', 'categories', 'work_shifts', 'contacts'])->get());
    }

    public function store(StoreGarageRequest $request)
    {
        $garage = Garage::create($request->all());

        $garage->tags()->sync($request->input('tags', []));
        $garage->languages()->sync($request->input('languages', []));
        $garage->specializations()->sync($request->input('specializations', []));
        $garage->services()->sync($request->input('services', []));
        $garage->categories()->sync($request->input('categories', []));

        // Create work shifts for the garage
        if ($request->has('work_shifts') && is_array($request->input('work_shifts'))) {
            foreach ($request->input('work_shifts') as $shiftData) {
                $garage->work_shifts()->create([
                    'opening_time' => $shiftData['opening_time'],
                    'closing_time' => $shiftData['closing_time'],
                    'day_of_week' => $shiftData['day_of_week'] ?? null,
                ]);
            }
        }

        // Create contacts for the garage
        if ($request->has('contacts') && is_array($request->input('contacts'))) {
            foreach ($request->input('contacts') as $contactData) {
                $garage->contacts()->create([
                    'contact_no' => $contactData['contact_no'],
                    'type' => $contactData['type'] ?? 'mobile',
                    'status' => $contactData['status'] ?? 'active',
                ]);
            }
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $garage->addMedia($request->file('logo'))->toMediaCollection('logo');
        }

        // Handle storefront image upload
        if ($request->hasFile('storefront_image')) {
            $garage->addMedia($request->file('storefront_image'))->toMediaCollection('storefront_image');
        }

        // Handle gallery upload
        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery') as $file) {
                $garage->addMedia($file)->toMediaCollection('gallery');
            }
        }
        /*

        if ($request->input('logo', false)) {
            $garage->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
        }

        if ($request->input('storefront_image', false)) {
            $garage->addMedia(storage_path('tmp/uploads/' . basename($request->input('storefront_image'))))->toMediaCollection('storefront_image');
        }

        foreach ($request->input('gallery', []) as $file) {
            $garage->addMedia(storage_path('tmp/uploads/' . basename($file)))->toMediaCollection('gallery');
        }
*/

        return (new GarageResource($garage))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Garage $garage)
    {
        //abort_if(Gate::denies('garage_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new GarageResource($garage->load(['user', 'country', 'tags', 'languages', 'specializations', 'services', 'categories', 'work_shifts', 'contacts']));
    }

    public function update(UpdateGarageRequest $request, Garage $garage)
    {
        $garage->update($request->all());
        $garage->tags()->sync($request->input('tags', []));
        $garage->languages()->sync($request->input('languages', []));
        $garage->specializations()->sync($request->input('specializations', []));
        $garage->services()->sync($request->input('services', []));
        $garage->categories()->sync($request->input('categories', []));

        // Update work shifts
        if ($request->has('work_shifts') && is_array($request->input('work_shifts'))) {
            // Delete existing shifts for this garage
            $garage->work_shifts()->delete();

            // Create new shifts based on request data
            foreach ($request->input('work_shifts') as $shiftData) {
                $garage->work_shifts()->create([
                    'opening_time' => $shiftData['opening_time'],
                    'closing_time' => $shiftData['closing_time'],
                    'day_of_week' => $shiftData['day_of_week'] ?? null,
                ]);
            }
        }

        // Update contacts
        if ($request->has('contacts') && is_array($request->input('contacts'))) {
            // Delete existing contacts for this garage
            $garage->contacts()->delete();

            // Create new contacts based on request data
            foreach ($request->input('contacts') as $contactData) {
                $garage->contacts()->create([
                    'contact_no' => $contactData['contact_no'],
                    'type' => $contactData['type'] ?? 'mobile',
                    'status' => $contactData['status'] ?? 'active',
                ]);
            }
        }

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
