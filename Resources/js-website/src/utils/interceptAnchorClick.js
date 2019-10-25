import router from '../services/router';

// eslint-disable-next-line require-jsdoc
function findClickedAnchorElement(clickTarget) {
    if (clickTarget.tagName.toLowerCase() === 'a') {
        return clickTarget;
    }

    if (clickTarget.parentNode) {
        return findClickedAnchorElement(clickTarget.parentNode);
    }

    return null;
}

/**
 * This function is responsible for calling the respective functions of the router instead of navigating the browser
 * when an anchor that points to the current host is clicked.
 * It must be registered as an event listener on the document.
 */
export default (clickEvent) => {
    // Do not call router if the default was already prevented by another listener
    if (clickEvent.defaultPrevented) {
        return;
    }

    // Execute the default action if the click is not a left-click
    if (clickEvent.button !== 0) {
        return;
    }

    // Execute the default action if a modifier key was pressed
    if (clickEvent.metaKey || clickEvent.altKey || clickEvent.ctrlKey || clickEvent.shiftKey) {
        return;
    }

    // Execute the default action if the target of the click is not a anchor
    const clickedAnchor = findClickedAnchorElement(clickEvent.target);
    if (!clickedAnchor) {
        return;
    }

    // Execute default action if origin of the anchor is not equal to the current origin
    if (clickedAnchor.origin !== window.location.origin) {
        return;
    }

    // Execute the default action if the target of the anchor is not empty or _self
    if (clickedAnchor.target && clickedAnchor.target !== '_self') {
        return;
    }

    clickEvent.preventDefault();
    router.assign(clickedAnchor.pathname, clickedAnchor.search);
};
