# coolms/taxonomy-bundle

Symfony wiring for the taxonomy family: the bundle, its DI extension, two
console commands and the API Platform surface.

```bash
composer require coolms/taxonomy-bundle
```

Requiring this pulls in `coolms/taxonomy` and `coolms/taxonomy-doctrine`, so it
is the only one of the three you name directly.

```php
// config/bundles.php
CoolMS\Taxonomy\Bundle\TaxonomyBundle::class => ['all' => true],
```

## What it wires

- Doctrine mapping, pointed at `coolms/taxonomy-doctrine`'s XML
- the two repositories, aliased to the domain's contracts
- the nested-set operator from `coolms/core`, bound to `TaxonomyNode`
- `coolms:taxonomy:tree:create` and `coolms:taxonomy:node:list`
- API Platform resources at `/taxonomy/trees` and `/taxonomy/nodes`
- a field widget provider and a navigation toolbar contributor

## The API surface is pinned on purpose

`TaxonomyNodeResource` and `TaxonomyTreeResource` declare an explicit
`shortName`, an explicit `uriTemplate` on every operation, and an explicit
operation `name`.

⚠️ **Keep `shortName` explicit.** If it is dropped and allowed to derive from the
class name, `@type` and the JSON-LD context IRI move. Nothing in this repository
reads either, so the change is silent here and visible to every consumer.

## Registering services, if you extend this

Every service in this package is registered by the extension, explicitly.

⚠️ **A class that moves into a package leaves the consuming application's
service scan, and nothing reports it.** The service simply stops existing;
`cache:clear` stays green, because a service nobody references does not fail
compilation. Only something that resolves it says so. When this family was
extracted, twelve services vanished that way and seven tests found them.

So if you add a provider, processor, command or tagged contributor here, add it
to `registerServices()` in the same change.

## Requires

`api-platform/core`, `coolms/core`, `coolms/core-bundle`, `coolms/entity`,
`coolms/rql`, `coolms/taxonomy`, `coolms/taxonomy-doctrine`, and the Symfony
config, console, dependency-injection, http-foundation, http-kernel and uid
components.
