<?php

namespace Ucscode\EasyAdmin\FieldDependencyResolver\Event;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\Form\FormInterface;

class DependencyFieldRehydrateEvent
{
    public function __construct(
        private AdminContext $context,
        private FormInterface $childField,
        private string $name,
        private mixed $value
    ) {
        // throw new \Exception('Not implemented');
    }

    public function getAdminContext(): AdminContext
    {
        return $this->context;
    }

    public function getField(): FormInterface
    {
        return $this->childField;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}
