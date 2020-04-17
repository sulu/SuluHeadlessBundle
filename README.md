<h1 align="center">SuluHeadlessBundle</h1>

<p align="center">
    <a href="https://sulu.io/" target="_blank">
        <img width="30%" src="https://sulu.io/uploads/media/800x/00/230-Official%20Bundle%20Seal.svg?v=2-6&inline=1" alt="Official Sulu Bundle Badge">
    </a>
</p>

<p align="center">
    <a href="https://github.com/sulu/SuluHeadlessBundle/blob/master/LICENSE" target="_blank">
        <img src="https://img.shields.io/github/license/sulu/SuluHeadlessBundle.svg" alt="GitHub license">
    </a>
    <a href="https://github.com/sulu/SuluHeadlessBundle/actions" target="_blank">
        <img src="https://img.shields.io/github/workflow/status/sulu/SuluHeadlessBundle/PHP/master.svg?label=github-actions" alt="GitHub actions status">
    </a>
    <a href="https://github.com/sulu/sulu/releases" target="_blank">
        <img src="https://img.shields.io/badge/sulu%20compatibility-%3E=2.0-52b6ca.svg" alt="Sulu compatibility">
    </a>
</p>
<br/>

The **SuluHeadlessBundle** provides controllers and services for using the [Sulu](https://sulu.io/) content 
management system in a headless way. 

To achieve this, the bundle includes a controller that allows to retrieve the 
content of a **Sulu page as plain JSON content**. Furthermore, the bundle provides APIs for accessing the managed
**navigation contexts** and the **search functionality** of Sulu via AJAX requests. Finally, the bundle includes
an optional **single page application setup** that is built upon React and MobX and utilizes the functionality of 
the bundle.


The SuluHeadlessBundle is compatible with Sulu **starting from version 2.0**. Have a look at the `require` section in 
the [composer.json](https://github.com/sulu/SuluHeadlessBundle/blob/master/composer.json) to find an 
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

Include the routes of the bundle in a new `config/routes/sulu_headles_website.yml` file in your project:

```yaml
sulu_headless:
    type: portal
    resource: "@SuluHeadlessBundle/Resources/config/routing_website.yml"
```

This will enable a JSON API to access the **search functionality** of Sulu via `{host}/api/search` and a JSON API for 
retrieving the **navigation contexts** of the project via `{host}/api/navigations/{contextName}`.

### Set the controller of you template

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
to `Sulu\Bundle\WebsiteBundle\Controller\DefaultController::indexAction`. When using the `HeadlessWebsiteController`
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
         "icon": [],
         "images": []
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
controller will render Twig template that is set as `view` of the template of the page. This is similar to the 
default behavior of Sulu and **allows to start a javascript application** that utilizes the functionality of the 
SuluHeadlessBundle after the initial request of the user. 

Be aware that the data that is passed to the Twig template 
by the `HeadlessWebsiteController` contains only scalar values and therefore **might differ from the data** that would 
be passed by the default Sulu `WebsiteController`.

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

- Sulu navigation contexts of the application can be retrieved as JSON object via `{host}/api/navigations/{contextName}`. 
Similar to the Twig extension, the API respects the following query parameters `depth`, `flat` and `excerpt`.

- The search functionality of SULU is accessible as JSON API via via `{host}/api/search?q={searchTerm}`.

### Reference single page application implementation

The SuluHeadlessBundle is completely **frontend independent** and does not require the use of a specific technology or 
framework. Still, the bundle contains an **independent and optional single page application setup** in the 
`Resources/js-website` directory that allows you to quick-start your project and serves as a reference implementation
for utilizing the bundle functionality. 

The provided reference implementation builds upon **React** as rendering library and utilizes **MobX** for state 
management. It is built around a central `viewRegistry` singleton that allows you to register React components 
as view for specific types of resources (eg. pages of a specific template). The application contains a router that will 
intercept the navigation of the browser, load the JSON data for the requested resource and render the respective view
with the loaded data. 

![Reference Frontend Implementation](https://user-images.githubusercontent.com/1698337/73056284-f7175100-3e8e-11ea-9e67-9371d8c65099.jpg)

To use the provided single page application setup, you need to include the following lines in your Twig template to
initialize and start the application:

```twig
{% block content %}
    {# ... #}

    {# define container element for rendering single page application #}
    <div id="sulu-headless-container"></div>
    
    {# initialize application with json data of current page to prevent second request on first load #}
    <script>window.SULU_HEADLESS_VIEW_DATA = {{ jsonData|raw }};</script>
    <script>window.SULU_HEADLESS_API_ENDPOINT = '{{ sulu_content_path('/api') }}';</script>
    
    {# start single page application by including built javascript code #}
    <script src="/build/headless/js/index.js"></script>
{% endblock %}
```

Additionally, you need to add the following files to your project to setup the single page application:

<details>
<summary>assets/headless/package.json</summary>

```json
{
  "name": "my-frontend-application",
  "main": "src/index.js",
  "private": true,
  "scripts": {
    "build": "webpack src/index.js -o ../../public/build/headless/js/index.js --module-bind js=babel-loader -p --display-modules --sort-modules-by size",
    "watch": "webpack src/index.js -w -o ../../public/build/headless/js/index.js  --module-bind js=babel-loader --mode=development --devtool source-map"
  },
  "dependencies": {
    "sulu-headless-bundle": "file:../../vendor/sulu/headless-bundle/Resources/js-website",
    "core-js": "^3.0.0",
    "loglevel": "^1.0.0",
    "mobx": "^4.0.0",
    "mobx-react": "^5.0.0",
    "prop-types": "^15.7.0",
    "react": "^16.8.0",
    "react-dom": "^16.8.0",
    "whatwg-fetch": "^3.0.0",
    "history": "^4.10.1"
  },
  "devDependencies": {
    "@babel/core": "^7.6.0",
    "@babel/plugin-proposal-class-properties": "^7.5.5",
    "@babel/plugin-proposal-decorators": "^7.6.0",
    "@babel/preset-env": "^7.6.0",
    "@babel/preset-react": "^7.0.0",
    "babel-eslint": "^10.0.3",
    "babel-loader": "^8.0.6",
    "webpack": "^4.40.2",
    "webpack-cli": "^3.3.8"
  }
}
```
</details>

<details>
<summary>assets/headless/webpack.config.js</summary>

```javascript
const path = require('path');
const nodeModulesPath = path.resolve(__dirname, 'node_modules');

/* eslint-disable-next-line no-unused-vars */
module.exports = (env, argv) => {
    return {
        resolve: {
            modules: [nodeModulesPath, 'node_modules'],
        },
        resolveLoader: {
            modules: [nodeModulesPath, 'node_modules'],
        },
    };
};
```
</details>

<details>
<summary>assets/headless/babel.config.js</summary>

```javascript
module.exports = {
    presets: ['@babel/env', '@babel/react'],
    plugins: [
        ['@babel/plugin-proposal-decorators', {'legacy': true}],
        ['@babel/plugin-proposal-class-properties', {'loose': true}]
    ]
};
```
</details>

<details>
<summary>assets/headless/src/index.js</summary>

```javascript
import { startApp } from 'sulu-headless-bundle';
import viewRegistry from 'sulu-headless-bundle/src/registries/viewRegistry';
import HeadlessTemplatePage from './views/HeadlessTemplatePage';

// register views for rendering page templates
viewRegistry.add('page', 'headless-template', HeadlessTemplatePage);

// register views for rendering article templates
// viewRegistry.add('article', 'headless-template', HeadlessTemplateArticle);

// start react application in specific DOM element
startApp(document.getElementById('sulu-headless-container'));
```
</details>

<details>
<summary>assets/headless/src/views/HeadlessTemplatePage.js</summary>

```javascript
import React from 'react';
import { observer } from 'mobx-react';

@observer
class HeadlessTemplatePage extends React.Component {
    render() {
        const serializedData = JSON.stringify(this.props.data, null, 2);

        return (<pre>{ serializedData }</pre>);
    }
}

export default HeadlessTemplatePage;
```
</details>

Finally, you can build your frontend application by executing `npm install` and `npm run build` in the `assets/headless`
directory.


## ❤️&nbsp; Support and Contributions

The Sulu content management system is a **community-driven open source project** backed by various partner companies. 
We are committed to a fully transparent development process and **highly appreciate any contributions**. 

In case you have questions, we are happy to welcome you in our official [Slack channel](https://sulu.io/services-and-support).
If you found a bug or miss a specific feature, feel free to **file a new issue** with a respective title and description 
on the the [sulu/SuluHeadlessBundle](https://github.com/sulu/SuluHeadlessBundle) repository.


## 📘&nbsp; License

The Sulu content management system is released under the under terms of the [MIT License](LICENSE).
