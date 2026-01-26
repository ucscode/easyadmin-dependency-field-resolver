<?php

namespace Ucscode\EasyAdmin\FieldDependencyResolver\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Ucscode\EasyAdmin\FieldDependencyResolver\Dto\DataDto;

class PreCreateStateEvent extends Event
{
    public function __construct(private DataDto $stateDto)
    {
    }

    public function getStateDto(): DataDto
    {
        return $this->stateDto;
    }

    public function setStateDto(DataDto $state): void
    {
        $this->stateDto = $state;
    }
}
