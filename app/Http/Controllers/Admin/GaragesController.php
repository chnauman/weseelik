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
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class GaragesController extends Controller
{
    use MediaUploadingTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('garage_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $query = Garage::with(['user', 'country', 'tags', 'languages', 'specializations', 'services', 'categories', 'media']);

        // Filter by status (GET param)
        $status = $request->query('status');
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        // Filter by location_verified (GET param)
        $locationVerified = $request->query('location_verified');
        if ($locationVerified !== null && $locationVerified !== '') {
            $query->where('location_verified', $locationVerified);
        }

        // Filter by area (GET param)
        $area = $request->query('area');
        if ($area !== null && $area !== '') {
            $query->where('area', $area);
        }

        // Filter by tags: accept array (tags[]=1&tags[]=2) or single value
        $tagsParam = $request->query('tags');
        $tagIds = [];
        if (is_array($tagsParam)) {
            $tagIds = array_filter(array_map('intval', $tagsParam));
        } elseif (is_string($tagsParam) && $tagsParam !== '') {
            $tagIds = array_filter(array_map('intval', explode(',', $tagsParam)));
        }
        if (count($tagIds) > 0) {
            $query->whereHas('tags', function ($q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            });
        }

        // Filter for verified garages (status = approved AND location_verified = verified)
        if ($request->query('verified') == '1') {
            $query->where('status', 'approved')
                  ->where('location_verified', 'verified');
        }

        // Filter for unverified garages
        if ($request->query('unverified') == '1') {
            $query->where(function ($q) {
                $q->where('status', '!=', 'approved')
                  ->orWhere('location_verified', '!=', 'verified')
                  ->orWhereNull('location_verified');
            });
        }

        $garages = $query->orderBy('title')->get();

        // Get filter options for the view
        $tags = Tag::pluck('title', 'id');
        $areas = Garage::select('area')->distinct()->whereNotNull('area')->where('area', '!=', '')->pluck('area')->filter()->values();

        return view('admin.garages.index', compact('garages', 'tags', 'areas'));
    }

    public function create()
    {
        abort_if(Gate::denies('garage_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::where('type', 'business')->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

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

        $users = User::where('type', 'business')->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

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

    public function assignForm(Request $request)
    {
        abort_if(Gate::denies('garage_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $garages = Garage::pluck('title', 'id');
        $users = User::where('type', 'business')->pluck('name', 'id');
        $selectedUser = $request->input('user_id', null);

        return view('admin.garages.assign_garage', compact('garages', 'users', 'selectedUser'));
    }

    public function assign(Request $request)
    {
        abort_if(Gate::denies('garage_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $request->validate([
            'garage_id' => 'required|exists:garages,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $garage = Garage::findOrFail($request->garage_id);
        $garage->user_id = $request->user_id;
        $garage->save();

        return redirect()->route('admin.garages.index')->with('message', 'Garage assigned successfully');
    }

    public function garagesWithoutArea()
    {
        $garages = Garage::whereNull('area')
            ->orWhere('area', '')
            ->get();

        return view('admin.garages-without-area', compact('garages'));
    }

    /**
     * Translate Garage fields (title/description) into Arabic using OpenAI.
     * Used by admin create/edit forms to auto-fill *_ar fields.
     */
    public function translateArabic(Request $request)
    {
        abort_if(Gate::denies('garage_create') && Gate::denies('garage_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $validated = $request->validate([
            'field' => 'required|in:title,description',
            'text'  => 'required|string|max:5000',
        ]);

        $apiKey = env('OPENAI_API_KEY');
        if (empty($apiKey)) {
            return response()->json([
                'message' => 'OpenAI API key is not configured.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $text = trim((string) $validated['text']);
        if ($text === '') {
            return response()->json([
                'message' => 'Text is empty.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $model = env('OPENAI_MODEL', 'gpt-4o-mini');

            $fieldContext = $validated['field'] === 'title'
                ? 'garage/business title in Kuwait'
                : 'garage/business description in Kuwait';

            $system = "You are a professional translator into Kuwaiti Arabic (Gulf dialect, Kuwait).
- Output ONLY the Arabic translation.
- Keep brand names, phone numbers, addresses, and URLs unchanged.
- Use common Kuwaiti terms where natural, but keep it understandable.";

            $prompt = "Translate the following {$fieldContext} into Kuwaiti Arabic. Return ONLY the Arabic translation:\n\n{$text}";

            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
            ]);

            $content = trim($response->choices[0]->message->content ?? '');
            $content = trim($content, "\"'\n\r\t ");

            if ($content === '') {
                return response()->json([
                    'message' => 'Translation returned empty result.',
                ], Response::HTTP_BAD_GATEWAY);
            }

            return response()->json([
                'translation' => $content,
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            Log::error('OpenAI translateArabic error', [
                'field' => $validated['field'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Translation failed. Please try again.',
            ], Response::HTTP_BAD_GATEWAY);
        }
    }
}
