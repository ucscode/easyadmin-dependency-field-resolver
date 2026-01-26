# EasyAdmin Dependency Field Resolver

A lightweight, event-driven Symfony bundle for **EasyAdmin 4** that allows fields to dynamically appear, disappear, or change their data based on the values of other fields.

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
composer require ucscode/easyadmin-dependency-field-resolver
```

---

## Basic Usage

### 1. The Controller Setup

Inject the `DependencyFieldResolver` into your CRUD Controller and use it within your `configureFields` method.

```php
use Ucscode\EasyAdmin\DependencyFieldResolver\Service\DependencyFieldResolver;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private DependencyFieldResolver $resolver
    ) {}

    public function configureFields(string $pageName): iterable
    {
        return $this->resolver
            ->configureFields(function(): iterable {
                // Do exactly the same thing you would do in `configureFields()` of your crud controller
                // You can return an array or use `yield` to return a Generator
                // However, you should only return *INDEPENDENT* Fields
                yield TextField::new('username');

                yield ChoiceField::new('type')
                    ->setChoices([
                        'Individual' => 'individual',
                        'Organization' => 'org',
                    ]);
            })
            ->dependsOn('type', function(array $values): iterable {
                // This Closure will only run if 'type' is not null
                if ($values['type'] === 'org') {
                    yield TextField::new('companyName');
                    yield AssociationField::new('industry');
                }
            })
            ->dependsOn(['type', 'username'], function(array $values) use ($pageName): iterable {
                // This Closure will only run if both 'type' and 'username' are not null
                if ($values['username'] == 'joe' && $values['type'] == 'org') {
                    yield TextField::new('website');
                    return;
                }
                
                yield ChoiceField::new(...);
            })
            ->resolve();
    }
}

```

---

## How It Works (Server-Side Lifecycle)

This library operates entirely on the server side by hijacking the Symfony Form submission process before it reaches the persistence layer.

### 1. State Encapsulation

The `DependencyFieldResolver` generates a `HiddenField` named `__resolver_state`. This field contains an unmapped base64-encoded snapshot of the "monitored parents" at the time the form was rendered.

### 2. Difference Detection

When the form is submitted (e.g., via a "Save" button or a field that triggers a submit), an event listener (`DependencyStateListener`) listens to easyadmin's `BeforeCrudActionEvent` to compares:

* The **current POST data** (what the user just submitted).
* The **`__resolver_state`** (what the values were before the submission).

### 3. The Redirect Loop

If a difference is detected in any monitored field:

1. The listener **intercepts** the request before the Controller can persist the data.
2. The current POST data is stored in the `ResolverDataBridge` (Session).
3. A `RedirectResponse` is issued to the same URL (GET request) to prevent false validation error message.

### 4. Data Recovery & Dynamic Yielding

On the subsequent GET request:

1. The `DependencyFormExtension` detects data in the `ResolverDataBridge`.
2. It injects this data back into the form fields, ensuring `EntityType` fields have their choices correctly populated.
3. The `DependencyFieldResolver` runs its closures. Since the parent values are now present in the Bridge, the dependent fields are **yielded** and rendered in the UI.

---

## Architecture Summary

| Component | Responsibility |
| --- | --- |
| **`DependencyFieldResolver`** | Defines dependencies and yields fields based on available data. |
| **`DependencyStateListener`** | Compares POST vs. Hidden State; triggers the redirect. |
| **`ResolverDataBridge`** | Acts as temporary storage for form data across the redirect. |
| **`DependencyFormExtension`** | Reconstructs the form state from the Bridge during the GET request. |

---

## Advanced: Events & Data Refinement

You can modify the data during the transition using the `DependencyChangedEvent`. This is useful for clearing specific fields when a parent changes.

### Create a Subscriber

```php
use Ucscode\EasyAdmin\DependencyFieldResolver\Event\DependencyChangedEvent;

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

## 1. Event System & Usage Examples

The library dispatches several events throughout the **Detection → Redirect → Recovery** lifecycle. These allow you to hook into the data flow to modify values, inject metadata, or manipulate fields dynamically.

### `DependencyChangedEvent`

**Location:** Dispatched by the `DependencyStateListener` when it detects a change in a monitored parent field, just before the redirect.  
**Usage:** Sanitize or "reset" dependent data.

```php
public function onDependencyChange(DependencyChangedEvent $event): void
{
    $data = $event->getPostData(); // The ResolverPostData DTO

    // If 'country' changed, clear 'state' so old data doesn't persist
    if ($data->has('country')) {
        $data->set('state', null);
    }
}

```

### `DependencyDataRecoveredEvent`

**Location:** Dispatched in the `FormExtension` when data is successfully pulled from the Bridge (Session).  
**Usage:** Logging or performing global transformations on the recovered dataset.

### `DependencyFieldRehydrateEvent`

**Location:** Dispatched for every individual field during the recovery phase.  
**Usage:** This is the primary hook for custom "inflation." If you have a non-entity field (like a JSON object or a File) that needs special handling, do it here.

---

## Handling Field Requirements

When building dynamic forms, you may want certain dependent fields to be mandatory. While your first instinct might be to use `setRequired(true)`, there is a more flexible approach using Symfony Constraints that provides a better experience during the "Redirect & Recovery" phase.

### The Recommendation

For fields yielded inside a `dependsOn` closure, I recommend setting `setRequired(false)` and enforcing the requirement via **Symfony Constraints** (e.g., `NotBlank`).

### Why this is suggested

When a field is marked as `required` at the form level, EasyAdmin's internal pre-validation often block the form submission if that field is empty.

In a dependency flow, if a user changes a "parent" field but a previously required "child" field is now empty, the form might trigger a validation error immediately. By using constraints instead of the `required` flag, you allow the library to smoothly intercept the data and perform the redirect without the browser or the server's initial validation layer getting in the way.

### An Example

Let's assume you mark a dependent field **state** as `required` and the field depends on a **country**. This might create a "deadlock":

1. User selects **United States** and submits.
2. **State** field appears and is marked `required`.
3. User realizes they meant **United Kingdom** and changes the **Country** field.
4. User clicks "Submit".
5. **Validation Fails:** EasyAdmin sees the **State** field is empty but required. 
6. The form refuses to submit.

### The Recommended Solution

Set field to `required == false` and use **Symfony Constraints** with **Validation Groups** (or simple conditional constraints) to enforce the "required" state.

#### Example Implementation

In this example, the `state` field is logically required, but we handle that requirement through a constraint to ensure the "Redirect & Recovery" cycle remains fluid.

```php
->configureFields(function() {
    yield CountryField::new('country');
})
->dependsOn('country', function(array $values) {
    yield ChoiceField::new('state')
        // Use false to ensure the form can always submit for state-tracking
        ->setRequired(false) 
        ->setFormTypeOptions([
            'constraints' => [
                // Use a constraint to ensure the data is valid upon final save
                new NotBlank([
                    'message' => 'Please select a state for ' . $values['country'],
                ])
            ]
        ]);
})

```

### Benefits of this approach

* **Smoother Transitions:** Users can switch parent values without being "trapped" by browser-level validation tooltips on child fields that are about to change anyway.
* **Reliable State Tracking:** Ensures the `DependencyStateListener` always receives the POST data it needs to calculate the next state.
* **Full Validation:** Your data integrity remains intact; Symfony will still prevent a final save to the database if the `NotBlank` constraint is not met.

---

## License

MIT
