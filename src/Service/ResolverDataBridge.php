<?php

namespace Ucscode\EasyAdmin\DependencyFieldResolver\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Acts as a temporary bridge to store POST data during a dependency-induced redirect.
 */
class ResolverDataBridge
{
    private ?array $data = null; // Changed to nullable to track if loaded
    private const SESSION_KEY = 'ucscode_ea_resolver_data';

    public function __construct(public readonly RequestStack $requestStack)
    {
        // Constructor is now empty and safe for CLI/Boot
    }

    private function loadData(): void
    {
        // Only attempt to load if we haven't already
        if ($this->data !== null) {
            return;
        }

        try {
            $session = $this->requestStack->getSession();

            if ($session->has(self::SESSION_KEY)) {
                $this->data = $session->get(self::SESSION_KEY);
                $session->remove(self::SESSION_KEY);
                return;
            }
        } catch (\Exception $e) {
            // Fallback for CLI or contexts where session is truly unavailable
        }

        $this->data = [];
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function hasData(): bool
    {
        $this->loadData();

        return !empty($this->data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->loadData();

        return $this->data[$key] ?? $default;
    }

    public function persist(array $data): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $data);
    }
}
