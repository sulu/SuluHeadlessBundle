/**
 * This is a backward compatible options for a fetch request, this
 * is needed to support for browsers like Safari 10.1 which has an
 * old implementation of the fetch standard.
 */

const defaultOptions = {
    credentials: 'same-origin',
};

export default (input, init) => {
    return fetch(input, {
        ...defaultOptions,
        ...init,
    });
};
