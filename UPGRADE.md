# Upgrade

## master

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
