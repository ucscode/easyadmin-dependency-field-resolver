<?php

namespace Ucscode\EasyAdmin\FieldDependencyResolver\Event;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;
use Ucscode\EasyAdmin\FieldDependencyResolver\Dto\DataDto;

class DependencyChangedEvent extends Event
{
    public function __construct(
        private AdminContext $context,
        private DataDto $postData,
        private Response $response
    ) {
    }

    public function getAdminContext(): AdminContext
    {
        return $this->context;
    }

    public function getPostData(): DataDto
    {
        return $this->postData;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }
}
