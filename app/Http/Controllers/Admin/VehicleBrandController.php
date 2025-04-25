<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyVehicleBrandRequest;
use App\Http\Requests\StoreVehicleBrandRequest;
use App\Http\Requests\UpdateVehicleBrandRequest;
use App\Models\VehicleBrand;
use App\Models\Tag;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class VehicleBrandController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('vehicle_brand_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vehicleBrands = VehicleBrand::with(['media', 'tag'])->get();

        return view('admin.vehicleBrands.index', compact('vehicleBrands'));
    }

    public function create()
    {
        abort_if(Gate::denies('vehicle_brand_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $tags = Tag::pluck('title', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.vehicleBrands.create', compact('tags'));
    }

    public function store(StoreVehicleBrandRequest $request)
    {
        $vehicleBrand = VehicleBrand::create($request->all());

        if ($request->input('logo', false)) {
            $vehicleBrand->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $vehicleBrand->id]);
        }

        return redirect()->route('admin.vehicle-brands.index');
    }

    public function edit(VehicleBrand $vehicleBrand)
    {
        abort_if(Gate::denies('vehicle_brand_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $tags = Tag::pluck('title', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.vehicleBrands.edit', compact('vehicleBrand', 'tags'));
    }

    public function update(UpdateVehicleBrandRequest $request, VehicleBrand $vehicleBrand)
    {
        $vehicleBrand->update($request->all());

        if ($request->input('logo', false)) {
            if (! $vehicleBrand->logo || $request->input('logo') !== $vehicleBrand->logo->file_name) {
                if ($vehicleBrand->logo) {
                    $vehicleBrand->logo->delete();
                }
                $vehicleBrand->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
            }
        } elseif ($vehicleBrand->logo) {
            $vehicleBrand->logo->delete();
        }

        return redirect()->route('admin.vehicle-brands.index');
    }

    public function show(VehicleBrand $vehicleBrand)
    {
        abort_if(Gate::denies('vehicle_brand_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.vehicleBrands.show', compact('vehicleBrand'));
    }

    public function destroy(VehicleBrand $vehicleBrand)
    {
        abort_if(Gate::denies('vehicle_brand_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vehicleBrand->delete();

        return back();
    }

    public function massDestroy(MassDestroyVehicleBrandRequest $request)
    {
        $vehicleBrands = VehicleBrand::find(request('ids'));

        foreach ($vehicleBrands as $vehicleBrand) {
            $vehicleBrand->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('vehicle_brand_create') && Gate::denies('vehicle_brand_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new VehicleBrand();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
