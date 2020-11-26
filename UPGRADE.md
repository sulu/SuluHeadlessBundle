# Upgrade

## dev-master

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

## 0.2.0

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
