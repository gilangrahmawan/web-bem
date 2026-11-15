<?php

namespace App\Http\Requests\Public;

use App\Support\CalendarFeed;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Rentang tanggal data kalender publik (dikirim FullCalendar). */
class CalendarFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && $this->range() === null) {
                    $validator->errors()->add('end', 'Rentang tanggal maksimal '.CalendarFeed::MAX_RANGE_DAYS.' hari.');
                }
            },
        ];
    }

    /** @return array{0: CarbonInterface, 1: CarbonInterface}|null */
    public function range(): ?array
    {
        return CalendarFeed::parseRange($this->input('start'), $this->input('end'));
    }
}
