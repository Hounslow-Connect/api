<?php

namespace App\Http\Requests\OrganisationEvent;

use App\Http\Requests\HasMissingValues;
use App\Models\File;
use App\Models\Organisation;
use App\Rules\FileIsMimeType;
use App\Rules\FileIsPendingAssignment;
use App\Rules\IsOrganisationAdmin;
use App\Rules\MarkdownMaxLength;
use App\Rules\MarkdownMinLength;
use App\Rules\UkPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    use HasMissingValues;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if ($this->user()->isOrganisationAdmin(Organisation::find($this->organisation_id))) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'organisation_id' => ['required', 'exists:organisations,id', new IsOrganisationAdmin($this->user('api'))],
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after:today'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time' => ['required', 'date_format:H:i:s', 'after_or_equal:start_time'],
            'intro' => ['required', 'string', 'min:1', 'max:300'],
            'description' => [
                'required',
                'string',
                new MarkdownMinLength(1),
                new MarkdownMaxLength(3000, 'Description tab - The long description must be 3000 characters or fewer.'),
            ],
            'is_free' => ['required', 'boolean'],
            'fees_text' => [
                'nullable',
                'string',
                'min:1',
                'max:255',
                'required_if:is_free,false',
            ],
            'fees_url' => [
                'nullable',
                'url',
                'min:1',
                'max:255',
                'required_if:is_free,false',
            ],
            'organiser_name' => ['nullable', 'string', 'min:1', 'max:255'],
            'organiser_phone' => [
                'nullable',
                'string',
                'min:1',
                'max:255',
                new UkPhoneNumber(),
                Rule::requiredIf(function () {
                    return !empty($this->organiser_name) && empty($this->organiser_email) && empty($this->organiser_url);
                }),
            ],
            'organiser_email' => [
                'nullable',
                'email',
                'max:255',
                Rule::requiredIf(function () {
                    return !empty($this->organiser_name) && empty($this->organiser_phone) && empty($this->organiser_url);
                }),
            ],
            'organiser_url' => [
                'nullable',
                'url',
                'max:255',
                Rule::requiredIf(function () {
                    return !empty($this->organiser_name) && empty($this->organiser_email) && empty($this->organiser_phone);
                }),
            ],
            'booking_title' => [
                'nullable',
                'string',
                'min:1',
                'max:255',
                'required_with:booking_summary,booking_url,booking_cta',
            ],
            'booking_summary' => [
                'nullable',
                'string',
                'min:1',
                'max:255',
                'required_with:booking_title,booking_url,booking_cta',
            ],
            'booking_url' => [
                'nullable',
                'url',
                'min:1',
                'max:255',
                'required_with:booking_summary,booking_title,booking_cta',
            ],
            'booking_cta' => [
                'nullable',
                'string',
                'min:1',
                'max:255',
                'required_with:booking_summary,booking_url,booking_title',
            ],
            'is_virtual' => ['required', 'boolean'],
            'location_id' => [
                'nullable',
                Rule::requiredIf(!$this->is_virtual),
                'exists:locations,id',
            ],
            'image_file_id' => [
                'nullable',
                'exists:files,id',
                new FileIsMimeType(File::MIME_TYPE_PNG),
                new FileIsPendingAssignment(),
            ],
        ];
    }

    /**
     * Check if the user requested only a preview of the update request.
     *
     * @return bool
     */
    public function isPreview(): bool
    {
        return $this->preview === true;
    }

    /**
     * @inheritDoc
     */
    public function messages()
    {
        $urlMessage = 'Please enter a valid web address in the correct format (starting with https:// or http://).';

        return [
            'organisation_id.required' => 'Details tab - Please select the name of your organisation from the dropdown list.',
            'fees_url.url' => $urlMessage,
            'organiser_url.url' => $urlMessage,
            'booking_url.url' => $urlMessage,
            'organiser_email.email' => "Please enter an email address users can use to contact your event organiser.",
        ];
    }
}
