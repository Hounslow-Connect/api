<?php

namespace App\Models\Mutators;

use DateTime;

trait OrganisationEventMutators
{
    /**
     * Get the Start date time as a Carbon instance.
     *
     * @return Carbon/CarbonImmutable
     */
    public function getStartDateTimeAttribute()
    {
        $start = $this->start_date->copy();
        list($startHour, $startMinute, $startSecond) = explode(':', $this->start_time);
        $start->hour($startHour)->minute($startMinute)->second($startSecond);

        return $start;
    }

    /**
     * Get the End date time as a Carbon instance.
     *
     * @return Carbon/CarbonImmutable
     */
    public function getEndDateTimeAttribute()
    {
        $end = $this->end_date->copy();
        list($endHour, $endMinute, $endSecond) = explode(':', $this->end_time);
        $end->hour($endHour)->minute($endMinute)->second($endSecond);

        return $end;
    }

    /**
     * Return a link which will add the event to a Google calendar.
     *
     * @return string
     */
    public function getGoogleCalendarLinkAttribute()
    {
        return sprintf(
            'https://calendar.google.com/calendar/render?action=TEMPLATE&dates=%s%%2F%s&details=%s&location=%s&text=%s',
            urlencode($this->startDateTime->format('Ymd\\THis\\Z')),
            urlencode($this->endDateTime->format('Ymd\\THis\\Z')),
            urlencode($this->title),
            $this->is_virtual ? '' : urlencode(implode(',', [
                $this->location->address_line_1,
                $this->location->city,
                $this->location->postcode,
            ])),
            urlencode($this->intro)
        );
    }

    /**
     * Return a link which will add the event to a Microsoft calendar.
     *
     * @return string
     */
    public function getMicrosoftCalendarLinkAttribute()
    {
        return sprintf(
            'https://outlook.office.com/calendar/0/deeplink/compose?path=%%2Fcalendar%%2Faction%%2Fcompose&rru=addevent&startdt=%s&enddt=%s&subject=%s&location=%s&body=%s',
            urlencode($this->startDateTime->format(DateTime::ATOM)),
            urlencode($this->endDateTime->format(DateTime::ATOM)),
            urlencode($this->title),
            $this->is_virtual ? '' : urlencode(implode(',', [
                $this->location->address_line_1,
                $this->location->city,
                $this->location->postcode,
            ])),
            urlencode($this->intro)
        );
    }

    /**
     * Return a link which will return the .ics file which can be added to an Apple calendar.
     *
     * @return string
     */
    public function getAppleCalendarLinkAttribute()
    {
        return secure_url('/core/v1/organisation-events/' . $this->id . '/event.ics');
    }
}
