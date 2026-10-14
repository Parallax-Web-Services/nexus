<?php

namespace App\Http\Controllers;

use App\Facades\Nexus;
use GuzzleHttp\Client;

class ApiForwarderController extends Controller
{
    public function handle()
    {
        $service = Nexus::service();

        // Only requests that arrived on a configured service domain are
        // forwarded; any other unknown API path simply does not exist.
        abort_unless($service && $service['url'], 404);

        $client = new Client([
            'base_uri' => $service['url'],
        ]);

        $response = $client->request($this->request->method(), $this->request->path(), [
            'Accept' => 'application/json',
            'query' => $this->request->query->all()
        ]);

        return $response->getBody()->getContents();
    }
}
