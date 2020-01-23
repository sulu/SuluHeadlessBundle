# SuluHeadlessBundle

This bundle provides api controller to query pages/navigations and a basic setup to use sulu as a headless CMS.

## Installation

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

```yaml
sulu_headless:
    type: portal
    resource: "@SuluHeadlessBundle/Resources/config/routing_website.yml"
```

## Frontend Application Setup

Create a new javascript project by adding the following files to a `assets/headless` folder:

### package.json

```json
{
  "name": "sulu-headless-bundle",
  "description": "A collection of react components to build a headless frontend based on Sulu",
  "main": "src/index.js",
  "private": true,
  "scripts": {
    "build": "webpack src/index.js -o ../../public/build/headless/js/index.js --module-bind js=babel-loader -p --display-modules --sort-modules-by size",
    "watch": "webpack src/index.js -w -o ../../public/build/headless/js/index.js  --module-bind js=babel-loader --mode=development --devtool source-map",
    "lint": "eslint src",
    "lint:fix": "eslint src --fix",
    "depcruise": "depcruise src -c dependency-cruiser.json"
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
    "dependency-cruiser": "^5.1.1",
    "eslint": "^6.4.0",
    "eslint-config-ma": "^1.1.0",
    "eslint-plugin-compat": "^3.3.0",
    "eslint-plugin-import": "^2.18.2",
    "eslint-plugin-react": "^7.14.3",
    "webpack": "^4.40.2",
    "webpack-cli": "^3.3.8"
  }
}
```

### webpack.config.js

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

### browserslistrc

```rc
> 1% in alt-EU
last 3 version and not dead
```

### .eslintrc

```json
{
    "root": true,
    "extends": [
        "eslint-config-ma",
        "plugin:import/errors",
        "plugin:import/warnings",
        "plugin:compat/recommended",
        "plugin:react/recommended"
    ],
    "parser": "babel-eslint",
    "plugins": [
        "import"
    ],
    "settings": {
        "react": {
            "version": "detect"
        },
        "polyfills": [
            "fetch",
            "Promise"
        ]
    },
    "rules": {
        "no-console": "error",
        "import/no-unresolved": "error",
        "import/no-dynamic-require": "error",
        "import/no-webpack-loader-syntax": "error",
        "import/export": "error",
        "import/no-extraneous-dependencies": "error",
        "import/no-absolute-path": "error",
        "import/named": "error",
        "import/namespace": "error",
        "import/default": "error",
        "import/no-self-import": "error",
        "import/no-cycle": "error",
        "import/no-useless-path-segments": "error",
        "import/no-unused-modules": "error",
        "import/no-named-as-default": "error",
        "import/no-named-as-default-member": "error",
        "import/no-deprecated": "error",
        "import/no-mutable-exports": "error",
        "import/unambiguous": "error",
        "import/no-commonjs": "error",
        "import/no-amd": "error",
        "import/no-nodejs-modules": "error",
        "import/first": "error",
        "import/exports-last": "error",
        "import/no-duplicates": "error",
        "import/no-namespace": "error",
        "import/extensions": "error",
        "import/order": "error",
        "import/newline-after-import": "error",
        "import/prefer-default-export": "error",
        "import/no-named-default": "error",
        "import/group-exports": "error"
    }
}
```

### dependency-cruiser.json

```json
{
  "forbidden": [
    {
      "name": "components-not-to-containers",
      "severity": "error",
      "from": {"path": "js-website/components"},
      "to": {"path": "containers"}
    },
    {
      "name": "components-not-to-services",
      "severity": "error",
      "from": {"path": "js-website/components"},
      "to": {"path": "services"}
    },
    {
      "name": "components-not-to-stores",
      "severity": "error",
      "from": {"path": "js-website/components"},
      "to": {"path": "stores"}
    },
    {
      "name": "components-not-to-views",
      "severity": "error",
      "from": {"path": "js-website/components"},
      "to": {"path": "views"}
    },
    {
      "name": "containers-not-to-views",
      "severity": "error",
      "from": {"path": "js-website/containers"},
      "to": {"path": "views"}
    },
    {
      "name": "services-not-to-components",
      "severity": "error",
      "from": {"path": "js-website/services"},
      "to": {"path": "components"}
    },
    {
      "name": "services-not-to-containers",
      "severity": "error",
      "from": {"path": "js-website/services"},
      "to": {"path": "containers"}
    },
    {
      "name": "services-not-to-views",
      "severity": "error",
      "from": {"path": "js-website/services"},
      "to": {"path": "views"}
    },
    {
      "name": "stores-not-to-components",
      "severity": "error",
      "from": {"path": "js-website/stores"},
      "to": {"path": "components"}
    },
    {
      "name": "stores-not-to-containers",
      "severity": "error",
      "from": {"path": "js-website/stores"},
      "to": {"path": "containers"}
    },
    {
      "name": "stores-not-to-views",
      "severity": "error",
      "from": {"path": "js-website/stores"},
      "to": {"path": "views"}
    }
  ]
}
```

### src/index.js

```javascript
import { startApp } from 'sulu-headless-bundle';
import viewRegistry from 'sulu-headless-bundle/src/registries/viewRegistry';
import staticRouteRegistry from 'sulu-headless-bundle/src/registries/staticRouteRegistry';
import HeadlessExamplePage from './views/HeadlessExamplePage';
import StaticExample from './views/StaticExample';
import Application from './Application';

staticRouteRegistry.add('/static-test', {
    type: 'static', template: 'default',
});

viewRegistry.add('static', 'default', StaticExample);
viewRegistry.add('page', 'headless-example', HeadlessExamplePage);

startApp(
    document.getElementById('sulu-headless-container'),
    Application
);
```
