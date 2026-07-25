<?php

namespace App\Enums;

enum Plan: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Premium = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Pro => 'Pro',
            self::Premium => 'Premium',
        };
    }

    /**
     * @return array{features: list<string>, limits: array<string, int|null>}
     */
    public function config(): array
    {
        /** @var array{features?: list<string>, limits?: array<string, int|null>} $config */
        $config = config('plans.'.$this->value, []);

        return [
            'features' => $config['features'] ?? [],
            'limits' => $config['limits'] ?? [],
        ];
    }

    /**
     * @return list<string>
     */
    public function features(): array
    {
        return $this->config()['features'];
    }

    public function allows(string $feature): bool
    {
        return in_array($feature, $this->features(), true);
    }

    /**
     * The numeric limit for a key. null = unlimited, 0 = not available.
     */
    public function limit(string $key): ?int
    {
        $limits = $this->config()['limits'];

        return array_key_exists($key, $limits) ? $limits[$key] : 0;
    }
}
