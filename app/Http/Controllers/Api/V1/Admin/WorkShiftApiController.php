<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkShiftRequest;
use App\Http\Requests\UpdateWorkShiftRequest;
use App\Http\Resources\Admin\WorkShiftResource;
use App\Models\WorkShift;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkShiftApiController extends Controller
{
    public function index()
    {
        //abort_if(Gate::denies('work_shift_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new WorkShiftResource(WorkShift::with(['garage'])->get());
    }

    public function store(StoreWorkShiftRequest $request)
    {
        $workShift = WorkShift::create($request->all());

        return (new WorkShiftResource($workShift))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(WorkShift $workShift)
    {
        abort_if(Gate::denies('work_shift_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new WorkShiftResource($workShift->load(['garage']));
    }

    public function update(UpdateWorkShiftRequest $request, WorkShift $workShift)
    {
        $workShift->update($request->all());

        return (new WorkShiftResource($workShift))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(WorkShift $workShift)
    {
        abort_if(Gate::denies('work_shift_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $workShift->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
