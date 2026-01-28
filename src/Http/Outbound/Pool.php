<?php

namespace Maharlika\Http\Outbound;

class Pool
{
    protected Client $client;
    protected array $requests = [];

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Add a GET request to the pool.
     */
    public function get(string $url, array $query = []): static
    {
        $this->requests[] = ['method' => 'GET', 'url' => $url, 'options' => ['query' => $query]];
        return $this;
    }

    /**
     * Add a POST request to the pool.
     */
    public function post(string $url, array $data = []): static
    {
        $this->requests[] = ['method' => 'POST', 'url' => $url, 'options' => ['body' => $data]];
        return $this;
    }

    /**
     * Add a custom request to the pool.
     */
    public function add(string $method, string $url, array $options = []): static
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];
        return $this;
    }

    /**
     * Send all requests concurrently.
     */
    public function send(): array
    {
        if (empty($this->requests)) {
            return [];
        }

        $mh = curl_multi_init();
        $handles = [];
        
        // Add all handles
        foreach ($this->requests as $index => $request) {
            $ch = $this->createHandle($request);
            curl_multi_add_handle($mh, $ch);
            $handles[$index] = $ch;
        }
        
        // Execute all handles
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh);
            }
        } while ($active && $status == CURLM_OK);
        
        // Collect responses
        $responses = [];
        foreach ($handles as $index => $ch) {
            $response = curl_multi_getcontent($ch);
            $info = curl_getinfo($ch);
            
            $responses[$index] = $this->parseResponse($response, $info);
            
            curl_multi_remove_handle($mh, $ch);
        }
        
        curl_multi_close($mh);
        
        return $responses;
    }

    /**
     * Create a cURL handle for a request.
     */
    protected function createHandle(array $request)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request['method']);
        
        return $ch;
    }

    /**
     * Parse response (simplified version).
     */
    protected function parseResponse(string $response, array $info): Response
    {
        $headerSize = $info['header_size'];
        $body = substr($response, $headerSize);
        
        return new Response($body, $info['http_code'], [], $info);
    }
}