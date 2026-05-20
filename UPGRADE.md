# Upgrade

## 3.0.0

### Route file moved

If your project still uses `config/routes/sulu_headless_website.yml` from previous versions, rename it to `config/routes/sulu_headless_website.yaml` (or keep the `.yml` filename if you prefer). In either case, update the path inside the route file:

```diff
- config/routes/sulu_headless_website.yml
+ config/routes/sulu_headless_website.yaml

  sulu_headless:
      type: portal
-     resource: "@SuluHeadlessBundle/Resources/config/routing_website.yml"
+     resource: "@SuluHeadlessBundle/Resources/config/routing_website.yaml"
```

### Increased minimum Sulu version to 3.0

The minimum Sulu version was increased from 2.6 to 3.0.

### Increased minimum Symfony version to 6.4

Symfony 5.4 support was dropped. Minimum required version is now 6.4.

### Response format changes

- `nodeType` removed, replaced with `linkType` (contains link provider name or `null` for regular pages)
- `linkType` added to navigation items (contains link provider name or `null` for regular pages)
- `path` removed from navigation responses
- `excerpt.images` renamed to `excerpt.image` (returns single object or `null` instead of array)
- `excerpt.icon` now returns single object or `null` instead of array
- Search endpoint now uses SEAL search engine instead of Massive SearchBundle
- Search `indices` query parameter replaced with `index` (single index, defaults to `website`)
- Search response structure changed from nested `_embedded.hits[].document` to flat `_embedded.hits[]`
- Search hit `imageUrl` field replaced with `media` (contains full serialized media object or `null`)
- Search hit `score` field removed
- Search hit `document.properties` (excerpt, state, etc.) removed — no longer included in response
- Search hit fields changed: `id`, `title`, `url`, `locale` remain; new fields: `resourceKey`, `resourceId`, `content`, `authoredAt`, `webspaces`, `_formatted`, `media`
- Media `type` field changed from an object `{"name": "image", "id": 2}` to a plain string (e.g. `"image"`)

### JavaScript reference implementation removed

The `Resources/js-website` directory containing the optional React/MobX single page application reference implementation has been removed. If your project depended on the `sulu-headless-bundle` npm package from this directory, you will need to implement your own frontend integration.

### HeadlessWebsiteController refactored

The `HeadlessWebsiteController` now extends `ContentController` (from `Sulu\Content\UserInterface\Controller\Website`) instead of `WebsiteController`.

The `indexAction` signature changed to match the new base controller:

```php
// Before
public function indexAction(
    Request $request,
    StructureInterface $structure,
    bool $preview = false,
    bool $partial = false
): Response;

// After
public function indexAction(
    Request $request,
    DimensionContentInterface $object,
    string $view,
    bool $preview = false,
    bool $partial = false,
): Response;
```

The `resolveStructure(StructureInterface $structure)` method was renamed to `resolveHeadlessData(DimensionContentInterface $object, string $locale)`:

```php
// Before
protected function resolveStructure(StructureInterface $structure): array;

// After
protected function resolveHeadlessData(DimensionContentInterface $object, string $locale): array;
```

The `serializeData()` helper method was removed. For non-JSON requests, override `resolveSuluParameters()` instead of `renderStructure()` — the `headless` key is now added there.

### StructureResolverInterface signature changed

The `StructureResolverInterface` now uses `DimensionContentInterface` instead of `StructureInterface`:

```php
// Before
public function resolve(StructureInterface $structure, string $locale, bool $includeExtension = true): array;

// After
public function resolve(DimensionContentInterface $dimensionContent, string $locale, bool $includeExtension = true): array;
```

### ContentTypeResolverInterface signature changed

All content type resolvers now receive `FieldMetadata` instead of `PropertyInterface`:

```php
// Before
public function resolve($data, PropertyInterface $property, string $locale, array $attributes = []): ContentView;

// After
public function resolve(mixed $data, FieldMetadata $fieldMetadata, string $locale, array $attributes = []): ContentView;
```

### ResourceLocatorResolver removed

The `ResourceLocatorResolver` content type resolver has been removed.

### DataProviderResolverInterface namespace change

The `ProviderConfigurationInterface` import changed:

```php
// Before
use Sulu\Component\SmartContent\Configuration\ProviderConfigurationInterface;

// After
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\ProviderConfigurationInterface;
```

## 0.11.0

### Increased minimum PHP version to 8.2

The minimum PHP version was increased from 7.3 to 8.2.

### Increased minimum Sulu version to 2.6

The minimum Sulu version was increased from 2.4 to 2.6.

## 0.10.0

### Add extension placeholder to serialized medias

Before this update the preferred extension was always added to the uri. 
Now the preferred extension is extracted into a new field `preferredExtension` and in the uri the placeholder `{extension}` is used instead of the extension.

For non-image medias the `formatUri` as well as the `formatPreferredExtension` fields are omitted.

**Before:**
```json
{
    "formatUri": "/media/1/{format}/media-1.png?v=1-0"
}
```

**After:**
```json
{
    "formatPreferredExtension": "png",
    "formatUri": "/media/1/{format}/media-1.{extension}?v=1-0"
}
```

## 0.9.0

### Make NavigationInvalidationSubscriber::collectNavigationContexts method private

## 0.6.0

### Change constructor arguments of SingleSnippetSelectionResolver

The constructor arguments of the `SingleSnippetSelectionResolver` (`$contentMapper`, `$structureResolver`, `$defaultSnippetManager`)
have been replaced by a single argument `$snippetSelectionResolver`, which accepts an instance of the `SnippetSelectionResolver` class.

## 0.4.0

### Increased mimimum PHP version to 7.3

The mimimum PHP version was increased from 7.2 to 7.3. The reason is that PHP 7.2 is not maintained anymore and this
allows the bundle to use the `JSON_THROW_ON_ERROR` flag.

### Changed attributes which are passed to templates by the HeadlessWebsiteController

The attributes that are passed to the .twig template by the `HeadlessWebsiteController` were changed to improve
compatibility with the `DefaultController` of Sulu. 

If a page is requested without the `.json` suffix, the `HeadlessWebsiteController` will render the configured Twig 
template with the data of the page. Before this change, the `HeadlessWebsiteController` passed the data generated
by the `StructureResolver` to the template.  While this is the same data that is used for generating the `.json`
response, it might be different to the data that is passed to the template by the `DefaultController` when not using 
the HeadlessBundle. This behaviour makes it unnecessarily difficult to switch from the `DefaultController` to the 
`HeadlessWebsiteController` inside of a project.

After this change, the data that is passed to the template by the `HeadlessWebsiteController` will be compatible to 
the data that would be passed by the `DefaultController`. Additionally, the data passed by the 
`HeadlessWebsiteController` includes a `headless` attribute that contains the data generated 
by the `StructureResolver`.

In the course of this change, the `jsonData` attribute was removed from the data passed to the template. If you need
the JSON string in your template, you can use `headless|json_encode` instead.

**Before:**

```php
[
    "type" => "page",
    "authored" => "2020-11-25T14:31:23+0000",
    "changed" => "2020-11-25T16:23:59+0000",
    "created" => "2020-11-25T14:31:24+0000",
    "content" => ["...content resolved by the StructureResolver"],
    "view" => ["...view resolved by the StructureResolver"],
    "extension" => ["...extension resolved by the StructureResolver"],
    "jsonData" => "json string representation of data returned by the StructureResolver"
];
```

```twig
{{ jsonData|raw }}
```

**After:**

```php
[
    "authored" => DateTime::class,
    "changed" => DateTime::class,
    "created" => DateTime::class,
    "content" => ["...content resolved by the DefaultController"],
    "view" => ["...view resolved by the DefaultController"],
    "extension" => ["...extension resolved by the DefaultController"],
    "headless" => [
        "type" => "page",
        "authored" => "2020-11-25T14:31:23+0000",
        "changed" => "2020-11-25T16:23:59+0000",
        "created" => "2020-11-25T14:31:24+0000",
        "content" => ["...content resolved by the StructureResolver"],
        "view" => ["...view resolved by the StructureResolver"],
        "extension" => ["...extension resolved by the StructureResolver"],
    ]   
];
```

```twig
{{ headless|json_encode|raw }}
```

## 0.2.0

### View Parameter of Single and Multi Selection Content Types changed

The view parameter of the single and multi selection has changed to be consistent through all selections:

**Before:**

```json
"view" {
    "single_selection": 1,
    "multi_selection": [1, 2],
}
```

**After:**

```json
"view" {
    "single_selection": {
        "id": 1,
    },
    "multi_selection": {
        "ids": [1, 2]
    },
}
```

### Data given into Twig file changed

The data given to has changed to fill out the meta tags correctly:

**Before:**

```json
{
    "jsonData": "...",
    "data": {
        "content": {},
        "view": {},
        "extension": {
            "seo": {}
        }
    }
}
```

**After:**

```json
{
    "jsonData": "...",
    "content": {},
    "view": {},
    "extension": {
        "seo": {}
    }
}
```

### View Parameter of Single and Multi Selection Content Types changed

The view parameter of the single and multi selection has changed to be consistent through all selections:

**Before:**

```json
"view" {
    "single_selection": 1,
    "multi_selection": [1, 2],
}
```

**After:**

```json
"view" {
    "single_selection": {
        "id": 1,
    },
    "multi_selection": {
        "ids": [1, 2]
    },
}
```

### Refactored serializer services to accept doctrine-entities and locale instead of api-entities

The following services where adjusted to accept doctrine-entities and locale instead of api-entities:

* `AccountSerializer`
* `CategorySerializer`
* `ContactSerializer`
* `MediaSerializer`

The reason for this change is that it makes the services more flexible to use. Furthermore, it simplifies things when 
overwriting Sulu entities such as the `Media` entity inside of a project. This change does not affect the format nor 
the content of the data returned by the services.

### Refactored PageDataProviderResolver to use StructureResolver for serializing pages

The `PageDataProviderResolver` was refactored to use the `StructureResolver` service for serializing matching pages.
The reason for this change is that the old strategy resolved the data of matching pages with the default Sulu content 
types instead of the resolvers of this bundle. 

The new strategy changes the format of the data that is returned when using a `smart_content` property with the
`page` data-provider inside of a page template.

### Refactored SingleAccountSelectionResolver to return null instead of an empty array if selection is empty

### Refactored SingleContactSelectionResolver to return null instead of an empty array if selection is empty

### Refactored SinglePageSelectionResolver to return null instead of an empty array if selection is empty

### Refactored PageSelectionResolver to use StructureResolver for serializing pages

The `PageSelectionResolver` was refactored to use the `StructureResolver` service for serializing selected pages.
The reason for this change is that the old strategy resolved the data of selected pages with the default Sulu content 
types instead of the resolvers of this bundle. 

The new strategy changes the format of the data that is returned when using a `page_selection` property inside of a page
template.
