<?php

namespace App\Support;

use \Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;

/**
 * Class Nexus
 *
 * @package App\Support
 */
class Nexus
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $activeService;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = config('nexus');

        $this->determineActiveService();
    }

    public function determineActiveService()
    {
        $request = app(Request::class);
        $host = $request->getHost();

        foreach ($this->config['services'] as $service => $config) {
            if ($config['domain'] == $host) {
                return $this->activeService = $service;
            }
        }

        return $this->activeService = 'nexus';
    }

    public function service()
    {
        if ($this->activeService == 'nexus') {
            return false;
        }

        return [
            'name' => $this->activeService,
            'url' => $this->config['services'][$this->activeService]['url'] ?? ''
        ];
    }
}
