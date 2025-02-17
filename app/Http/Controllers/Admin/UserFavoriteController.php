<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyUserFavoriteRequest;
use App\Http\Requests\StoreUserFavoriteRequest;
use App\Http\Requests\UpdateUserFavoriteRequest;
use App\Models\User;
use App\Models\UserFavorite;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserFavoriteController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('user_favorite_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $userFavorites = UserFavorite::with(['user', 'garage'])->get();

        return view('admin.userFavorites.index', compact('userFavorites'));
    }

    public function create()
    {
        abort_if(Gate::denies('user_favorite_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $garages = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.userFavorites.create', compact('garages', 'users'));
    }

    public function store(StoreUserFavoriteRequest $request)
    {
        $userFavorite = UserFavorite::create($request->all());

        return redirect()->route('admin.user-favorites.index');
    }

    public function edit(UserFavorite $userFavorite)
    {
        abort_if(Gate::denies('user_favorite_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $garages = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $userFavorite->load('user', 'garage');

        return view('admin.userFavorites.edit', compact('garages', 'userFavorite', 'users'));
    }

    public function update(UpdateUserFavoriteRequest $request, UserFavorite $userFavorite)
    {
        $userFavorite->update($request->all());

        return redirect()->route('admin.user-favorites.index');
    }

    public function show(UserFavorite $userFavorite)
    {
        abort_if(Gate::denies('user_favorite_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $userFavorite->load('user', 'garage');

        return view('admin.userFavorites.show', compact('userFavorite'));
    }

    public function destroy(UserFavorite $userFavorite)
    {
        abort_if(Gate::denies('user_favorite_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $userFavorite->delete();

        return back();
    }

    public function massDestroy(MassDestroyUserFavoriteRequest $request)
    {
        $userFavorites = UserFavorite::find(request('ids'));

        foreach ($userFavorites as $userFavorite) {
            $userFavorite->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
