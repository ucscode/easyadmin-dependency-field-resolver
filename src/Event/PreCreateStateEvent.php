<?php

namespace Ucscode\EasyAdmin\DependencyFieldResolver\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Ucscode\EasyAdmin\DependencyFieldResolver\Dto\DataDto;

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
