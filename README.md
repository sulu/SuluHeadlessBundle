# SuluHeadlessBundle

This bundle provides api controller to query pages/navigations and a basic setup to use sulu as a headless CMS.

## Installation

Add repository to `composer.json`:

```json
{
    "repositories": [
        {
            "type": "git",
            "url": "git@gitlab.sulu.io:bundles/headless-bundle.git"
        }
    ]
}
```

Require package:

```bash
composer require sulu/headless-bundle
```

Enable bundle in `config/bundles.php`:

```php
return [
    Sulu\Bundle\HeadlessBundle\SuluHeadlessBundle::class => ['all' => true],
];
```

Add routes to website routing `config/routes/sulu_website.yml`:

````yaml
sulu_headless:
    type: portal
    resource: "@SuluHeadlessBundle/Resources/config/routing_website.xml"
```
