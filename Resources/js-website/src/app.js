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

    const appElement = (
        <div onClick={interceptAnchorClick}>
            <RootComponent/>
        </div>
    );

    ReactDOM.render(appElement, appContainer);
}

export {
    startApp,
};

export default startApp;
