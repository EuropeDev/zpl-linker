<?php

namespace Europedev\ZPLLinker;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Exception;
use DateTime;

// Custom exception for API-related errors
class ApiException extends Exception {}

class LinkerClient
{
    protected $apiUrl;
    protected $apiKey;

    public function __construct($apiKey = null, $apiUrl = null)
    {
        $this->apiKey = $apiKey ?: env('ZPL_API_KEY');
        $this->apiUrl = $apiUrl ?: env('ZPL_API_URL', 'https://linker.zakonyprolidi.cz/api');
    } 
 
    /**
     * Sends a hello message to the API.
     *
     * @param string $message The message to send.
     * @return array The API response.
     * @throws ApiException If the API call fails.
     */
    public function hello($message)
    {
        $res = $this->call('Hello','json', ['Message' => $message]);
        return $res['Message'] ?? null;
    }

    public function form(string $html, string $collection = null, DateTime $effectiveDate = null, bool $removeExisting = true, string $attributes = null)
    {
        $data = [
            'html' => $html,
            'collection' => $collection,
            'effectiveDate' => $effectiveDate,
            'removeExisting' => $removeExisting,
            'attributes' => $attributes,
        ];

        $rules = [
            'html' => 'required|string',
            'collection' => ['nullable', 'string', Rule::in(['cs', 'ms'])],
            'effectiveDate' => 'nullable|date',
            'removeExisting' => 'required|boolean', 
            'attributes' => 'nullable|string',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $this->call('process','form', [
            'SourceHtml' => $data['html'],
            'Collection' => $data['collection'],
            'EffectiveDate' => $effectiveDate ? $effectiveDate->format('Y-m-d') : null,
            'RemoveExisting' => $data['removeExisting'],
            'Attributes' => $data['attributes'],
        ]);
    }

    /**
     * Sends a simple process request to the API.
     *
     * @param string $html The HTML content to process.
     * @param string $id Id of previous request to update, null for new request.
     * @param string|null $collection The collection to use, null for all.
     * @param DateTime|null $effectiveDate The effective date.
     * @param bool $removeExisting Whether to remove existing links.
     * @param string|null $attributes Additional HTML attributes.
     * @return array The API response.
     * @throws ValidationException If validation fails.
     * @throws ApiException If the API call fails.
     */
    public function simple(string $html, int $id = null, string $collection = null, DateTime $effectiveDate = null, bool $removeExisting = true, string $attributes = null)
    {
        $data = [
            'id' => $id,
            'html' => $html,
            'collection' => $collection,
            'effectiveDate' => $effectiveDate,
            'removeExisting' => $removeExisting,
            'attributes' => $attributes,
        ];

        $rules = [
            'id' => 'nullable|integer',
            'html' => 'required|string',
            'collection' => ['nullable', 'string', Rule::in(['cs', 'ms'])],
            'effectiveDate' => 'nullable|date',
            'removeExisting' => 'required|boolean', 
            'attributes' => 'nullable|string',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $this->call('Process','json', [
            'id' => $data['id'],
            'SourceHtml' => $data['html'],
            'Collections' => $data['collection'] ? array($data['collection']) : null,
            'EffectDate' => $effectiveDate ? $effectiveDate->format('Y-m-d') : null,
            'RemoveExisting' => $data['removeExisting'],
            'Attributes' => $data['attributes'] ?? null,
        ]);
    }

   /* public function complex(string $html, string $collection = null, DateTime $effectiveDate = null, bool $removeExisting = true, string $attributes = null)
    {
        $data = [
            'html' => $html,
            'collection' => $collection,
            'effectiveDate' => $effectiveDate,
            'removeExisting' => $removeExisting,
            'attributes' => $attributes,
        ];

        $rules = [
            'html' => 'required|string',
            'collection' => ['nullable', 'string', Rule::in(['cs', 'ms'])],
            'effectiveDate' => 'nullable|date',
            'removeExisting' => 'required|boolean',
            'attributes' => 'nullable|string',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $this->call('add','json', [
            'Html' => $data['html'],
            'Collection' => $data['collection'],
            'EffectiveDate' => $effectiveDate ? $effectiveDate->format('Y-m-d') : null,
            'RemoveExisting' => $data['removeExisting'],
            'Attributes' => $data['attributes'],
        ]);
    }*/
    /**
     * Performs an API call.
     *
     * @param string $method The API method to call.
     * @param array $data The data to send in the request.
     * @param string $type The request type (json or form).
     * @return array The API response.
     * @throws ApiException If the API call fails.
     */
    protected function call($method, $type = 'json', array $data = [])
    {
        $url = "{$this->apiUrl}/{$type}/{$method}";

        if ($type === 'json') {
            $response = Http::withHeaders([
                'ApiKey' => $this->apiKey,
                'Content-Type' => 'application/json; charset=UTF-8',
            ])->post($url, $data);
        } elseif ($type === 'form') {
            $response = Http::asForm()->withHeaders([
                'ApiKey' => $this->apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post($url, $data);
        } else {
            throw new ApiException("Invalid request type: {$type}");
        }

        if ($response->successful()) {
            if($type === 'json'){
                return $response->json();
            } else {
                return $response->body();
            }        } else {
            print_r(array(
                'Status' => $response->status(),
                'Body' => $response->body(),
                'Headers' => $response->headers(),  
            ));
        }

        $errorDetails = $response->json()['error'] ?? $response->body();

        if ($response->clientError()) {
            throw new ApiException("Client error ({$response->status()}): $errorDetails");
        }

        if ($response->serverError()) {
            throw new ApiException("Server error ({$response->status()}): $errorDetails");
        }

        throw new ApiException("Failed to call {$method} method. Error: $errorDetails");
    }
}