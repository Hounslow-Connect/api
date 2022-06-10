<?php

namespace App\Http\Controllers\Core\V1\OrganisationEvent;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganisationEvent\Calendar\ShowRequest;
use App\Models\File;
use App\Models\OrganisationEvent;
use DateTime;

class CalendarController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\OrganisationEvent\Image\ShowRequest $request
     * @param \App\Models\OrganisationEvent $organisationEvent
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @return \Illuminate\Http\Response
     */
    public function __invoke(ShowRequest $request, OrganisationEvent $organisationEvent)
    {
        $now = new DateTime();
        $start = $organisationEvent->start_date->copy();
        list($startHour, $startMinute, $startSecond) = explode(':', $organisationEvent->start_time);
        $start->hour($startHour)->minute($startMinute)->second($startSecond);
        $end = $organisationEvent->end_date->copy();
        list($endHour, $endMinute, $endSecond) = explode(':', $organisationEvent->end_time);
        $end->hour($endHour)->minute($endMinute)->second($endSecond);

        $vEvent = [
            'VERSION' => '2.0',
            'PRODID' => '-//hacksw/handcal//NONSGML v1.0//EN',
            'BEGIN' => 'VEVENT',
            'UID' => $organisationEvent->id,
            'DTSTAMP' => $now->format('Ymd\\THis\\Z'),
            'ORGANIZER' => null,
            'DTSTART' => $start->format('Ymd\\THis\\Z'),
            'DTEND' => $end->format('Ymd\\THis\\Z'),
            'SUMMARY' => $organisationEvent->title,
            'GEO' => null,
            'END' => 'VEVENT',
        ];

        if (!$organisationEvent->is_virtual) {
            $vEvent['GEO'] = $organisationEvent->location->lat . ';' . $organisationEvent->location->lon;
        }

        if ($organisationEvent->organiser_name) {
            $vEvent['ORGANIZER'] = 'CN=' . $organisationEvent->organiser_name;

            if ($organisationEvent->organiser_email) {
                $vEvent['ORGANIZER'] .= ':MAILTO:' . $organisationEvent->organiser_email;
            }
        }

        // Remove any empty rows
        $vEvent = array_filter($vEvent, function ($value) {
            return !!$value;
        });

        $vEvent = array_map(
            function ($key, $value) {

                return $key === 'ORGANIZER' ? $key . ';' . $value : $key . ':' . $value;
            },
            array_keys($vEvent),
            $vEvent
        );

        // Wrap the vEvent in a vCalendar
        array_unshift($vEvent, 'BEGIN:VCALENDAR');
        array_push($vEvent, 'END:VCALENDAR');

        return response(implode(
            PHP_EOL,
            $vEvent
        ))->header('Content-Type', 'text/calendar');
    }
}
