import React from 'react';
import ReactDOM from 'react-dom';
import ViewRenderer from './containers/ViewRenderer';
import interceptAnchorClick from './utils/interceptAnchorClick';

/**
 * Starts the Application.
 */
function startApp(container, rootComponent) {
    const appContainer = container || document.getElementById('sulu-headless-container');
    const RootComponent = rootComponent || ViewRenderer;

    document.addEventListener('click', interceptAnchorClick);
    ReactDOM.render(
        <RootComponent/>,
        appContainer
    );
}

export {
    startApp,
};

export default startApp;
