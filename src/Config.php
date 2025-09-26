<?php

namespace App;

class Config
{
    private array $data;

    public function __construct(string $configPath)
    {
        if (!file_exists($configPath)) {
            throw new \RuntimeException("Config file not found: {$configPath}");
        }

        $config = include $configPath;
        if (!is_array($config)) {
            throw new \RuntimeException('Config file must return an array.');
        }

        $this->data = $config;
    }

    public function get(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $value = $this->data;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
