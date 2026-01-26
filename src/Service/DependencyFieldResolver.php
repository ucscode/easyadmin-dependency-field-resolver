<?php

namespace Ucscode\EasyAdmin\FieldDependencyResolver\Service;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\HiddenField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

#[Autoconfigure(shared: false)] // Always create a new instance to prevent state leakage
class DependencyFieldResolver
{
    public const RESOLVER_STATE = '__resolver_state';

    /**
     * @var FieldInterface[]
     */
    private array $independentFields = [];

    /**
     * @var array{parents:string[],closure:\Closure}[]
     */
    private array $dependencies = [];

    /**
     * @var string[]
     */
    private array $monitoredParents = [];

    public function __construct(
        private ResolverDataBridge $bridge,
        private PropertyAccessorInterface $propertyAccessor,
        private AdminContextProvider $adminContextProvider,
    ) {
    }

    public function configureFields(\Closure $closure): self
    {
        $this->independentFields = iterator_to_array($closure());
        
        return $this;
    }

    /**
     * @param string|array<string> $parents
     */
    public function dependsOn(string|array $parents, \Closure $closure): self
    {
        $parents = is_string($parents) ? [$parents] : $parents;

        $this->dependencies[] = [
            'parents' => $parents,
            'closure' => $closure
        ];

        $this->monitoredParents = array_unique(array_merge($this->monitoredParents, $parents));

        return $this;
    }

    public function resolve(): iterable
    {
        // 1. Resolve Independent Fields
        foreach ($this->independentFields as $field) {
            yield $field;
        }

        // 2. Resolve Dependent Fields
        foreach ($this->dependencies as $dependency) {
            $parentValues = [];
            $allParentsSatisfied = true;

            foreach ($dependency['parents'] as $parent) {
                $val = $this->getValue($parent);

                // GATEKEEPER: Closure is NEVER called if a parent is has no data
                if ($val === null || (is_string($val) && $val === '')) {
                    $allParentsSatisfied = false;
                    break;
                }

                $parentValues[$parent] = $val;
            }

            if ($allParentsSatisfied) {
                $fields = $dependency['closure']($parentValues);
                $fields = is_iterable($fields) ? $fields : [$fields];

                foreach ($fields as $field) {
                    yield $field;
                }
            }
        }

        yield $this->createStateField();
    }

    public function getValue(string $propertyName, mixed $default = null): mixed
    {
        // 1. Check Bridge (Redirect Data)
        if ($this->bridge->hasData()) {
            return $this->bridge->get($propertyName, $default);
        }

        $context = $this->getAdminContext();
        if (!$context) {
            return $default;
        }

        $request = $context->getRequest();
        $formName = $context->getEntity()->getName();

        // 2. Check Request (Live POST)
        $postData = $request->request->all($formName);
        if (isset($postData[$propertyName])) {
            return $postData[$propertyName];
        }

        // 3. Check Entity (Database)
        try {
            $entity = $context->getEntity()->getInstance();
            return $this->propertyAccessor->getValue($entity, $propertyName) ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public function isFormPage(string $pageName): bool
    {
        return in_array($pageName, [Crud::PAGE_EDIT, Crud::PAGE_NEW], true);
    }

    private function createStateField(): HiddenField
    {
        $state = [];
        foreach ($this->monitoredParents as $parent) {
            $state[$parent] = $this->getValue($parent);
        }

        return HiddenField::new(self::RESOLVER_STATE)
            ->setFormTypeOptions([
                'data' => json_encode($state),
                'mapped' => false,
            ])
            ->onlyOnForms();
    }

    private function getAdminContext(): ?AdminContextInterface
    {
        return $this->adminContextProvider->getContext();
    }
}
