<?php

namespace Ucscode\EasyAdmin\DependencyFieldResolver\Event;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\Form\FormInterface;
use Ucscode\EasyAdmin\DependencyFieldResolver\Dto\DataDto;

class DependencyDataRecoveredEvent
{
    public function __construct(
        private AdminContext $adminContext,
        private FormInterface $form,
        private DataDto $postDataDto,
    ) {
        // throw new \Exception('Not implemented');
    }

    public function getAdminContext(): AdminContext
    {
        return $this->adminContext;
    }

    public function getForm(): FormInterface
    {
        return $this->form;
    }

    public function getPostData(): DataDto
    {
        return $this->postDataDto;
    }
}
