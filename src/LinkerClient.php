<?php
namespace Europedev\ZPLLinker;

use Illuminate\Support\Facades\Http;

class LinkerClient
{
    protected $apiUrl = 'https://linker.zakonyprolidi.cz/api/json';
    protected $apiKey = env('ZAKONY_LINKER_API_KEY') ?? 'ZDE_VLOZTE_VAS_API_KLIC'; // alternativne do .env vlozte ZAKONY_LINKER_API_KEY=ZDE_VLOZTE_VAS_API_KLIC

    public function hello($message)
    {
        return $this->call('Hello', ['Message' => $message]);
    }

    protected function call($method, array $data = [])
    {
        $url = "{$this->apiUrl}/{$method}";
        $response = Http::withHeaders([
            'ApiKey' => $this->apiKey,
        ])->post($url, $data);

        return $response->json();
    }

    // Implement other methods as needed
}