<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyGarageRequest;
use App\Http\Requests\StoreGarageRequest;
use App\Http\Requests\UpdateGarageRequest;
use App\Models\Category;
use App\Models\Country;
use App\Models\Garage;
use App\Models\Language;
use App\Models\Service;
use App\Models\Specialization;
use App\Models\Tag;
use App\Models\User;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class GaragesController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('garage_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $garages = Garage::with(['user', 'country', 'tags', 'languages', 'specializations', 'services', 'categories', 'media'])->get();

        return view('admin.garages.index', compact('garages'));
    }

    public function create()
    {
        abort_if(Gate::denies('garage_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $countries = Country::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $tags = Tag::pluck('title', 'id');

        $languages = Language::pluck('title', 'id');

        $specializations = Specialization::pluck('title', 'id');

        $services = Service::pluck('title', 'id');

        $categories = Category::pluck('title', 'id');

        return view('admin.garages.create', compact('categories', 'countries', 'languages', 'services', 'specializations', 'tags', 'users'));
    }

    public function store(StoreGarageRequest $request)
    {
        $garage = Garage::create($request->all());
        $garage->tags()->sync($request->input('tags', []));
        $garage->languages()->sync($request->input('languages', []));
        $garage->specializations()->sync($request->input('specializations', []));
        $garage->services()->sync($request->input('services', []));
        $garage->categories()->sync($request->input('categories', []));
        if ($request->input('logo', false)) {
            $garage->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
        }

        if ($request->input('storefront_image', false)) {
            $garage->addMedia(storage_path('tmp/uploads/' . basename($request->input('storefront_image'))))->toMediaCollection('storefront_image');
        }

        foreach ($request->input('gallery', []) as $file) {
            $garage->addMedia(storage_path('tmp/uploads/' . basename($file)))->toMediaCollection('gallery');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $garage->id]);
        }

        return redirect()->route('admin.garages.index');
    }

    public function edit(Garage $garage)
    {
        abort_if(Gate::denies('garage_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $countries = Country::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $tags = Tag::pluck('title', 'id');

        $languages = Language::pluck('title', 'id');

        $specializations = Specialization::pluck('title', 'id');

        $services = Service::pluck('title', 'id');

        $categories = Category::pluck('title', 'id');

        $garage->load('user', 'country', 'tags', 'languages', 'specializations', 'services', 'categories');

        return view('admin.garages.edit', compact('categories', 'countries', 'garage', 'languages', 'services', 'specializations', 'tags', 'users'));
    }

    public function update(UpdateGarageRequest $request, Garage $garage)
    {
        $garage->update($request->all());
        $garage->tags()->sync($request->input('tags', []));
        $garage->languages()->sync($request->input('languages', []));
        $garage->specializations()->sync($request->input('specializations', []));
        $garage->services()->sync($request->input('services', []));
        $garage->categories()->sync($request->input('categories', []));
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

        return redirect()->route('admin.garages.index');
    }

    public function show(Garage $garage)
    {
        abort_if(Gate::denies('garage_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $garage->load('user', 'country', 'tags', 'languages', 'specializations', 'services', 'categories');

        return view('admin.garages.show', compact('garage'));
    }

    public function destroy(Garage $garage)
    {
        abort_if(Gate::denies('garage_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $garage->delete();

        return back();
    }

    public function massDestroy(MassDestroyGarageRequest $request)
    {
        $garages = Garage::find(request('ids'));

        foreach ($garages as $garage) {
            $garage->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('garage_create') && Gate::denies('garage_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new Garage();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
