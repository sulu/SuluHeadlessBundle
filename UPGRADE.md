# Upgrade

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
