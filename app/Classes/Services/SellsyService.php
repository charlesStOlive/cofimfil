<?php

namespace App\Classes\Services;

use Exception;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Models\SellsyToken;
use App\Settings\AnalyseSettings;
use Illuminate\Support\Facades\Log;
use App\Exceptions\Sellsy\ExceptionResult;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

class SellsyService
{
    protected $clientId;
    protected $clientSecret;
    protected $client;

    public function __construct()
    {
        $this->clientId = env('SELLSY_CLIENT_ID');
        $this->clientSecret = env('SELLSY_CLIENT_SECRET');
        $this->client = new Client([
            'base_uri' => 'https://api.sellsy.com/v2/',
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    protected function requestAccessToken()
    {
        try {
            $response = $this->client->post('https://login.sellsy.com/oauth2/access-tokens', [
                'json' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $expiresAt = Carbon::now()->addSeconds($data['expires_in']);

            return [
                'access_token' => $data['access_token'],
                'expires_at' => $expiresAt,
            ];
        } catch (ConnectException $e) {
            Log::error('Connection error: ' . $e->getMessage());
            throw new \Exception('Connection error: Unable to connect to Sellsy API');
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $errorMessage = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error("Request error (Status: $statusCode): $errorMessage");
            throw new \Exception("Request error (Status: $statusCode): $errorMessage");
        } catch (\Exception $e) {
            Log::error('Unexpected error: ' . $e->getMessage());
            throw new \Exception('Unexpected error: ' . $e->getMessage());
        }
    }

    protected function handleRequest($method, $url, $options = [], $retry = true)
    {
        // Ajoute l'en-tête d'autorisation pour chaque requête
        $options['headers']['Authorization'] = "Bearer {$this->getAccessToken()}";

        try {
            $response = $this->client->request($method, $url, $options);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            if ($statusCode == 401 && $retry) {
                // Token has been revoked, request a new one and retry once
                $this->requestAccessToken();
                return $this->handleRequest($method, $url, $options, false);
            }
            $errorMessage = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Log::error("Request error (Status: $statusCode): $errorMessage");
            throw new \Exception("Request error (Status: $statusCode): $errorMessage");
        } catch (ConnectException $e) {
            Log::error('Connection error: ' . $e->getMessage());
            throw new \Exception('Connection error: Unable to connect to Sellsy API');
        } catch (\Exception $e) {
            Log::error('Unexpected error: ' . $e->getMessage());
            throw new \Exception('Unexpected error: ' . $e->getMessage());
        }
    }

    public function getAccessToken()
    {
        $token = SellsyToken::first();

        if (!$token || Carbon::now()->gte($token->expires_at)) {
            $newTokenData = $this->requestAccessToken();
            //\Log::info($newTokenData);
            $expiresAt = Carbon::now()->addSeconds($newTokenData['expires_at']);
            if ($token) {
                $token->update([
                    'access_token' => $newTokenData['access_token'],
                    'expires_at' => $expiresAt,
                ]);
            } else {
                SellsyToken::create([
                    'access_token' => $newTokenData['access_token'],
                    'expires_at' => $expiresAt,
                ]);
            }

            return $newTokenData['access_token'];
        }

        return $token->access_token;
    }

    public function getContactByEmail($email = 'alexis.clement@suscillon.com')
    {
        $options = [
            'query' => [
                'email' => $email,
            ]
        ];

        return $this->handleRequest('GET', 'contacts', $options);
    }

    

    public function executeQuery($query) {
        $allData = [];
        $uniqueCompanyId = null;
        $stopBecauseOfNonUnique = false;

        do {
            $data = $this->handleRequest('GET', $query);

            
            $count = $data['pagination']['count'] ?? null;
            $total = $data['pagination']['total'] ?? null;

            if($count == 0) {
                throw new ExceptionResult('no_contact');
            }

            foreach ($data['data'] as $item) {
                foreach ($item['companies'] as $company) {
                    $companyId = $company['id'] ?? null;
                    if (!$uniqueCompanyId) $uniqueCompanyId = $companyId;
                    if ($uniqueCompanyId != $companyId) {
                        $stopBecauseOfNonUnique = true;
                    }
                }
                $filteredItem = $item;
                $allData[] = $filteredItem;
                if ($stopBecauseOfNonUnique) {
                    throw new ExceptionResult('multiple_client', ['new_id' => $companyId,'previous_id' => $uniqueCompanyId, 'x-search' => $allData]);
                }
            }
            if ($count == $total) {
                break;
            }
            if (isset($data['pagination']['offset'])) {
                $query = sprintf('%s&offset=%s', $query, $data['pagination']['offset']);
            } else {
                break;
            }
        } while (true);

        return $allData;
    }

    

    public function getContactById($id) {
        $queryParams = [
            'field' => ['first_name', 'last_name', 'email', 'position',]
        ];
        $queryParams = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        return $this->handleRequest('GET', "contacts/{$id}?{$queryParams}");
    }

    public function getClientById($id) {
        $queryParams = [
            'embed' => ['cf.197833', 'cf.282914', 'cf.182029'],
            'field' => ['name', '_embed']
        ];
        $queryParams = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        return $this->handleRequest('GET', "companies/{$id}?{$queryParams}");
    }

    public function searchByStaffId($id) {
        $queryParams = [
            'field' => ['email', 'firstname', 'lastname'],
        ];
        $queryParams = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        return $this->handleRequest('GET', "staffs/{$id}?{$queryParams}");
    }

    public function getCustomFields() {
        return $this->handleRequest('GET', "custom-fields?limit=50");
    }

    
}
