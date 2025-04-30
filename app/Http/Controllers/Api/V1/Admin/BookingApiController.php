<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Http\Resources\Admin\BookingResource;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class BookingApiController extends Controller
{
    public function index()
    {
        //abort_if(Gate::denies('booking_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $user_id = Auth::id();

        return new BookingResource(Booking::with(['user', 'garage'])->where('user_id', $user_id)->orderByDesc('id')->get());
    }

    public function store(StoreBookingRequest $request)
    {
        $booking = Booking::create($request->all());

        return (new BookingResource($booking))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Booking $booking)
    {
        // abort_if(Gate::denies('booking_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new BookingResource($booking->load(['user', 'garage']));
    }

    public function update(UpdateBookingRequest $request, Booking $booking)
    {
        $booking->update($request->all());

        return (new BookingResource($booking))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Booking $booking)
    {
        abort_if(Gate::denies('booking_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $booking->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function createBooking(Request $request)
    {
        $validator = validator($request->all(), [
            'garage_id' => 'required|integer|exists:garages,id',
            'datetime' => 'required|date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Create booking with a temporary reference number
        $booking = new Booking();
        $booking->reference_no = 'temp_' . time(); // Temporary reference
        $booking->user_id = auth()->id();
        $booking->garage_id = $request->garage_id;
        $booking->appointment_time = $request->datetime;
        $booking->status = 'pending';
        $booking->processing_status = 'n/a';
        $booking->save();

        // Now create the actual unique reference using the database ID
        $referenceNumber = 'wslk_' . $request->garage_id . '_' . rand(100000, 999999) . '_' . $booking->id;
        $booking->reference_no = $referenceNumber;
        $booking->save();

        return response()->json([
            'success' => true,
            'reference_number' => $referenceNumber,
            'message' => 'Booking created successfully',
        ], Response::HTTP_CREATED);
    }
}
