Here is a comprehensive `README.md` for your library. It covers the architecture, the "Gatekeeper" logic, and how to implement it in an EasyAdmin project.

---

# EasyAdmin Field Dependency Resolver

A powerful, event-driven Symfony bundle for **EasyAdmin 4** that allows fields to dynamically appear, disappear, or change their data based on the values of other fields.

Unlike standard EasyAdmin dynamic forms, this library uses a **Redirect & Recovery** strategy. This ensures that even complex fields (like Autocomplete Entity types) are correctly re-initialized with full Doctrine support after a dependency change.

## Features

* **Closure-based logic:** Define dependencies using simple PHP closures.
* **Gatekeeper Logic:** Closures are only executed when all required parent values are present.
* **State Tracking:** Uses a hidden internal state to detect exactly which field changed.
* **Autocomplete Support:** Correctly "inflates" entity IDs back into full Doctrine objects during recovery.
* **Extensible:** Hook into the process using custom DTOs and Events.

---

## Installation

```bash
composer require ucscode/easyadmin-field-dependency-resolver
```

---

## Basic Usage

### 1. The Controller Setup

Inject the `FieldDependencyResolver` into your CRUD Controller and use it within your `configureFields` method.

```php
use Ucscode\EasyAdmin\FieldDependencyResolver\Service\FieldDependencyResolver;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private FieldDependencyResolver $resolver
    ) {}

    public function configureFields(string $pageName): iterable
    {
        return $this->resolver
            ->configureFields(fn() => [
                TextField::new('username'),
                ChoiceField::new('type')->setChoices([
                    'Individual' => 'individual',
                    'Organization' => 'org',
                ]),
            ])
            ->dependsOn('type', function(array $values) {
                // This only runs if 'type' is not null
                if ($values['type'] === 'org') {
                    yield TextField::new('companyName');
                    yield AssociationField::new('industry');
                }
            })
            ->resolve();
    }
}

```

---

## How It Works (Server-Side Lifecycle)

This library operates entirely on the server side by hijacking the Symfony Form submission process before it reaches the persistence layer.

### 1. State Encapsulation

The `FieldDependencyResolver` generates a `HiddenField` named `__resolver_state`. This field contains a JSON-encoded snapshot of the "monitored parents" at the time the form was rendered.

### 2. Difference Detection

When the form is submitted (e.g., via a "Save" button or a field that triggers a submit), an event listener (`DependencyStateListener`) listens to easyadmin's `BeforeCrudActionEvent` to compares:

* The **current POST data** (what the user just submitted).
* The **`__resolver_state`** (what the values were before the submission).

### 3. The Redirect Loop

If a difference is detected in any monitored field:

1. The listener **intercepts** the request before the Controller can persist the data.
2. The current POST data is stored in the `ResolverDataBridge` (Session).
3. A `RedirectResponse` is issued to the same URL (GET request) which evades the submission.

### 4. Data Recovery & Dynamic Yielding

On the subsequent GET request:

1. The `DependencyFormExtension` detects data in the `ResolverDataBridge`.
2. It injects this data back into the form fields, ensuring `EntityType` fields have their choices correctly populated.
3. The `FieldDependencyResolver` runs its closures. Since the parent values are now present in the Bridge, the dependent fields are **yielded** and rendered in the UI.

---

## Architecture Summary

| Component | Responsibility |
| --- | --- |
| **`FieldDependencyResolver`** | Defines dependencies and yields fields based on available data. |
| **`DependencyStateListener`** | Compares POST vs. Hidden State; triggers the redirect. |
| **`ResolverDataBridge`** | Acts as temporary storage for form data across the redirect. |
| **`DependencyFormExtension`** | Reconstructs the form state from the Bridge during the GET request. |

---

## Advantages of this Approach

* **No Custom JavaScript:** Works with EasyAdmin's native behavior without needing to maintain JS assets.
* **Validation Friendly:** Since the form reloads, Symfony's validation and EasyAdmin's `Autocomplete` subscribers run naturally.
* **Complex Dependencies:** Allows for multi-level dependencies (e.g., Country -> State -> City) because each step re-evaluates the entire form.

---

## Advanced: Events & Data Refinement

You can modify the data during the transition using the `DependencyChangedEvent`. This is useful for clearing specific fields when a parent changes.

### Create a Subscriber

```php
use Ucscode\EasyAdmin\FieldDependencyResolver\Event\DependencyChangedEvent;

class DependencySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            DependencyChangedEvent::class => 'onDependencyChange',
        ];
    }

    public function onDependencyChange(DependencyChangedEvent $event): void
    {
        $data = $event->getPostData();

        // If the type changes, we might want to force clear the company name
        if ($data->get('type') === 'individual') {
            $data->set('companyName', null);
        }
    }
}

```

---

## Architecture Checklist

If you are extending this library, ensure your namespaces align with the following structure:

| Namespace | Role |
| --- | --- |
| `..\Service` | `FieldDependencyResolver`, `ResolverDataBridge` |
| `..\Event` | `DependencyChangedEvent`, `PostFieldInflationEvent` |
| `..\EventListener` | `DependencyStateListener` |
| `..\Form\Extension` | `DependencyFormExtension` |

---

## Standalone Quality Sniffing

To verify the library's integrity without a full Symfony app, use the following tools:

```bash
# Static Analysis
vendor/bin/phpstan analyze src

# Coding Standards
vendor/bin/php-cs-fixer fix src --dry-run

# Dependency Validation
vendor/bin/deptrac

```

---

## License

MIT
