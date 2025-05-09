<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\StoreGarageRequest;
use App\Http\Requests\UpdateGarageRequest;
use App\Http\Resources\Admin\GarageResource;
use App\Models\Garage;
use App\Models\Contact;
use App\Models\UserBlock;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
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
        $data = $request->all();
        $data['surveyor_id'] = auth()->id();

        $garage = Garage::create($data);

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
            foreach ($request->file('logo') as $file) {
                $garage->addMedia($file)->toMediaCollection('logo');
            }
        }

        // Handle storefront image upload
        if ($request->hasFile('storefront_image')) {
            foreach ($request->file('storefront_image') as $file) {
                $garage->addMedia($file)->toMediaCollection('storefront_image');
            }
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

    /**
     * Get top visited garages
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function topVisited(Request $request)
    {
        // Validate limit parameter
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer',
        ]);

        // Get the limit from request or use default value of 10
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        // Query the top visited garages
        $query = Garage::orderBy('no_of_visits', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->with(['user', 'country', 'tags', 'languages', 'specializations', 'services', 'categories', 'work_shifts', 'contacts']);

            // Get user if authenticated
        $user = $request->user();

        if ($user) {
            $query->select([
                'garages.*',
                DB::raw('CASE WHEN user_favorites.id IS NOT NULL THEN 1 ELSE 0 END as is_favorite')
            ])
            ->leftJoin('user_favorites', function($join) use ($user) {
                $join->on('garages.id', '=', 'user_favorites.garage_id')
                     ->where('user_favorites.user_id', '=', $user->id)
                     ->where('user_favorites.deleted_at', null);
            });

            // Exclude blocked garages
            $query->whereNotExists(function($subquery) use ($user) {
                $subquery->select(DB::raw(1))
                    ->from('user_blocks')
                    ->whereRaw('user_blocks.garage_id = garages.id')
                    ->where('user_blocks.user_id', $user->id)
                    ->where('user_blocks.deleted_at', null);
            });
        }

        $topGarages = $query->get();
        return new GarageResource($topGarages);
    }

    /**
     * Get nearby garages based on latitude and longitude
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function nearby(Request $request)
    {
        // Validate request
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer',
            'radius' => 'nullable|numeric|min:0.1|max:500', // radius in kilometers
        ]);

        // Get parameters
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);
        $radius = $request->input('radius', 10); // Default radius is 10 km

        // Get user if authenticated
        $user = $request->user();

        // Base query with distance calculation
        $query = Garage::select([
                'garages.id',
                'garages.title',
                'garages.description',
                'garages.contact_no',
                'garages.latitude',
                'garages.longitude',
                'garages.no_of_visits',
                'garages.no_of_likes',
                DB::raw("(
                    6371 * acos(
                        cos(radians($latitude)) *
                        cos(radians(latitude)) *
                        cos(radians(longitude) - radians($longitude)) +
                        sin(radians($latitude)) *
                        sin(radians(latitude))
                    )
                ) AS distance")
            ]);

        // If user is authenticated, add favorite and block information
        if ($user) {
            // Add is_favorite field
            $query->addSelect([
                DB::raw('CASE WHEN user_favorites.id IS NOT NULL THEN 1 ELSE 0 END as is_favorite')
            ])
            ->leftJoin('user_favorites', function($join) use ($user) {
                $join->on('garages.id', '=', 'user_favorites.garage_id')
                     ->where('user_favorites.user_id', '=', $user->id)
                     ->where('user_favorites.deleted_at', null);
            });

            // Exclude blocked garages
            $query->whereNotExists(function($subquery) use ($user) {
                $subquery->select(DB::raw(1))
                    ->from('user_blocks')
                    ->whereRaw('user_blocks.garage_id = garages.id')
                    ->where('user_blocks.user_id', $user->id)
                    ->where('user_blocks.deleted_at', null);
            });
        }

        // Apply distance filter and ordering
        $garages = $query->having('distance', '<=', $radius)
            ->orderBy('distance')
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Return a custom JSON response
        return response()->json([
            'success' => true,
            'count' => $garages->count(),
            'data' => $garages->map(function ($garage) {
                $data = [
                    'id' => $garage->id,
                    'title' => $garage->title,
                    'description' => $garage->description,
                    'contact_no' => $garage->contact_no,
                    'latitude' => $garage->latitude,
                    'longitude' => $garage->longitude,
                    'distance' => round($garage->distance, 2) . ' km',
                    'no_of_visits' => $garage->no_of_visits,
                    'no_of_likes' => $garage->no_of_likes,
                    'logo' => $garage->logo,
                    'storefront_image' => $garage->storefront_image,
                    'gallery' => $garage->gallery
                ];

                // Add is_favorite if it exists
                if (isset($garage->is_favorite)) {
                    $data['is_favorite'] = $garage->is_favorite;
                }

                return $data;
            })
        ]);
    }

    /**
     * Get garages with favorite information for the authenticated user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserGarages(Request $request)
    {
        // Get the authenticated user
        $user = $request->user();

        // First, get IDs of garages blocked by the user
        $blockedGarageIds = UserBlock::where('user_id', $user->id)
            ->pluck('garage_id')
            ->toArray();

        // Get garages with favorite information, excluding blocked garages
        $garages = Garage::select([
                'garages.id',
                'garages.title',
                'garages.description',
                'garages.contact_no',
                'garages.latitude',
                'garages.longitude',
                'garages.no_of_visits',
                'garages.no_of_likes',
                DB::raw('CASE WHEN user_favorites.id IS NOT NULL THEN 1 ELSE 0 END as is_favorite'),
                DB::raw('user_favorites.id as favorite_id'),
                DB::raw('user_favorites.note as favorite_note')
            ])
            ->leftJoin('user_favorites', function($join) use ($user) {
                $join->on('garages.id', '=', 'user_favorites.garage_id')
                     ->where('user_favorites.user_id', '=', $user->id);
            })
            // Exclude blocked garages using whereNotIn
            ->whereNotIn('garages.id', $blockedGarageIds)
            ->orderBy('is_favorite', 'desc') // Show favorites first
            ->orderBy('garages.title')
            ->get();

        // Return a custom JSON response
        return response()->json([
            'success' => true,
            'count' => $garages->count(),
            'data' => $garages->map(function ($garage) {
                return [
                    'id' => $garage->id,
                    'title' => $garage->title,
                    'description' => $garage->description,
                    'contact_no' => $garage->contact_no,
                    'latitude' => $garage->latitude,
                    'longitude' => $garage->longitude,
                    'no_of_visits' => $garage->no_of_visits,
                    'no_of_likes' => $garage->no_of_likes,
                    'is_favorite' => (bool)$garage->is_favorite,
                    'favorite_id' => $garage->is_favorite ? $garage->favorite_id : null,
                    'favorite_note' => $garage->is_favorite ? $garage->favorite_note : null,
                    'logo' => $garage->logo,
                    'storefront_image' => $garage->storefront_image,
                    'gallery' => $garage->gallery
                ];
            })
        ]);
    }

    /**
     * Search garages with filters
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $query = $request->input('query', '');
        $tagIds = $request->input('tags', []);
        $serviceIds = $request->input('services', []);

        $query = Garage::query()
            ->when($query, function ($q) use ($query) {
                $q->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%")
                        ->orWhere('address', 'like', "%{$query}%")
                        ->orWhere('service_description', 'like', "%{$query}%")
                        ->orWhereHas('tags', function ($q) use ($query) {
                            $q->where('title', 'like', "%{$query}%");
                        })
                        ->orWhereHas('services', function ($q) use ($query) {
                            $q->where('title', 'like', "%{$query}%");
                        });
                });
            })
            ->when(!empty($tagIds), function ($q) use ($tagIds) {
                $q->whereHas('tags', function ($q) use ($tagIds) {
                    $q->whereIn('tags.id', $tagIds);
                });
            })
            ->when(!empty($serviceIds), function ($q) use ($serviceIds) {
                $q->whereHas('services', function ($q) use ($serviceIds) {
                    $q->whereIn('services.id', $serviceIds);
                });
            })
            ->with(['user', 'country', 'tags', 'languages', 'specializations', 'services', 'categories', 'work_shifts', 'contacts']);
        
        $user = $request->user();

        if ($user) {
            $query->select([
                'garages.*',
                DB::raw('CASE WHEN user_favorites.id IS NOT NULL THEN 1 ELSE 0 END as is_favorite')
            ])
            ->leftJoin('user_favorites', function($join) use ($user) {
                $join->on('garages.id', '=', 'user_favorites.garage_id')
                     ->where('user_favorites.user_id', '=', $user->id)
                     ->where('user_favorites.deleted_at', null);
            });

            // Exclude blocked garages
            $query->whereNotExists(function($subquery) use ($user) {
                $subquery->select(DB::raw(1))
                    ->from('user_blocks')
                    ->whereRaw('user_blocks.garage_id = garages.id')
                    ->where('user_blocks.user_id', $user->id)
                    ->where('user_blocks.deleted_at', null);
            });
        }

        $garages = $query->get();
        return new GarageResource($garages);
    }
}
