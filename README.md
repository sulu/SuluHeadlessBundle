<h1 align="center">SuluHeadlessBundle</h1>

<p align="center">
    <a href="https://sulu.io/" target="_blank">
        <img width="30%" src="https://sulu.io/uploads/media/800x/00/230-Official%20Bundle%20Seal.svg?v=2-6&inline=1" alt="Official Sulu Bundle Badge">
    </a>
</p>

<p align="center">
    <a href="LICENSE" target="_blank">
        <img src="https://img.shields.io/github/license/sulu/SuluHeadlessBundle.svg" alt="GitHub license">
    </a>
    <a href="https://github.com/sulu/SuluHeadlessBundle/actions" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/sulu/SuluHeadlessBundle/test-application.yaml" alt="Test workflow status">
    </a>
    <a href="https://github.com/sulu/sulu/releases" target="_blank">
        <img src="https://img.shields.io/badge/sulu%20compatibility-%3E=3.0-52b6ca.svg" alt="Sulu compatibility">
    </a>
</p>
<br/>

The **SuluHeadlessBundle** provides controllers and services for using the [Sulu](https://sulu.io/) content 
management system in a headless way. 

To achieve this, the bundle includes a controller that allows to retrieve the 
content of a **Sulu page as plain JSON content**. Furthermore, the bundle provides APIs for accessing features that are 
available via Twig extensions in traditional templates such as navigation contexts and snippet areas.


The SuluHeadlessBundle is compatible with Sulu **starting from version 2.0**. Have a look at the `require` section in 
the [composer.json](composer.json) to find an 
**up-to-date list of the requirements** of the bundle.
Please be aware that this bundle is **still under development** and might not cover every use-case yet.
Depending on the feedback of the community, future versions of the bundle might contain breaking changes.


## 🚀&nbsp; Installation and Usage

### Install the bundle 

Execute the following [composer](https://getcomposer.org/) command to add the bundle to the dependencies of your 
project:

```bash
composer require sulu/headless-bundle
```

### Enable the bundle 

Enable the bundle by adding it to the list of registered bundles in the `config/bundles.php` file of your project:

```php
return [
    /* ... */
    Sulu\Bundle\HeadlessBundle\SuluHeadlessBundle::class => ['all' => true],
];
```

### Include the routes of the bundle

Include the routes of the bundle in a new `config/routes/sulu_headless_website.yaml` file in your project:

```yaml
sulu_headless:
    type: portal
    resource: "@SuluHeadlessBundle/Resources/config/routing_website.yaml"
```

### Set the controller of your template

To provide an API for retrieving the content of a page in the JSON format, the controller of the page template
must be set to the `HeadlessWebsiteController` included in this bundle:

```xml
<?xml version="1.0" ?>
<template xmlns="..." xmlns:xsi="..." xsi:schemaLocation="...">
    <!-- ... -->
    <controller>Sulu\Bundle\HeadlessBundle\Controller\HeadlessWebsiteController::indexAction</controller>
    <!-- ... -->
</template>
```

This controller will provide the **content of the page as JSON object** if the page is requested in the JSON format 
via `{pageUrl}.json`.


## 💡&nbsp; Key Concepts

### Deliver content of pages with the HeadlessWebsiteController 

The main use-case of the SuluHeadlessBundle is **delivering the content of a page as a JSON object**. This can be 
enabled individually per template by setting the controller of the template of the page 
to `Sulu\Bundle\HeadlessBundle\Controller\HeadlessWebsiteController::indexAction`. When using the `HeadlessWebsiteController`
as controller for a template, the content of the page is available as JSON object via `{pageUrl}.json`.

Additionally to the content of the page, the JSON object returned by the `HeadlessWebsiteController` contains **meta 
information** such as the page template and the data of the page excerpt:

```json
{
   "id": "a5181a5a-b030-4933-b3b0-e9faf7ec756c",
   "type": "page",
   "template": "headless-template",
   "content": {
      "title": "Headless Example Page",
      "url": "/headless-example",
      "contacts": [
         {
            "id": 416,
            "firstName": "Homer",
            "lastName": "Simpson",
            "fullName": "Homer Simpson",
            "title": "Dr. ",
            "position": "Nuclear safety Inspector at the Springfield Nuclear Power Plan"
         }
      ]
   },
   "view": {
      "title": [],
      "url": [],
      "contacts": []
   },
   "extension": {
      "seo": {
         "title": "",
         "description": "",
         "keywords": "",
         "canonicalUrl": "",
         "noIndex": false,
         "noFollow": false,
         "hideInSitemap": false
      },
      "excerpt": {
         "title": "",
         "more": "",
         "description": "",
         "categories": [],
         "tags": [],
         "icon": null,
         "image": null
      }
   },
   "author": "2",
   "authored": "2019-12-03T11:01:38+0100",
   "changer": 2,
   "changed": "2020-01-30T07:47:46+0100",
   "creator": 2,
   "created": "2019-12-03T11:01:38+0100"
}
```

If the content of a page that uses the `HeadlessWebsiteController` is requested without the `.json` suffix, the
controller will render Twig template that is set as `view` of the template of the page. 
In this case, the data that would have been returned in case of a `.json` request is available in the twig 
template via a `headless` variable.

#### Resolve content data to scalar values via ContentTypeResolver

Internally, Sulu uses `ContentType` services that are responsible for persisting page content when a page is modified 
and resolving the data that is passed to the Twig template when a page is rendered. Unfortunately, some `ContentType` 
services pass non-scalar values such as media entities to the Twig template. As a JSON object must contain only scalar
values, the SuluHeadlessBundle cannot use the existing `ContentType` services for resolving the content of a page.

To solve this problem, the SuluHeadlessBundle introduces `ContentTypeResolver` services to resolve the content of
pages to scalar values. The bundle already includes `ContentTypeResolver` services for various content types.
If your project includes custom content types or if you are not satisfied with an existing `ContentTypeResolver`, 
you can register your own `ContentTypeResolver` by implementing the `ContentTypeResolverInterface` and
adding a `sulu_headless.content_type_resolver` tag to the service.

### Provide popular Sulu functionality via JSON APIs

The Sulu content management system comes with various services and Twig extensions to simplify the development and the
rendering complex websites. This functionality is not available when serving the content of the website in a headless 
way, therefore the SuluHeadlessBundle includes controllers to **provide JSON APIs for accessing these features**.

The APIs are registered as portal URLs and therefore their path is prefixed with the URL of the webspace.
If you have configured a language-specific URL for your webspace, the API URL will look something like this:

 - `https://example.org/en/api/...`

#### Navigation

`/api/navigations/{contextKey}`

| Parameter        | Type    | Default Value | Description
|------------------|---------|---------------|---------------------------------------
| depth            | integer | `1`           | Maximum depth of the navigation tree that is loaded.
| flat             | boolean | `false`       | Return navigation as flat list instead of tree.
| excerpt          | boolean | `false`       | Include excerpt data in the returned navigation.

Example: `/api/navigations/main?depth=2&flat=false&excerpt=true`

#### Search

`/api/search?q={searchTerm}`

| Parameter        | Type    | Default Value | Description
|------------------|---------|---------------|---------------------------------------
| q                | string  |               | The text you want to search for.

Example: `/api/search?q=CMS`

#### Snippet Areas

`/api/snippet-areas/{area}`

| Parameter        | Type    | Default Value | Description
|------------------|---------|---------------|---------------------------------------------------
| includeExtension | boolean | `false`       | Include extension data (e.g. excerpt) in the returned result.

Example: `/api/snippet-areas/settings?includeExtension=true`

#### Analytics

`/api/analytics.json`

## ❤️&nbsp; Support and Contributions

The Sulu content management system is a **community-driven open source project** backed by various partner companies. 
We are committed to a fully transparent development process and **highly appreciate any contributions**. 

In case you have questions, we are happy to welcome you in our official [Slack channel](https://sulu.io/services-and-support).
If you found a bug or miss a specific feature, feel free to **file a new issue** with a respective title and description 
on the [sulu/SuluHeadlessBundle](https://github.com/sulu/SuluHeadlessBundle) repository.


## 📘&nbsp; License

The Sulu content management system is released under the under terms of the [MIT License](LICENSE).
