<?php

namespace App\Http\Requests\Search\Events;

use App\Contracts\EventSearch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Request extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'query' => [
                'required_without_all:category,is_free,is_virtual,location',
                'string',
                'min:3',
                'max:255',
            ],
            'category' => [
                'required_without_all:query,is_free,is_virtual,location',
                'string',
                'min:1',
                'max:255',
            ],
            'is_free' => [
                'required_without_all:query,category,is_virtual,location',
                'boolean',
            ],
            'is_virtual' => [
                'required_without_all:query,category,is_free,location',
                'boolean',
            ],
            'location' => [
                'required_without_all:query,category,is_free,is_virtual',
                'required_if:order,distance',
                'array',
            ],
            'location.lat' => [
                'required_with:location',
                'numeric',
                'min:-90',
                'max:90',
            ],
            'location.lon' => [
                'required_with:location',
                'numeric',
                'min:-180',
                'max:180',
            ],
            'distance' => [
                'integer',
                'min:0',
            ],
            'order' => [
                Rule::in([
                    EventSearch::ORDER_RELEVANCE,
                    EventSearch::ORDER_DISTANCE,
                    EventSearch::ORDER_START,
                    EventSearch::ORDER_END,
                ])],
        ];
    }
}
