<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserFavoriteRequest;
use App\Http\Requests\UpdateUserFavoriteRequest;
use App\Http\Resources\Admin\UserFavoriteResource;
use App\Models\UserFavorite;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserFavoriteApiController extends Controller
{
    public function index()
    {
        //abort_if(Gate::denies('user_favorite_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new UserFavoriteResource(UserFavorite::with(['user', 'garage'])->get());
    }

    public function store(StoreUserFavoriteRequest $request)
    {
        $userFavorite = UserFavorite::create($request->all());

        return (new UserFavoriteResource($userFavorite))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(UserFavorite $userFavorite)
    {
        // abort_if(Gate::denies('user_favorite_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new UserFavoriteResource($userFavorite->load(['user', 'garage']));
    }

    public function update(UpdateUserFavoriteRequest $request, UserFavorite $userFavorite)
    {
        $userFavorite->update($request->all());

        return (new UserFavoriteResource($userFavorite))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(UserFavorite $userFavorite)
    {
        abort_if(Gate::denies('user_favorite_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $userFavorite->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function getUserFavorties(Request $request)
    {
        $user = $request->user();
        return new UserFavoriteResource(UserFavorite::with(['user', 'garage'])->where('user_id',$user->id)->get());
        //return new UserFavoriteResource( $user->favorties())->get());
    }

    /**
     * Add a garage to the authenticated user's favorites
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleFavorites(Request $request)
    {
        // Validate request
        $request->validate([
            'garage_id' => 'required|exists:garages,id',
        ]);

        // Get the authenticated user
        $user = $request->user();

        // Check if this garage is already in the user's favorites
        $existingFavorite = UserFavorite::where('user_id', $user->id)
            ->where('garage_id', $request->garage_id)
            ->first();

        if ($existingFavorite) {
            // Remove from favorites
            $existingFavorite->delete();
            
            return response()->json([
                'success' => true,
                'code' => 'removed',
                'message' => 'Garage removed from favorites successfully'
            ]);
        }

        // Create new favorite
        $userFavorite = UserFavorite::create([
            'user_id' => $user->id,
            'garage_id' => $request->garage_id,
        ]);

        return response()->json([
            'success' => true,
            'code' => 'added',
            'message' => 'Garage added to favorites successfully',
            'data' => new UserFavoriteResource($userFavorite)
        ], Response::HTTP_CREATED);
    }

    /**
     * Update the note for a favorite garage
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateNote(Request $request)
    {
        // Validate request
        $request->validate([
            'favorite_id' => 'required|exists:user_favorites,id',
            'note' => 'required|string|max:1000',
        ]);

        // Get the authenticated user
        $user = $request->user();

        // Find the favorite and ensure it belongs to the authenticated user
        $userFavorite = UserFavorite::where('id', $request->favorite_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$userFavorite) {
            return response()->json([
                'success' => false,
                'message' => 'Favorite not found or does not belong to you'
            ], Response::HTTP_NOT_FOUND);
        }

        // Update the note
        $userFavorite->update([
            'note' => $request->note
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Note updated successfully',
            'data' => new UserFavoriteResource($userFavorite)
        ]);
    }
}
