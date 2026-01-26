<?php

namespace Ucscode\EasyAdmin\DependencyFieldResolver\Event;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Symfony\Contracts\EventDispatcher\Event;

class AfterFieldResolveEvent extends Event
{
    public function __construct(private iterable $fields)
    {
    }

    public function getFields(): array
    {
        return array_filter(iterator_to_array($this->fields), function ($field) {
            return $field instanceof FieldInterface;
        });
    }

    public function setFields(iterable|\Closure $fields): void
    {
        $this->fields = is_iterable($fields) ? $fields : $fields();
    }
}
