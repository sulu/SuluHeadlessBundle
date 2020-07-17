# Upgrade

## master

### Refactored serializer services to accept doctrine-entities and locale instead of api-entities

The following services where adjusted to accept doctrine-entities and locale instead of api-entities:
* `AccountSerializer`
* `CategorySerializer`
* `ContactSerializer`
* `MediaSerializer`

The reason for this change is that it makes the services mor flexible to use. Furthermore, it simplifies things when 
overwriting Sulu entities such as the `Media` entity inside of a project. This change does not affect the format nor 
the content of the date returned by the services.
