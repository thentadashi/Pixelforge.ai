<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public const SLOTS = ['09:00', '10:30', '13:00', '14:30'];

    public function availability(Request $request)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:'.now()->addMonths(3)->toDateString()]);
        $taken = DB::table('bookings')->where('date', $data['date'])->whereNotNull('reservation_key')->pluck('slot')->all();

        return response()->json(['slots' => array_values(array_filter(self::SLOTS, fn ($slot) => ! in_array($slot, $taken) && Carbon::parse($data['date'].' '.$slot)->isFuture())), 'timezone' => 'Asia/Manila']);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:255', 'organization' => 'required|string|max:255', 'sector' => ['required', Rule::in(['Business', 'Corporation', 'Government', 'Education'])], 'requirements' => 'required|string|max:10000', 'date' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:'.now()->addMonths(3)->toDateString(), 'slot' => ['required', Rule::in(self::SLOTS)]]);
        if (! Carbon::parse($data['date'].' '.$data['slot'])->isFuture()) {
            throw ValidationException::withMessages(['slot' => 'Choose a future time.']);
        }
        $data['reservation_key'] = $data['date'].'/'.$data['slot'];
        $data['reference'] = (string) Str::uuid();
        $data['created_at'] = $data['updated_at'] = now();
        try {
            DB::table('bookings')->insert($data);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['slot' => 'This time was just reserved. Choose another slot.']);
        }

        return response()->json(['reference' => $data['reference'], 'message' => 'Your discovery call request has been saved. PixelForge will contact you to confirm the meeting details.'], 201);
    }

    public function update(Request $request, int $id)
    {
        abort_unless(DB::table('bookings')->where('id', $id)->exists(), 404);
        $data = $request->validate(['status' => ['required', Rule::in(['requested', 'confirmed', 'completed', 'cancelled'])], 'notes' => 'nullable|string|max:10000']);
        if ($data['status'] === 'cancelled') {
            $data['reservation_key'] = null;
        } else {
            $booking = DB::table('bookings')->find($id);
            $data['reservation_key'] = $booking->date.'/'.$booking->slot;
        }
        $data['updated_at'] = now();
        try {
            DB::table('bookings')->where('id', $id)->update($data);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['status' => 'Another booking holds this slot. Leave this booking cancelled.']);
        }

        return response()->json(['message' => 'Booking updated.']);
    }
}
