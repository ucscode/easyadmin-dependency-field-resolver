<?php

namespace Ucscode\EasyAdmin\DependencyFieldResolver\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\CrudFormType;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Ucscode\EasyAdmin\DependencyFieldResolver\Dto\DataDto;
use Ucscode\EasyAdmin\DependencyFieldResolver\Event\DependencyDataRecoveredEvent;
use Ucscode\EasyAdmin\DependencyFieldResolver\Event\DependencyFieldRehydrateEvent;
use Ucscode\EasyAdmin\DependencyFieldResolver\Service\ResolverDataBridge;

class DependencyFormExtension extends AbstractTypeExtension
{
    public function __construct(
        private ResolverDataBridge $bridge,
        protected EventDispatcherInterface $eventDispatcher,
        protected AdminContextProvider $adminContextProvider,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [CrudFormType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $request = $this->bridge->requestStack->getCurrentRequest();

        // Only trigger on GET (recovery) if the bridge has data
        if (!$request?->isMethod('GET') || !$this->bridge->hasData()) {
            return;
        }

        /**
         * CrudAutocompleteSubscriber is the pain giver :(
         *
         * @see EasyCorp\Bundle\EasyAdminBundle\Form\EventListener\CrudAutocompleteSubscriber
         */
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            if (!$this->bridge->hasData()) {
                return;
            }

            $adminContext = $this->adminContextProvider->getContext();
            $form = $event->getForm();
            $postDataDto = new DataDto($this->bridge->getData());

            $this->eventDispatcher->dispatch(new DependencyDataRecoveredEvent(
                $adminContext,
                $form,
                $postDataDto
            ));

            foreach ($postDataDto->all() as $name => $value) {
                if (!$form->has($name)) {
                    continue;
                }

                $child = $form->get($name);
                $config = $child->getConfig();
                $resolvedValue = $value;

                if (is_a($config->getType()->getInnerType(), EntityType::class)) {
                    $entities = $this->normalizeEntityValue($child, $value);
                    $resolvedValue = $config->getOption('multiple') ? $entities : ($entities[0] ?? null);

                    $options = $config->getOptions();
                    $options['choices'] = $entities;
                    unset(
                        $options['em'],
                        $options['loader'],
                        $options['choice_list'],
                        $options['choices_as_values']
                    );

                    $form->add($name, EntityType::class, $options);
                }

                // --- EVENT: POST-FIELD INFLATION ---
                // Dispatched for EVERY field. Allows user to modify $resolvedValue
                // (e.g., for non-entity types or extra processing).
                $inflationEvent = new DependencyFieldRehydrateEvent(
                    $adminContext,
                    $child,
                    $name,
                    $resolvedValue
                );

                $this->eventDispatcher->dispatch($inflationEvent);

                $form->get($name)->setData($inflationEvent->getValue());
            }

        }, 100); // HIGH priority
    }

    private function normalizeEntityValue(FormInterface $form, mixed $value): array
    {
        $config = $form->getConfig();
        $options = $config->getOptions();

        $class = $options['class'];
        $em = $options['em'];
        $idReader = $options['id_reader'];

        if ($value === null || $value === '') {
            return [];
        }

        // already entities → keep
        if (is_object($value)) {
            return [$value];
        }

        if (is_iterable($value)) {
            $value = array_values($value);

            if ($value && is_object($value[0])) {
                return $value;
            }
        }

        // IDs → entities
        return $em->getRepository($class)->findBy([
            $idReader->getIdField() => (array) $value,
        ]);
    }

}
