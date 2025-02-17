<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLanguageRequest;
use App\Http\Requests\UpdateLanguageRequest;
use App\Http\Resources\Admin\LanguageResource;
use App\Models\Language;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LanguageApiController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('language_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new LanguageResource(Language::all());
    }

    public function store(StoreLanguageRequest $request)
    {
        $language = Language::create($request->all());

        return (new LanguageResource($language))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Language $language)
    {
        abort_if(Gate::denies('language_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new LanguageResource($language);
    }

    public function update(UpdateLanguageRequest $request, Language $language)
    {
        $language->update($request->all());

        return (new LanguageResource($language))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Language $language)
    {
        abort_if(Gate::denies('language_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $language->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
