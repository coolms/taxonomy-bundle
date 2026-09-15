# Changelog

All notable changes to `coolms/taxonomy-bundle` are recorded here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Added

- Declares `support` -- `issues` and `source` -- so a page imported from this
  package, and the catalogue, know where a correction is filed. Packagist filled
  the gap from GitHub when the manifest was silent; the declared field is the
  one that holds on any registry.
**A test suite.** `phpunit.xml.dist`, the `tests/` namespace and the dev
dependency, copied from the field family this package was built after. CI
runs the suite outright: the step that printed "no tests in this package
yet" and exited green is gone, so an empty suite now fails the build
instead of reporting success over nothing. The first tests here are the
ones the application had been carrying for this package: `TaxonomyFieldWidgetProviderTest`.

## 2.0.0-alpha1 - 2026-09-10

**A pre-release. It carries no compatibility promise.**

### Added

- `TaxonomyBundle` and its DI extension: Doctrine mapping pointed at
  `coolms/taxonomy-doctrine`, the two repositories aliased to the domain
  contracts, and the nested-set operator from `coolms/core` bound to
  `TaxonomyNode`.
- Console: `coolms:taxonomy:tree:create`, `coolms:taxonomy:node:list`.
- API Platform: `TaxonomyNodeResource` and `TaxonomyTreeResource` with two
  providers and six processors, at `/taxonomy/trees` and `/taxonomy/nodes`.
- A field widget provider and a navigation toolbar contributor.

### Every service is registered explicitly, and that is not style

While this family lived in the application, twelve of these classes were picked
up by the application's `App\:` prototype scan. Moving them into a package
removed them from it - and **nothing reported that**. The services simply
stopped existing: `cache:clear` stayed green, because a service nobody
references does not fail compilation. Seven tests found them by asking the
container for something that was no longer there.

So the extension registers each provider, processor, command and tagged
contributor by name. Add to `registerServices()` in the same change as the
class.

### The mapping is registered with `is_bundle: false`

With it true, `dir` resolves against the bundle directory rather than the path
given. That produced the only hard failure in the extraction - a non-existent
mapping directory - and it surfaced *after* the autoloader reported every class
as loadable, because loading is not mapping.

### The API surface is pinned

Explicit `shortName`, explicit `uriTemplate` per operation, explicit operation
`name`. A namespace change therefore cannot move a path, an operation name,
`@type` or the context IRI. Dropping the explicit `shortName` would move the
last two silently.

### Why `coolms/entity` is constrained to `^2.0.0-alpha3`

`CoolMS\Entity\Attribute\DiscriminatorValue` moved into `coolms/entity` and
first shipped in **v2.0.0-alpha3**. Earlier releases resolve cleanly and then
fail the moment anything reflects the attribute, because PHP resolves an
attribute class lazily: the entity autoloads, `getAttributes()` returns an entry,
and only `newInstance()` throws.

⚠️ So `^2.0` would have been wrong in the quiet way. It installs and breaks on
first boot, and no `class_exists` check finds it.

~~Until alpha3 existed this was expressed as
`"conflict": {"coolms/entity": "<=2.0.0-alpha2"}`~~ -- a constraint can only name
a release that exists, so the conflict stood in for the floor until the floor
could be written. It has been removed now that it can.
