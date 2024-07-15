<?php

namespace App\Classes\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use App\Models\SellsyToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Exceptions\Selssy\ExceptionResult;
use Exception;

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

    protected function handleRequest($method, $url, $options = [])
    {
        // Ajoute l'en-tête d'autorisation pour chaque requête
        $options['headers']['Authorization'] = "Bearer {$this->getAccessToken()}";

        try {
            $response = $this->client->request($method, $url, $options);
            return json_decode($response->getBody(), true);
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

    private function getDomainFromEmail(string $email): ?string {
        $parts = explode('@', $email);
        return $parts[1] ?? null;
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

    function extractCustomFields($data) {
        $customFields = $data['_embed']['custom_fields'];
        $result = [];
        foreach ($customFields as $field) {
            // Vérifiez si la valeur est un tableau et prenez la première valeur si c'est le cas
            if (is_array($field['value'])) {
                $result[$field['code']] = $field['value'][0];
            } else {
                $result[$field['code']] = $field['value'];
            }
        }
        unset($data['_embed']);
        return array_merge($data, $result);
    }

    public function searchContactByEmail(string $email) {
        $query = sprintf('search?q=%s&type[]=contact&limit=50', $email);
        
        try {
            \Log::info('avant execute');
            $searchResult = $this->executeQuery($query);
            $finalResult = [];
            $nbContacts = count($searchResult);
            $result = $searchResult[0];
            $clientId = $result['companies'][0]['id'] ?? null;
            $contactId = $result['object']['id'] ?? null;
            if ($contactId && $nbContacts == 1) {
                $finalResult['contact'] = $this->getContactById($contactId);
            } else if($nbContacts > 1) {
                $finalResult['contact']['error'] = 'multiple';
            }
            if ($clientId) {
                $clientResult = $this->getClientById($clientId);
                $clientResult  = $this->extractCustomFields($clientResult);
                $finalResult['client'] = $clientResult;
                if ($staffId = $clientResult['progi-commerc2'] ?? false) {
                    $finalResult['staff'] = $this->searchByStaffId($staffId);
                }
            }
            $finalResult['x-search'] = $searchResult;
            return $finalResult;
        } catch(ExceptionResult $e)  {
            if($e->getMessage() == 'no_contact') {
                $ndd = $this->getDomainFromEmail($email);
                $finalResult = $this->searchFromNdd($ndd);
                return $finalResult;
            }
            if($e->getMessage() == 'multiple_client') {
                return array_merge(['error' => 'multiple_client'], $e->getData());
            }
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    public function searchFromNdd($ndd) {
        $query = sprintf('search?q=%s&type[]=contact&limit=50', $ndd);
        \Log::info('pas de contact recherche sur NDD : '.$ndd);
        try {
            $searchResult = $this->executeQuery($query);
            $finalResult = [];
            \Log::info('$searchResult');
            \Log::info($searchResult);
            $result = $searchResult[0];
            $clientId = $result['companies'][0]['id'] ?? null;
            if ($clientId) {
                $clientResult = $this->getClientById($clientId);
                $clientResult  = $this->extractCustomFields($clientResult);
                $finalResult['client'] = $clientResult;
                if ($staffId = $clientResult['progi-commerc2'] ?? false) {
                    $finalResult['staff'] = $this->searchByStaffId($staffId);
                }
            }
            $finalResult['x-search'] = $searchResult;
            return $finalResult;
        } catch(ExceptionResult $e)  {
            \Log::info($e->getMessage());
            return array_merge(['error' => $e->getMessage()], $e->getData());
        } catch (Exception $ex) {
            throw $ex;
        }
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
