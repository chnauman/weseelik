<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserFavoriteRequest;
use App\Http\Requests\UpdateUserFavoriteRequest;
use App\Http\Resources\Admin\UserFavoriteResource;
use App\Models\UserFavorite;
use Gate;
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
}
