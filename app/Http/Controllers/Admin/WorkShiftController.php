<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyWorkShiftRequest;
use App\Http\Requests\StoreWorkShiftRequest;
use App\Http\Requests\UpdateWorkShiftRequest;
use App\Models\Garage;
use App\Models\WorkShift;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkShiftController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('work_shift_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $workShifts = WorkShift::with(['garage'])->get();

        return view('admin.workShifts.index', compact('workShifts'));
    }

    public function create()
    {
        abort_if(Gate::denies('work_shift_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $garages = Garage::pluck('title', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.workShifts.create', compact('garages'));
    }

    public function store(StoreWorkShiftRequest $request)
    {
        $workShift = WorkShift::create($request->all());

        return redirect()->route('admin.work-shifts.index');
    }

    public function edit(WorkShift $workShift)
    {
        abort_if(Gate::denies('work_shift_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $garages = Garage::pluck('title', 'id')->prepend(trans('global.pleaseSelect'), '');

        $workShift->load('garage');

        return view('admin.workShifts.edit', compact('garages', 'workShift'));
    }

    public function update(UpdateWorkShiftRequest $request, WorkShift $workShift)
    {
        $workShift->update($request->all());

        return redirect()->route('admin.work-shifts.index');
    }

    public function show(WorkShift $workShift)
    {
        abort_if(Gate::denies('work_shift_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $workShift->load('garage');

        return view('admin.workShifts.show', compact('workShift'));
    }

    public function destroy(WorkShift $workShift)
    {
        abort_if(Gate::denies('work_shift_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $workShift->delete();

        return back();
    }

    public function massDestroy(MassDestroyWorkShiftRequest $request)
    {
        $workShifts = WorkShift::find(request('ids'));

        foreach ($workShifts as $workShift) {
            $workShift->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
